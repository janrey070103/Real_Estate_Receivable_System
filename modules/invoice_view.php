<?php
/**
 * Invoice View and Print Page
 * Display invoice with printable format
 */

// Include authentication
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';
require_once '../includes/financial_helpers.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// Get invoice ID
$invoice_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

if ($invoice_id <= 0) {
    set_flash_message('danger', 'Invalid invoice ID.');
    header('Location: invoices.php');
    exit();
}

try {
    // Fetch invoice details
    $stmt = $pdo->prepare("\n        SELECT \n            i.*,\n            p.property_id,\n            p.property_name,\n            p.total_price as property_total,\n            p.client_id,\n            p.term_months,\n            c.name as client_name,\n            c.address as client_address,\n            c.email as client_email,\n            c.contact_no as client_phone,\n            ps.due_date as schedule_due_date,\n            ps.amount_due as schedule_amount,\n            ps.schedule_id,\n            ps.schedule_number,\n            ps.property_id as schedule_property_id\n        FROM invoices i\n        LEFT JOIN payment_schedules ps ON i.schedule_id = ps.schedule_id\n        LEFT JOIN properties p ON i.property_id = p.property_id OR ps.property_id = p.property_id\n        LEFT JOIN clients c ON p.client_id = c.client_id\n        WHERE i.invoice_id = ?\n    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        set_flash_message('danger', 'Invoice not found.');
        header('Location: invoices.php');
        exit();
    }

    // Get the correct property ID (from invoice or from schedule)
    $property_id_for_balance = $invoice['property_id'] ?? $invoice['schedule_property_id'] ?? 0;
    
    // Calculate property balance
    $property_balance = calculate_property_balance($pdo, $property_id_for_balance);

    // Determine computed status
    $computed_status = $invoice['status'];
    if ($invoice['status'] === 'unpaid' && $invoice['due_date'] < date('Y-m-d')) {
        $computed_status = 'overdue';
    }

    // Fetch payment history for this invoice
    $payments_stmt = $pdo->prepare("
        SELECT p.*, ps.schedule_id
        FROM payments p
        INNER JOIN payment_schedules ps ON p.schedule_id = ps.schedule_id
        WHERE ps.schedule_id = ? OR EXISTS (
            SELECT 1 FROM invoices i2 
            WHERE i2.invoice_id = ? 
            AND (i2.schedule_id = ps.schedule_id OR i2.property_id = ps.property_id)
        )
        ORDER BY p.date_paid DESC
    ");
    $payments_stmt->execute([$invoice['schedule_id'] ?? 0, $invoice_id]);
    $payments = $payments_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Invoice view error: " . $e->getMessage());
    set_flash_message('danger', 'Database error occurred.');
    header('Location: invoices.php');
    exit();
}

// Set page title
$page_title = 'Invoice ' . $invoice['invoice_no'];

// Don't include header/footer if print mode
if (!$print_mode) {
    include '../templates/header.php';
}
?>

<?php if (!$print_mode): ?>
    <!-- Include Navigation -->
    <?php include '../templates/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <div class="main-content">
        <?php endif; ?>

        <?php if ($print_mode): ?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Invoice <?php echo htmlspecialchars($invoice['invoice_no']); ?></title>
                <link rel="stylesheet" href="../assets/bootstrap/bootstrap.min.css">
                <style>
                    @media print {
                        .no-print {
                            display: none !important;
                        }

                        body {
                            background: white;
                        }

                        .invoice-container {
                            box-shadow: none !important;
                        }
                    }

                    .invoice-container {
                        max-width: 800px;
                        margin: 20px auto;
                        background: white;
                        padding: 40px;
                        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                    }

                    .invoice-header {
                        border-bottom: 3px solid #ff6b35;
                        padding-bottom: 20px;
                        margin-bottom: 30px;
                    }

                    .invoice-title {
                        font-size: 2rem;
                        color: #ff6b35;
                        font-weight: bold;
                    }

                    .invoice-details td {
                        padding: 8px 0;
                    }

                    .invoice-table th {
                        background-color: #ff6b35;
                        color: white;
                        padding: 12px;
                    }

                    .invoice-table td {
                        padding: 10px 12px;
                        border-bottom: 1px solid #dee2e6;
                    }

                    .total-row {
                        font-size: 1.2rem;
                        font-weight: bold;
                        background-color: #f8f9fa;
                    }
                </style>
            </head>

            <body>
            <?php endif; ?>

            <?php if (!$print_mode): ?>
                <div class="container-fluid py-4">
                    <div class="row justify-content-center">
                        <div class="col-lg-10">
                            <!-- Page Header -->
                            <div class="page-header mb-4 no-print">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <a href="invoices.php" class="btn btn-outline-secondary me-3">
                                            <span>←</span> Back
                                        </a>
                                        <div>
                                            <h2 class="mb-0"><span>📄</span> Invoice Details</h2>
                                            <p class="text-muted mb-0">
                                                <?php echo htmlspecialchars($invoice['invoice_no']); ?></p>
                                        </div>
                                    </div>
                                    <div>
                                        <button onclick="window.print()" class="btn btn-primary me-2">
                                            <span>🖨️</span> Print Invoice
                                        </button>
                                        <?php if ($invoice['status'] === 'unpaid'): ?>
                                            <a href="record_payment.php?id=<?php echo $invoice['schedule_id']; ?>"
                                                class="btn btn-success">
                                                <span>💳</span> Record Payment
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Invoice Container -->
                        <div class="invoice-container">

                            <!-- Invoice Header -->
                            <div class="invoice-header">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="invoice-title">INVOICE</div>
                                        <div class="text-muted">Real Estate Receivable System</div>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <h4 class="mb-1"><?php echo htmlspecialchars($invoice['invoice_no']); ?></h4>
                                        <div class="mb-2">
                                            <?php
                                            $status_class = 'secondary';
                                            if ($computed_status === 'paid')
                                                $status_class = 'success';
                                            elseif ($computed_status === 'overdue')
                                                $status_class = 'danger';
                                            elseif ($computed_status === 'unpaid')
                                                $status_class = 'warning';
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?> fs-6">
                                                <?php echo strtoupper($computed_status); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bill To & Invoice Info -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-uppercase text-muted mb-2">Bill To:</h6>
                                    <div class="mb-3">
                                        <strong
                                            class="fs-5"><?php echo htmlspecialchars($invoice['client_name']); ?></strong><br>
                                        <span
                                            class="text-muted"><?php echo htmlspecialchars($invoice['client_address'] ?? 'N/A'); ?></span><br>
                                        <?php if ($invoice['client_email']): ?>
                                            <span class="text-muted">📧
                                                <?php echo htmlspecialchars($invoice['client_email']); ?></span><br>
                                        <?php endif; ?>
                                        <?php if ($invoice['client_phone']): ?>
                                            <span class="text-muted">📱
                                                <?php echo htmlspecialchars($invoice['client_phone']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <td class="text-muted fw-bold">Invoice Date:</td>
                                            <td class="text-end">
                                                <?php echo date('F d, Y', strtotime($invoice['invoice_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-bold">Due Date:</td>
                                            <td
                                                class="text-end <?php echo $computed_status === 'overdue' ? 'text-danger fw-bold' : ''; ?>">
                                                <?php echo date('F d, Y', strtotime($invoice['due_date'])); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-bold">Property:</td>
                                            <td class="text-end">
                                                <?php echo htmlspecialchars($invoice['property_name']); ?></td>
                                        </tr>
                                        <?php if ($invoice['schedule_id']): ?>
                                            <tr>
                                                <td class="text-muted fw-bold">Payment Schedule:</td>
                                                <td class="text-end">Schedule <?php echo $invoice['schedule_number']; ?> of
                                                    <?php echo $invoice['term_months']; ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>

                            <!-- Invoice Items -->
                            <div class="mb-4">
                                <table class="table invoice-table">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Unit Price</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <strong><?php echo $invoice['schedule_id'] ? 'Payment Installment' : 'Property Payment'; ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($invoice['property_name']); ?>
                                                    <?php if ($invoice['schedule_due_date']): ?>
                                                        <br>Due Date:
                                                        <?php echo date('M d, Y', strtotime($invoice['schedule_due_date'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td class="text-center">1</td>
                                            <td class="text-end">
                                                ₱<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                            <td class="text-end">
                                                ₱<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="total-row">
                                            <td colspan="3" class="text-end">TOTAL AMOUNT DUE:</td>
                                            <td class="text-end">
                                                ₱<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Property Balance Summary -->
                            <div class="mb-4">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><strong>💰 Overall Property Balance</strong></h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td class="text-muted">🏠 Property:</td>
                                                <td class="text-end"><strong><?php echo htmlspecialchars($invoice['property_name']); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Total Contract Price:</td>
                                                <td class="text-end">₱<?php echo number_format($invoice['property_total'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Total Amount Due (incl. interest & penalties):</td>
                                                <td class="text-end">₱<?php echo number_format($property_balance['total_due'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted text-success"><strong>Total Amount Paid:</strong></td>
                                                <td class="text-end text-success"><strong>₱<?php echo number_format($property_balance['total_paid'], 2); ?></strong></td>
                                            </tr>
                                            <tr class="table-warning">
                                                <td class="fw-bold" style="font-size: 1.1rem;">📊 REMAINING BALANCE:</td>
                                                <td class="text-end fw-bold" style="font-size: 1.3rem; color: #ff6b35;">
                                                    ₱<?php echo number_format($property_balance['remaining_balance'], 2); ?>
                                                </td>
                                            </tr>
                                            <?php if ($property_balance['overdue_amount'] > 0): ?>
                                            <tr>
                                                <td class="text-danger">⚠️ Overdue Amount:</td>
                                                <td class="text-end text-danger fw-bold">₱<?php echo number_format($property_balance['overdue_amount'], 2); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment History -->
                            <?php if (count($payments) > 0 && !$print_mode): ?>
                                <div class="mb-4">
                                    <h6 class="fw-bold text-uppercase text-muted mb-3">Payment History</h6>
                                    <table class="table table-sm table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date Paid</th>
                                                <th>Receipt No.</th>
                                                <th class="text-end">Amount Paid</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($payment['date_paid'])); ?></td>
                                                    <td><?php echo htmlspecialchars($payment['receipt_no'] ?? 'N/A'); ?></td>
                                                    <td class="text-end text-success">
                                                        ₱<?php echo number_format($payment['amount_paid'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <!-- Notes -->
                            <?php if (!empty($invoice['notes'])): ?>
                                <div class="mb-4">
                                    <h6 class="fw-bold text-uppercase text-muted mb-2">Notes</h6>
                                    <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Footer -->
                            <div class="border-top pt-3 mt-5">
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            Generated on <?php echo date('F d, Y h:i A'); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <small class="text-muted">
                                            Thank you for your business!
                                        </small>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <?php if (!$print_mode): ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($print_mode): ?>
                <div class="text-center mt-3 mb-3 no-print">
                    <button onclick="window.print()" class="btn btn-primary btn-lg">
                        <span>🖨️</span> Print Invoice
                    </button>
                    <button onclick="window.close()" class="btn btn-outline-secondary btn-lg ms-2">
                        <span>✖</span> Close
                    </button>
                </div>

                <script>
                    // Auto-print on load if in print mode
                    window.addEventListener('load', function () {
                        // Uncomment to enable auto-print
                        // window.print();
                    });
                </script>

            </body>

            </html>
        <?php else: ?>
        <?php endif; ?>

        <?php if (!$print_mode): ?>
        </div> <!-- Close main-content -->
        <?php include '../templates/footer.php'; ?>
    <?php endif; ?>