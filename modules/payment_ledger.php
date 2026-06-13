<?php
/**
 * Payment Ledger/History View
 * Real Estate Receivable System - Phase 4
 * 
 * Full payment history with advanced filtering and export capabilities
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

require_login();

$page_title = 'Payment Ledger';

// Filters
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$property_id = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$export = isset($_GET['export']) ? $_GET['export'] : '';

$has_filters = $client_id > 0 || $property_id > 0 || !empty($date_from) || !empty($date_to) || !empty($search);

try {
    // Get all clients for filter dropdown
    $clients_stmt = $pdo->query("SELECT client_id, name FROM clients ORDER BY name ASC");
    $clients = $clients_stmt->fetchAll();
    
    // Get all properties for filter dropdown
    $properties_stmt = $pdo->query("
        SELECT p.property_id, p.property_name, c.name as client_name 
        FROM properties p 
        LEFT JOIN clients c ON p.client_id = c.client_id 
        ORDER BY p.property_name ASC
    ");
    $all_properties = $properties_stmt->fetchAll();
    
    // Build dynamic query for payments
    $where_clauses = ["1=1"];
    $params = [];
    
    if ($client_id > 0) {
        $where_clauses[] = "p.client_id = ?";
        $params[] = $client_id;
    }
    
    if ($property_id > 0) {
        $where_clauses[] = "p.property_id = ?";
        $params[] = $property_id;
    }
    
    if (!empty($date_from)) {
        $where_clauses[] = "pay.date_paid >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_clauses[] = "pay.date_paid <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(pay.receipt_no LIKE ? OR c.name LIKE ? OR p.property_name LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Get ledger summary
    $summary_stmt = $pdo->prepare("
        SELECT 
            COUNT(pay.payment_id) as total_payments,
            COALESCE(SUM(pay.amount_paid), 0) as total_amount,
            COUNT(DISTINCT c.client_id) as unique_clients,
            COUNT(DISTINCT p.property_id) as unique_properties
        FROM payments pay
        INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        WHERE {$where_sql}
    ");
    $summary_stmt->execute($params);
    $summary = $summary_stmt->fetch();
    
    // Get payment records with pagination
    $records_per_page = 25;
    $current_page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $offset = ($current_page - 1) * $records_per_page;
    
    // Count total records
    $count_stmt = $pdo->prepare("
        SELECT COUNT(pay.payment_id) as total
        FROM payments pay
        INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        WHERE {$where_sql}
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Fetch payment records
    $payments_sql = "
        SELECT 
            pay.payment_id,
            pay.amount_paid,
            pay.date_paid,
            pay.receipt_no,
            pay.created_at,
            ps.schedule_id,
            ps.schedule_number,
            ps.due_date,
            ps.amount_due,
            ps.principal_amount,
            ps.interest_amount,
            p.property_id,
            p.property_name,
            c.client_id,
            c.name as client_name
        FROM payments pay
        INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        WHERE {$where_sql}
        ORDER BY pay.date_paid DESC, pay.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $payments_params = array_merge($params, [$records_per_page, $offset]);
    $payments_stmt = $pdo->prepare($payments_sql);
    $payments_stmt->execute($payments_params);
    $payments = $payments_stmt->fetchAll();
    
    // Calculate running balance for each payment
    $running_balance = 0;
    foreach ($payments as &$payment) {
        // Add the payment amount to running total (cumulative payments)
        $running_balance += $payment['amount_paid'];
        $payment['running_balance'] = $running_balance;
    }
    unset($payment); // Break reference
    
    // Handle CSV Export
    if ($export === 'csv') {
        // Get all records without pagination for export
        $export_stmt = $pdo->prepare("
            SELECT 
                pay.payment_id,
                pay.amount_paid,
                pay.date_paid,
                pay.receipt_no,
                ps.schedule_number,
                ps.due_date,
                ps.amount_due,
                p.property_name,
                c.name as client_name
            FROM payments pay
            INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
            INNER JOIN properties p ON ps.property_id = p.property_id
            LEFT JOIN clients c ON p.client_id = c.client_id
            WHERE {$where_sql}
            ORDER BY pay.date_paid DESC
        ");
        $export_stmt->execute($params);
        $export_data = $export_stmt->fetchAll();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="payment_ledger_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Payment ID', 'Date Paid', 'Client', 'Property', 'Schedule #', 'Due Date', 'Amount Due', 'Amount Paid', 'Receipt No']);
        
        foreach ($export_data as $row) {
            fputcsv($output, [
                $row['payment_id'],
                $row['date_paid'],
                $row['client_name'],
                $row['property_name'],
                $row['schedule_number'],
                $row['due_date'],
                $row['amount_due'],
                $row['amount_paid'],
                $row['receipt_no'] ?? ''
            ]);
        }
        
        fclose($output);
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Payment ledger error: " . $e->getMessage());
    $error_message = "Database error occurred.";
    $clients = [];
    $all_properties = [];
    $payments = [];
    $summary = ['total_payments' => 0, 'total_amount' => 0, 'unique_clients' => 0, 'unique_properties' => 0];
}

include '../templates/header.php';
?>

<!-- Include Sidebar Navigation -->
<?php include '../templates/sidebar.php'; ?>

<!-- Main Content Wrapper -->
<div class="main-wrapper">
    <div class="main-content">
        <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2>
                        <span style="color: var(--primary-maroon);">📒</span> Payment Ledger
                    </h2>
                    <p class="text-muted mb-0">Complete payment history and records</p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                       class="btn btn-success">
                        📥 Export CSV
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card border-primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo number_format($summary['total_payments']); ?></h3>
                            <p>Total Payments</p>
                        </div>
                        <div style="font-size: 2.5rem; opacity: 0.3;">💳</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card border-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo format_peso($summary['total_amount']); ?></h3>
                            <p>Total Amount</p>
                        </div>
                        <div style="font-size: 2.5rem; opacity: 0.3;">💰</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card border-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo number_format($summary['unique_clients']); ?></h3>
                            <p>Unique Clients</p>
                        </div>
                        <div style="font-size: 2.5rem; opacity: 0.3;">👥</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card border-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo number_format($summary['unique_properties']); ?></h3>
                            <p>Properties</p>
                        </div>
                        <div style="font-size: 2.5rem; opacity: 0.3;">🏠</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Card -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <span>🔍</span> Filter Payments
            </div>
            <div class="card-body">
                <form method="GET" action="payment_ledger.php">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Client</label>
                            <select name="client_id" class="form-select">
                                <option value="">All Clients</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['client_id']; ?>"
                                        <?php echo ($client_id == $client['client_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Property</label>
                            <select name="property_id" class="form-select">
                                <option value="">All Properties</option>
                                <?php foreach ($all_properties as $prop): ?>
                                <option value="<?php echo $prop['property_id']; ?>"
                                        <?php echo ($property_id == $prop['property_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prop['property_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Client / Property / Receipt</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search by client, property, or receipt..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                            <?php if ($has_filters): ?>
                            <a href="payment_ledger.php" class="btn btn-outline-secondary">✖ Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Payment Records Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>📋 Payment Records</span>
                <span class="badge bg-light text-dark"><?php echo number_format($total_records); ?> records</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($payments) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 100px;">Date</th>
                                <th>Client</th>
                                <th>Property</th>
                                <th class="text-center">Schedule</th>
                                <th class="text-end">Amount Due</th>
                                <th class="text-end">Amount Paid</th>
                                <th class="text-end">Running Balance</th>
                                <th>Receipt #</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $pay): ?>
                            <tr>
                                <td><strong class="text-muted">#<?php echo $pay['payment_id']; ?></strong></td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($pay['date_paid'])); ?></small>
                                </td>
                                <td>
                                    <a href="client_dashboard.php?id=<?php echo $pay['client_id']; ?>" 
                                       class="text-decoration-none">
                                        <?php echo htmlspecialchars($pay['client_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="property_edit.php?id=<?php echo $pay['property_id']; ?>"
                                       class="text-decoration-none">
                                        <?php echo htmlspecialchars($pay['property_name']); ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <?php if ($pay['schedule_number'] == 0): ?>
                                        <span class="badge bg-info text-dark">🔐 Security Deposit</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">#<?php echo $pay['schedule_number']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <small><?php echo format_peso($pay['amount_due']); ?></small>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success"><?php echo format_peso($pay['amount_paid']); ?></strong>
                                </td>
                                <td class="text-end">
                                    <strong class="text-info"><?php echo format_peso($pay['running_balance']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($pay['receipt_no']): ?>
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($pay['receipt_no']); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-light">
                    <nav>
                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>">
                                    &laquo; Previous
                                </a>
                            </li>
                            
                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>">
                                    Next &raquo;
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-state" style="padding: 3rem 1rem;">
                    <div class="empty-icon" style="font-size: 4rem;">📒</div>
                    <h5 class="text-muted">No payment records found</h5>
                    <p class="text-muted">
                        <?php echo $has_filters ? 'Try adjusting your filters.' : 'No payments have been recorded yet.'; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<?php include '../templates/footer.php'; ?>
