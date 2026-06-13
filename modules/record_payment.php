<?php
/**
 * Record Payment Page
 * Real Estate Receivable System
 * 
 * Records payment for a payment schedule and updates status
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

// Require module access (finance or admin)
require_module_access('payments');

// Get schedule ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid payment schedule ID.');
    header('Location: payments.php');
    exit();
}

$schedule_id = (int) $_GET['id'];
$errors = [];

// Fetch payment schedule details with property and client info
try {
    $stmt = $pdo->prepare("
        SELECT 
            ps.*,
            p.property_name,
            p.client_id,
            c.name as client_name,
            c.email as client_email,
            c.contact_no as client_contact,
            COALESCE(SUM(pay.amount_paid), 0) as total_paid,
            (ps.amount_due - COALESCE(SUM(pay.amount_paid), 0)) as remaining_balance
        FROM payment_schedules ps
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        LEFT JOIN payments pay ON ps.schedule_id = pay.schedule_id
        WHERE ps.schedule_id = ?
        GROUP BY ps.schedule_id
    ");
    $stmt->execute([$schedule_id]);
    $schedule = $stmt->fetch();

    if (!$schedule) {
        set_flash_message('error', 'Payment schedule not found.');
        header('Location: payments.php');
        exit();
    }

    // Check if already fully paid
    if ($schedule['remaining_balance'] <= 0 && $schedule['status'] === 'paid') {
        set_flash_message('info', 'This payment schedule is already fully paid.');
        header('Location: payments.php');
        exit();
    }

    // Auto-calculate penalty if overdue
    $penalty_to_apply = ($schedule['penalty_amount'] ?? 0);

    if ($schedule['status'] === 'overdue') {
        $days_overdue = (new DateTime())->diff(new DateTime($schedule['due_date']))->days;
        $calculated_penalty = calculate_penalty($schedule['amount_due'], $days_overdue);

        if ($calculated_penalty > $penalty_to_apply) {
            $penalty_to_apply = $calculated_penalty;
            // Update schedule penalty for DB update later
            $schedule['penalty_amount'] = $penalty_to_apply;
        }
    }

    // Recalculate remaining balance completely to ensure accuracy
    // Formula: Amount Due + Penalty - Total Paid
    $schedule['remaining_balance'] = ($schedule['amount_due'] + $penalty_to_apply) - $schedule['total_paid'];


    // Fetch existing payments for this schedule
    $payments_stmt = $pdo->prepare("
        SELECT * FROM payments 
        WHERE schedule_id = ? 
        ORDER BY date_paid DESC, created_at DESC
    ");
    $payments_stmt->execute([$schedule_id]);
    $existing_payments = $payments_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Fetch schedule error: " . $e->getMessage());
    set_flash_message('error', 'Failed to load payment schedule.');
    header('Location: payments.php');
    exit();
}

// Process payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token. Please try again.');
        header('Location: record_payment.php?id=' . $schedule_id);
        exit();
    }

    // Get and sanitize input
    $amount_paid = sanitize_input($_POST['amount_paid'] ?? '');
    $date_paid = sanitize_input($_POST['date_paid'] ?? '');
    $receipt_no = sanitize_input($_POST['receipt_no'] ?? '');

    // Basic validation (non-DB dependent)
    if (empty($amount_paid)) {
        $errors['amount_paid'] = 'Payment amount is required.';
    } elseif (!is_numeric($amount_paid)) {
        $errors['amount_paid'] = 'Payment amount must be a valid number.';
    } elseif ($amount_paid <= 0) {
        $errors['amount_paid'] = 'Payment amount must be greater than zero.';
    }

    if (empty($date_paid)) {
        $errors['date_paid'] = 'Payment date is required.';
    } else {
        $date_obj = DateTime::createFromFormat('Y-m-d', $date_paid);
        if (!$date_obj) {
            $errors['date_paid'] = 'Invalid date format.';
        }
    }

    if (!empty($receipt_no) && strlen($receipt_no) > 50) {
        $errors['receipt_no'] = 'Receipt number cannot exceed 50 characters.';
    }

    // If basic validation passes, proceed with locked transaction
    if (empty($errors)) {
        try {
            // START TRANSACTION EARLY - Before balance-dependent validation
            $pdo->beginTransaction();

            // LOCK the schedule row to prevent concurrent modifications
            $lock_stmt = $pdo->prepare("
                SELECT 
                    ps.schedule_id,
                    ps.amount_due,
                    COALESCE(ps.penalty_amount, 0) as penalty_amount,
                    ps.status,
                    COALESCE(SUM(pay.amount_paid), 0) as total_paid
                FROM payment_schedules ps
                LEFT JOIN payments pay ON ps.schedule_id = pay.schedule_id
                WHERE ps.schedule_id = ?
                GROUP BY ps.schedule_id
                FOR UPDATE
            ");
            $lock_stmt->execute([$schedule_id]);
            $locked_schedule = $lock_stmt->fetch();

            if (!$locked_schedule) {
                $pdo->rollBack();
                $errors['general'] = 'Payment schedule not found.';
            } else {
                // Recalculate balance with locked, fresh data
                $fresh_remaining = ($locked_schedule['amount_due'] + $locked_schedule['penalty_amount']) - $locked_schedule['total_paid'];

                // Validate amount against FRESH balance
                if ($amount_paid > $fresh_remaining) {
                    $pdo->rollBack();
                    $errors['amount_paid'] = "Payment amount exceeds current remaining balance (₱" . number_format($fresh_remaining, 2) . "). Please refresh and try again.";
                } elseif ($locked_schedule['status'] === 'paid' && $fresh_remaining <= 0) {
                    $pdo->rollBack();
                    $errors['general'] = 'This schedule is already fully paid.';
                } else {
                    // Check receipt uniqueness inside transaction
                    if (!empty($receipt_no)) {
                        $receipt_check = $pdo->prepare("SELECT payment_id FROM payments WHERE receipt_no = ?");
                        $receipt_check->execute([$receipt_no]);
                        if ($receipt_check->fetch()) {
                            $pdo->rollBack();
                            $errors['receipt_no'] = 'This receipt number already exists.';
                        }
                    }

                    // Final insert if no errors
                    if (empty($errors)) {
                        // Insert payment record
                        $stmt = $pdo->prepare("
                            INSERT INTO payments (schedule_id, amount_paid, date_paid, receipt_no)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $schedule_id,
                            $amount_paid,
                            $date_paid,
                            !empty($receipt_no) ? $receipt_no : null
                        ]);

                        // Update penalty amount in DB if applicable
                        if (isset($schedule['penalty_amount']) && $schedule['penalty_amount'] > 0) {
                            $update_penalty = $pdo->prepare("UPDATE payment_schedules SET penalty_amount = ? WHERE schedule_id = ?");
                            $update_penalty->execute([$schedule['penalty_amount'], $schedule_id]);
                        }

                        $payment_id = $pdo->lastInsertId();

                        // Recalculate remaining balance for display
                        $new_total_paid = $locked_schedule['total_paid'] + $amount_paid;
                        $new_remaining_balance = $fresh_remaining - $amount_paid;

                        // Note: Status updates are handled automatically by database triggers

                        // Commit transaction
                        $pdo->commit();

                        // Log the payment to audit trail
                        log_audit(
                            $pdo,
                            'RECORD_PAYMENT',
                            'payment_id:' . $payment_id,
                            'Recorded payment of ₱' . number_format($amount_paid, 2) . ' for schedule #' . $schedule_id
                        );

                        set_flash_message('success', "Payment of ₱" . number_format($amount_paid, 2) . " recorded successfully! " .
                            ($new_remaining_balance <= 0 ? "Schedule marked as PAID." : "Remaining balance: ₱" . number_format($new_remaining_balance, 2)));
                        header('Location: record_payment.php?id=' . $schedule_id);
                        exit();
                    }
                }
            }

        } catch (PDOException $e) {
            // Rollback on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Record payment error: " . $e->getMessage());

            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'receipt_no') !== false) {
                $errors['receipt_no'] = 'This receipt number already exists.';
            } else {
                $errors['general'] = 'Failed to record payment. Please try again.';
            }
        } catch (Exception $e) {
            // Rollback on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Record payment error: " . $e->getMessage());
            $errors['general'] = 'An error occurred while recording payment.';
        }
    }

    // Refresh data after submission
    if (empty($errors)) {
        // Reload schedule data
        $stmt = $pdo->prepare("
            SELECT 
                ps.*,
                p.property_name,
                p.client_id,
                c.name as client_name,
                c.email as client_email,
                c.contact_no as client_contact,
                COALESCE(SUM(pay.amount_paid), 0) as total_paid,
                (ps.amount_due - COALESCE(SUM(pay.amount_paid), 0)) as remaining_balance
            FROM payment_schedules ps
            INNER JOIN properties p ON ps.property_id = p.property_id
            LEFT JOIN clients c ON p.client_id = c.client_id
            LEFT JOIN payments pay ON ps.schedule_id = pay.schedule_id
            WHERE ps.schedule_id = ?
            GROUP BY ps.schedule_id
        ");
        $stmt->execute([$schedule_id]);
        $schedule = $stmt->fetch();

        // Reload existing payments
        $payments_stmt = $pdo->prepare("
            SELECT * FROM payments 
            WHERE schedule_id = ? 
            ORDER BY date_paid DESC, created_at DESC
        ");
        $payments_stmt->execute([$schedule_id]);
        $existing_payments = $payments_stmt->fetchAll();
    }
}

// Set page title
$page_title = 'Record Payment - ' . $schedule['property_name'];

// Include header
include '../templates/header.php';
?>

<!-- Include Navigation -->
<?php include '../templates/sidebar.php'; ?>

<!-- Main Content Wrapper -->
<div class="main-wrapper">
    <div class="main-content">
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="payments.php">Payments</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Record Payment</li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <span style="color: var(--primary-orange);">💳</span> Record Payment
                        </h2>
                        <p class="text-muted mb-0">Record payment for payment schedule</p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <a href="payments.php" class="btn btn-outline-secondary">
                            <span>◀</span> Back to Payments
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo htmlspecialchars($errors['general']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php
            $flash = get_flash_message();
            if ($flash):
                $alert_class = 'alert-info';
                if ($flash['type'] === 'success')
                    $alert_class = 'alert-success';
                if ($flash['type'] === 'error')
                    $alert_class = 'alert-danger';
                if ($flash['type'] === 'warning')
                    $alert_class = 'alert-warning';
                ?>
                <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
                    <strong><?php echo ucfirst($flash['type']); ?>!</strong>
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Payment Schedule Details -->
                <div class="col-lg-5 mb-4">
                    <!-- Schedule Information -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <span>📅</span> Schedule Information
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6 class="text-muted mb-1">Property</h6>
                                <p class="mb-0">
                                    <strong><?php echo htmlspecialchars($schedule['property_name']); ?></strong>
                                </p>
                            </div>
                            <div class="mb-3">
                                <h6 class="text-muted mb-1">Client</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($schedule['client_name']); ?></p>
                                <?php if (!empty($schedule['client_contact'])): ?>
                                    <small class="text-muted">📞
                                        <?php echo htmlspecialchars($schedule['client_contact']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <h6 class="text-muted mb-1">Due Date</h6>
                                <p class="mb-0">
                                    <strong><?php echo date('F d, Y', strtotime($schedule['due_date'])); ?></strong>
                                    <?php
                                    $due_date = new DateTime($schedule['due_date']);
                                    $today = new DateTime();
                                    $diff = $today->diff($due_date);
                                    if ($schedule['status'] === 'overdue'): ?>
                                        <span class="badge bg-danger">Overdue by <?php echo $diff->days; ?> days</span>
                                    <?php elseif ($diff->days <= 7 && $due_date > $today): ?>
                                        <span class="badge bg-warning">Due in <?php echo $diff->days; ?> days</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="mb-2">
                                <h6 class="text-muted mb-1">Status</h6>
                                <p class="mb-0">
                                    <?php
                                    $status_badge = 'secondary';
                                    $status_icon = '⚪';
                                    if ($schedule['status'] === 'paid') {
                                        $status_badge = 'success';
                                        $status_icon = '🟢';
                                    } elseif ($schedule['status'] === 'overdue') {
                                        $status_badge = 'danger';
                                        $status_icon = '🔴';
                                    } elseif ($schedule['status'] === 'pending') {
                                        $status_badge = 'warning';
                                        $status_icon = '🟡';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $status_badge; ?> fs-6">
                                        <?php echo $status_icon; ?> <?php echo ucfirst($schedule['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Summary -->
                    <div class="card">
                        <div class="card-header">
                            <span>💰</span> Payment Summary
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="text-muted mb-0">Amount Due</h6>
                                    <h4 class="mb-0">₱<?php echo number_format($schedule['amount_due'], 2); ?></h4>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="text-muted mb-0">Total Paid</h6>
                                    <h5 class="mb-0 text-success">
                                        ₱<?php echo number_format($schedule['total_paid'], 2); ?>
                                    </h5>
                                </div>
                                <small class="text-muted"><?php echo count($existing_payments); ?> payment(s)
                                    recorded</small>
                            </div>
                            <hr>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="text-muted mb-0">Remaining Balance</h6>
                                    <h4
                                        class="mb-0 <?php echo $schedule['remaining_balance'] <= 0 ? 'text-success' : 'text-danger'; ?>">
                                        ₱<?php echo number_format($schedule['remaining_balance'], 2); ?>
                                    </h4>
                                </div>
                            </div>

                            <?php if ($schedule['remaining_balance'] > 0): ?>
                                <div class="progress mt-3" style="height: 25px;">
                                    <?php
                                    $payment_percent = ($schedule['total_paid'] / $schedule['amount_due']) * 100;
                                    ?>
                                    <div class="progress-bar bg-success" role="progressbar"
                                        style="width: <?php echo $payment_percent; ?>%;"
                                        aria-valuenow="<?php echo $payment_percent; ?>" aria-valuemin="0"
                                        aria-valuemax="100">
                                        <?php echo number_format($payment_percent, 1); ?>%
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mt-3 mb-0">
                                    <strong>✓ Fully Paid!</strong> This schedule is complete.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Form and History -->
                <div class="col-lg-7">
                    <!-- Record Payment Form -->
                    <?php if ($schedule['remaining_balance'] > 0): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <span>➕</span> Record New Payment
                            </div>
                            <div class="card-body">
                                <form method="POST" action="record_payment.php?id=<?php echo $schedule_id; ?>" novalidate>
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                                    <!-- Amount Paid -->
                                    <div class="mb-3">
                                        <label for="amount_paid" class="form-label">
                                            Payment Amount <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="number"
                                                class="form-control <?php echo isset($errors['amount_paid']) ? 'is-invalid' : ''; ?>"
                                                id="amount_paid" name="amount_paid"
                                                value="<?php echo isset($_POST['amount_paid']) ? htmlspecialchars($_POST['amount_paid']) : $schedule['remaining_balance']; ?>"
                                                step="0.01" min="0.01" max="<?php echo $schedule['remaining_balance']; ?>"
                                                required autofocus>
                                            <?php if (isset($errors['amount_paid'])): ?>
                                                <div class="invalid-feedback">
                                                    <?php echo htmlspecialchars($errors['amount_paid']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <small class="form-text text-muted">
                                            Maximum: ₱<?php echo number_format($schedule['remaining_balance'], 2); ?>
                                            (remaining
                                            balance)
                                        </small>
                                    </div>

                                    <!-- Date Paid -->
                                    <div class="mb-3">
                                        <label for="date_paid" class="form-label">
                                            Payment Date <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">📅</span>
                                            <input type="date"
                                                class="form-control <?php echo isset($errors['date_paid']) ? 'is-invalid' : ''; ?>"
                                                id="date_paid" name="date_paid"
                                                value="<?php echo isset($_POST['date_paid']) ? htmlspecialchars($_POST['date_paid']) : date('Y-m-d'); ?>"
                                                max="<?php echo date('Y-m-d'); ?>" required>
                                            <?php if (isset($errors['date_paid'])): ?>
                                                <div class="invalid-feedback">
                                                    <?php echo htmlspecialchars($errors['date_paid']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <small class="form-text text-muted">Date when payment was received</small>
                                    </div>

                                    <!-- Receipt Number -->
                                    <div class="mb-3">
                                        <label for="receipt_no" class="form-label">
                                            Receipt Number <span class="text-muted">(Optional)</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">🧾</span>
                                            <input type="text"
                                                class="form-control <?php echo isset($errors['receipt_no']) ? 'is-invalid' : ''; ?>"
                                                id="receipt_no" name="receipt_no"
                                                value="<?php echo isset($_POST['receipt_no']) ? htmlspecialchars($_POST['receipt_no']) : ''; ?>"
                                                placeholder="OR-2025-001" maxlength="50">
                                            <?php if (isset($errors['receipt_no'])): ?>
                                                <div class="invalid-feedback">
                                                    <?php echo htmlspecialchars($errors['receipt_no']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <small class="form-text text-muted">Official receipt or reference number (max 50
                                            characters)</small>
                                    </div>

                                    <hr class="my-4">

                                    <!-- Form Actions -->
                                    <div class="d-flex justify-content-between">
                                        <a href="payments.php" class="btn btn-outline-secondary">
                                            ✖ Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            ✓ Record Payment
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Payment History -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><span>📜</span> Payment History</span>
                            <span class="badge bg-light text-dark"><?php echo count($existing_payments); ?>
                                payment(s)</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($existing_payments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50px;">ID</th>
                                                <th>Receipt No</th>
                                                <th>Date Paid</th>
                                                <th class="text-end">Amount Paid</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($existing_payments as $payment): ?>
                                                <tr>
                                                    <td><strong><?php echo $payment['payment_id']; ?></strong></td>
                                                    <td>
                                                        <?php if (!empty($payment['receipt_no'])): ?>
                                                            <span
                                                                class="badge bg-secondary"><?php echo htmlspecialchars($payment['receipt_no']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($payment['date_paid'])); ?></td>
                                                    <td class="text-end">
                                                        <strong
                                                            class="text-success">₱<?php echo number_format($payment['amount_paid'], 2); ?></strong>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="3">Total Paid</th>
                                                <th class="text-end">
                                                    <strong
                                                        class="text-success">₱<?php echo number_format($schedule['total_paid'], 2); ?></strong>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state" style="padding: 2rem 1rem;">
                                    <div class="empty-icon" style="font-size: 3rem;">📜</div>
                                    <p class="text-muted mb-0">No payments recorded yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include '../templates/footer.php'; ?>

    <!-- Real-time Balance AJAX -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const amountInput = document.getElementById('amount_paid');
            if (!amountInput) return;

            const scheduleId = <?php echo $schedule_id; ?>;
            const remainingBalance = <?php echo $schedule['remaining_balance']; ?>;

            // Create balance preview element
            const previewDiv = document.createElement('div');
            previewDiv.id = 'balancePreview';
            previewDiv.className = 'alert alert-info mt-3';
            previewDiv.style.display = 'none';
            previewDiv.innerHTML = '<strong>📊 Projected Balance:</strong> <span id="projectedBalance">-</span>';

            // Insert after amount input group
            const amountParent = amountInput.closest('.mb-3');
            if (amountParent) {
                amountParent.appendChild(previewDiv);
            }

            let debounceTimer;

            amountInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                const amount = parseFloat(this.value) || 0;

                if (amount <= 0) {
                    previewDiv.style.display = 'none';
                    return;
                }

                debounceTimer = setTimeout(function () {
                    // Calculate locally for instant feedback
                    const projected = remainingBalance - amount;
                    const willComplete = projected <= 0;

                    previewDiv.style.display = 'block';

                    if (willComplete) {
                        previewDiv.className = 'alert alert-success mt-3';
                        document.getElementById('projectedBalance').innerHTML =
                            '<strong class="text-success">₱0.00</strong> - 🎉 This will complete the payment!';
                    } else if (amount > remainingBalance) {
                        previewDiv.className = 'alert alert-danger mt-3';
                        document.getElementById('projectedBalance').innerHTML =
                            '<strong class="text-danger">Amount exceeds balance!</strong> Max: ₱' +
                            remainingBalance.toLocaleString('en-PH', { minimumFractionDigits: 2 });
                    } else {
                        previewDiv.className = 'alert alert-info mt-3';
                        document.getElementById('projectedBalance').innerHTML =
                            '<strong>₱' + projected.toLocaleString('en-PH', { minimumFractionDigits: 2 }) + '</strong> remaining after this payment';
                    }

                    // Verify with API for accuracy
                    fetch('../api/calculate_balance.php?schedule_id=' + scheduleId + '&amount=' + amount)
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) return;

                            if (data.will_complete) {
                                previewDiv.className = 'alert alert-success mt-3';
                                document.getElementById('projectedBalance').innerHTML =
                                    '<strong class="text-success">' + data.formatted.projected_balance + '</strong> - 🎉 This will complete the payment!';
                            } else {
                                document.getElementById('projectedBalance').innerHTML =
                                    '<strong>' + data.formatted.projected_balance + '</strong> remaining after this payment';
                            }

                            if (data.penalty_amount > 0 && data.is_overdue) {
                                document.getElementById('projectedBalance').innerHTML +=
                                    '<br><small class="text-warning">⚠️ Suggested penalty for ' + data.days_overdue + ' days late: ' + data.formatted.penalty + '</small>';
                            }
                        })
                        .catch(err => console.log('Balance API not available, using local calculation'));

                }, 300);
            });

            // Trigger initial calculation if value exists
            if (amountInput.value) {
                amountInput.dispatchEvent(new Event('input'));
            }
        });
    </script>