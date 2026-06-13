<?php
/**
 * Payments Management - Enhanced with Advanced Search & Client-First Flow
 * Real Estate Receivable System
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

require_login();

$page_title = 'Payment Management';

// Advanced Search Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$property_id = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$receipt_no = isset($_GET['receipt_no']) ? trim($_GET['receipt_no']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$has_filters = !empty($search) || $client_id > 0 || $property_id > 0 || 
               !empty($date_from) || !empty($date_to) || !empty($receipt_no) || !empty($status_filter);

try {
    // Get all clients for dropdown
    $clients_stmt = $pdo->query("SELECT client_id, name FROM clients ORDER BY name ASC");
    $clients = $clients_stmt->fetchAll();
    
    // Get dashboard statistics
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN ps.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN ps.status = 'overdue' THEN 1 END) as overdue_count,
            COUNT(CASE WHEN ps.status = 'paid' THEN 1 END) as paid_count,
            COALESCE(SUM(CASE WHEN ps.status = 'pending' THEN ps.amount_due ELSE 0 END), 0) as pending_amount,
            COALESCE(SUM(CASE WHEN ps.status = 'overdue' THEN ps.amount_due ELSE 0 END), 0) as overdue_amount,
            COALESCE(SUM(CASE WHEN ps.status = 'paid' THEN ps.amount_due ELSE 0 END), 0) as paid_amount
        FROM payment_schedules ps
    ");
    $stats = $stats_stmt->fetch();
    
    // Build dynamic query for properties with schedules
    $where_clauses = ["1=1"];
    $search_params = [];
    
    if (!empty($search)) {
        $where_clauses[] = "(p.property_name LIKE ? OR c.name LIKE ?)";
        $search_param = "%{$search}%";
        $search_params[] = $search_param;
        $search_params[] = $search_param;
    }
    
    if ($client_id > 0) {
        $where_clauses[] = "p.client_id = ?";
        $search_params[] = $client_id;
    }
    
    if ($property_id > 0) {
        $where_clauses[] = "p.property_id = ?";
        $search_params[] = $property_id;
    }
    
    if (!empty($status_filter) && in_array($status_filter, ['pending', 'overdue', 'paid'])) {
        $where_clauses[] = "ps.status = ?";
        $search_params[] = $status_filter;
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    $properties_stmt = $pdo->prepare("
        SELECT 
            p.property_id,
            p.property_name,
            p.client_id,
            p.term_months,
            p.interest_rate,
            c.name as client_name,
            COUNT(ps.schedule_id) as total_schedules,
            SUM(CASE WHEN ps.status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
            SUM(CASE WHEN ps.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN ps.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            COALESCE(SUM(ps.amount_due), 0) as total_amount,
            COALESCE(SUM(CASE WHEN ps.status = 'overdue' THEN ps.amount_due ELSE 0 END), 0) as overdue_amount,
            COALESCE(SUM(CASE WHEN ps.status = 'pending' THEN ps.amount_due ELSE 0 END), 0) as pending_amount,
            COALESCE(SUM(CASE WHEN ps.status = 'paid' THEN ps.amount_due ELSE 0 END), 0) as paid_amount
        FROM properties p
        LEFT JOIN clients c ON p.client_id = c.client_id
        LEFT JOIN payment_schedules ps ON p.property_id = ps.property_id
        WHERE {$where_sql}
        GROUP BY p.property_id
        HAVING COUNT(ps.schedule_id) > 0
        ORDER BY 
            SUM(CASE WHEN ps.status = 'overdue' THEN 1 ELSE 0 END) DESC,
            p.property_name ASC
    ");
    $properties_stmt->execute($search_params);
    $properties = $properties_stmt->fetchAll();
    
    // Batch fetch all schedules
    $property_ids = array_column($properties, 'property_id');
    $schedules_by_property = [];
    
    if (!empty($property_ids)) {
        $placeholders = str_repeat('?,', count($property_ids) - 1) . '?';
        
        $schedule_params = $property_ids;
        $sched_where = "";
        
        // Apply date filters to schedules
        if (!empty($date_from)) {
            $sched_where .= " AND ps.due_date >= ?";
            $schedule_params[] = $date_from;
        }
        if (!empty($date_to)) {
            $sched_where .= " AND ps.due_date <= ?";
            $schedule_params[] = $date_to;
        }
        
        $all_schedules_stmt = $pdo->prepare("
            SELECT 
                ps.*,
                COALESCE(SUM(pay.amount_paid), 0) as total_paid,
                (ps.amount_due + COALESCE(ps.penalty_amount, 0) - COALESCE(SUM(pay.amount_paid), 0)) as remaining_balance,
                DATEDIFF(CURDATE(), ps.due_date) as days_overdue,
                DATEDIFF(ps.due_date, CURDATE()) as days_until_due
            FROM payment_schedules ps
            LEFT JOIN payments pay ON ps.schedule_id = pay.schedule_id
            INNER JOIN (
                SELECT property_id, MIN(due_date) as next_due_date
                FROM payment_schedules
                WHERE status IN ('pending', 'overdue')
                GROUP BY property_id
            ) next_sched ON ps.property_id = next_sched.property_id 
                         AND ps.due_date = next_sched.next_due_date
            WHERE ps.property_id IN ({$placeholders}) {$sched_where}
            GROUP BY ps.schedule_id
            ORDER BY ps.property_id ASC, ps.due_date ASC
        ");
        $all_schedules_stmt->execute($schedule_params);
        $all_schedules = $all_schedules_stmt->fetchAll();
        
        foreach ($all_schedules as $schedule) {
            $schedules_by_property[$schedule['property_id']][] = $schedule;
        }
    }
    
    // Get recent payment history for quick reference
    $recent_payments_stmt = $pdo->query("
        SELECT 
            pay.payment_id,
            pay.amount_paid,
            pay.date_paid,
            pay.receipt_no,
            ps.schedule_number,
            p.property_name,
            c.name as client_name
        FROM payments pay
        INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        ORDER BY pay.date_paid DESC, pay.created_at DESC
        LIMIT 10
    ");
    $recent_payments = $recent_payments_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Payments listing error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again later.";
    $stats = ['pending_count' => 0, 'overdue_count' => 0, 'paid_count' => 0,
              'pending_amount' => 0, 'overdue_amount' => 0, 'paid_amount' => 0];
    $properties = [];
    $clients = [];
    $recent_payments = [];
}

include '../templates/header.php';
?>

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
                        <span style="color: var(--primary-maroon);">💳</span> Payment Management
                    </h2>
                    <p class="text-muted mb-0">Record payments, search history, and track balances</p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <button class="btn btn-info me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#paymentHistoryPanel">
                        📜 Recent Payments
                    </button>
                </div>
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php 
        $flash = get_flash_message();
        if ($flash):
            $alert_class = $flash['type'] === 'success' ? 'alert-success' : 
                          ($flash['type'] === 'error' ? 'alert-danger' : 'alert-info');
        ?>
        <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show">
            <strong><?php echo ucfirst($flash['type']); ?>!</strong> <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stats-card border-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo number_format($stats['pending_count']); ?></h3>
                            <p>Pending Schedules</p>
                            <small class="text-muted"><?php echo format_peso($stats['pending_amount']); ?></small>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">🟡</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card border-danger">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo number_format($stats['overdue_count']); ?></h3>
                            <p>Overdue Schedules</p>
                            <small class="text-muted"><?php echo format_peso($stats['overdue_amount']); ?></small>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">🔴</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card border-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo number_format($stats['paid_count']); ?></h3>
                            <p>Paid Schedules</p>
                            <small class="text-muted"><?php echo format_peso($stats['paid_amount']); ?></small>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">🟢</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Advanced Search Card -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <span><span>🔍</span> Advanced Search</span>
                    <button class="btn btn-sm btn-outline-secondary" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                        <?php echo $has_filters ? '▲ Hide Filters' : '▼ Show Filters'; ?>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="payments.php">
                    <!-- Quick Search Row -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text">🔍</span>
                                <input type="text" name="search" id="realTimeSearch" class="form-control form-control-lg" 
                                       placeholder="Search by client name, property, or receipt number..." 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       autocomplete="off">
                                <span class="input-group-text" id="searchStatus" style="display: none;">
                                    <div class="spinner-border spinner-border-sm" role="status"></div>
                                </span>
                            </div>
                            <div id="searchResults" class="mt-2" style="display: none;">
                                <!-- Real-time search results will appear here -->
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid d-md-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-fill">🔍 Search</button>
                                <?php if ($has_filters): ?>
                                <a href="payments.php" class="btn btn-outline-secondary flex-fill">✖ Clear</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Filters (collapsible) -->
                    <div class="collapse <?php echo $has_filters ? 'show' : ''; ?>" id="advancedFilters">
                        <hr>
                        <div class="row g-3">
                            <!-- Client Filter -->
                            <div class="col-md-3">
                                <label class="form-label">Client</label>
                                <select name="client_id" class="form-select" id="clientFilter">
                                    <option value="">All Clients</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['client_id']; ?>"
                                            <?php echo ($client_id == $client['client_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Status Filter -->
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>🔴 Overdue</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>🟡 Pending</option>
                                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>🟢 Paid</option>
                                </select>
                            </div>
                            
                            <!-- Date Range -->
                            <div class="col-md-2">
                                <label class="form-label">Due From</label>
                                <input type="date" name="date_from" class="form-control" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Due To</label>
                                <input type="date" name="date_to" class="form-control" 
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            
                            <!-- Receipt Search -->
                            <div class="col-md-3">
                                <label class="form-label">Receipt No.</label>
                                <input type="text" name="receipt_no" class="form-control" 
                                       placeholder="Search receipt..."
                                       value="<?php echo htmlspecialchars($receipt_no); ?>">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Client-First Payment Entry -->
        <div class="card mb-4 bg-light">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <h6 class="mb-2"><span>💳</span> Quick Payment Entry</h6>
                        <small class="text-muted">Select client to view their properties and record payment</small>
                    </div>
                    <div class="col-md-5">
                        <select class="form-select" id="quickClientSelect">
                            <option value="">-- Select Client First --</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['client_id']; ?>">
                                <?php echo htmlspecialchars($client['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="quickPropertySelect" disabled>
                            <option value="">-- Select Property --</option>
                        </select>
                    </div>
                </div>
                <div id="quickScheduleList" class="mt-3" style="display: none;">
                    <!-- Schedule list will be populated via AJAX -->
                </div>
            </div>
        </div>
        
        <!-- Properties with Payment Schedules -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">👥 Clients & Payment Schedules</h5>
                <span class="badge bg-light text-dark"><?php echo count($properties); ?> properties</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($properties) > 0): ?>
                <div class="accordion" id="propertiesAccordion">
                    <?php foreach ($properties as $idx => $property): 
                        $schedules = $schedules_by_property[$property['property_id']] ?? [];
                        
                        $badge_class = 'secondary';
                        $status_text = 'All Paid';
                        if ($property['overdue_count'] > 0) {
                            $badge_class = 'danger';
                            $status_text = $property['overdue_count'] . ' Overdue';
                        } elseif ($property['pending_count'] > 0) {
                            $badge_class = 'warning';
                            $status_text = $property['pending_count'] . ' Pending';
                        } elseif ($property['paid_count'] > 0) {
                            $badge_class = 'success';
                        }
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?php echo $property['property_id']; ?>">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#collapse<?php echo $property['property_id']; ?>">
                                <div class="w-100">
                                    <div class="row align-items-center">
                                        <!-- CLIENT NAME FIRST -->
                                        <div class="col-md-4">
                                            <strong>👤 <?php echo htmlspecialchars($property['client_name']); ?></strong>
                                        </div>
                                        <!-- PROPERTY SECOND -->
                                        <div class="col-md-3">
                                            <small class="text-muted">🏠 <?php echo htmlspecialchars($property['property_name']); ?></small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <span class="badge bg-<?php echo $badge_class; ?> rounded-pill">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <small class="text-muted"><?php echo $property['total_schedules']; ?> schedules</small>
                                            <?php if ($property['interest_rate'] > 0): ?>
                                            <span class="badge bg-info ms-1"><?php echo $property['interest_rate']; ?>%</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?php echo $property['property_id']; ?>" 
                             class="accordion-collapse collapse" 
                             data-bs-parent="#propertiesAccordion">
                            <div class="accordion-body p-0">
                                <!-- Property Summary -->
                                <div class="p-3 bg-light border-bottom">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <small class="text-muted d-block">Total Amount</small>
                                            <strong><?php echo format_peso($property['total_amount']); ?></strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted d-block">Overdue</small>
                                            <strong class="text-danger"><?php echo $property['overdue_count']; ?> (<?php echo format_peso($property['overdue_amount']); ?>)</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted d-block">Pending</small>
                                            <strong class="text-warning"><?php echo $property['pending_count']; ?> (<?php echo format_peso($property['pending_amount']); ?>)</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted d-block">Paid</small>
                                            <strong class="text-success"><?php echo $property['paid_count']; ?> (<?php echo format_peso($property['paid_amount']); ?>)</strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Payment Schedules Table -->
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 80px;">#</th>
                                                <th style="width: 120px;">Due Date</th>
                                                <th style="width: 90px;" class="text-center">Status</th>
                                                <th class="text-end">Amount</th>
                                                <th class="text-end">Interest</th>
                                                <th class="text-end">Paid</th>
                                                <th class="text-end">Balance</th>
                                                <th style="width: 100px;" class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($schedules)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted p-4">
                                                        <div style="font-size: 2rem;">🎉</div>
                                                        <h6 class="mt-2 text-success">All payments completed!</h6>
                                                        <small>No pending schedules for this property.</small>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php foreach ($schedules as $schedule): 
                                                $is_fully_paid = $schedule['remaining_balance'] <= 0;
                                                
                                                $row_class = '';
                                                $status_badge = 'secondary';
                                                $status_icon = '⚪';
                                                $status_label = ucfirst($schedule['status']);
                                                
                                                if ($schedule['status'] === 'overdue') {
                                                    $row_class = 'table-danger';
                                                    $status_badge = 'danger';
                                                    $status_icon = '🔴';
                                                    $status_label = $schedule['days_overdue'] . 'd late';
                                                } elseif ($schedule['status'] === 'pending') {
                                                    if ($schedule['days_until_due'] <= 7) {
                                                        $row_class = 'table-warning';
                                                    }
                                                    $status_badge = 'warning';
                                                    $status_icon = '🟡';
                                                    $status_label = 'in ' . $schedule['days_until_due'] . 'd';
                                                } elseif ($schedule['status'] === 'paid') {
                                                    $status_badge = 'success';
                                                    $status_icon = '🟢';
                                                }
                                            ?>
                                            <tr class="<?php echo $row_class; ?>">
                                                <td><strong><?php echo $schedule['schedule_number']; ?>/<?php echo $property['term_months']; ?></strong></td>
                                                <td><small><?php echo date('M d, Y', strtotime($schedule['due_date'])); ?></small></td>
                                                <td class="text-center">
                                                    <span class="badge bg-<?php echo $status_badge; ?>">
                                                        <?php echo $status_icon; ?> <?php echo $status_label; ?>
                                                    </span>
                                                </td>
                                                <td class="text-end"><strong><?php echo format_peso($schedule['amount_due']); ?></strong></td>
                                                <td class="text-end"><small><?php echo format_peso($schedule['principal_amount'] ?? 0); ?></small></td>
                                                <td class="text-end"><small><?php echo format_peso($schedule['interest_amount'] ?? 0); ?></small></td>
                                                <td class="text-end"><span class="text-success"><?php echo format_peso($schedule['total_paid']); ?></span></td>
                                                <td class="text-end">
                                                    <strong class="<?php echo $is_fully_paid ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo format_peso($schedule['remaining_balance']); ?>
                                                    </strong>
                                                </td>
                                                <td class="text-center">
                                                    <?php if (!$is_fully_paid && $schedule['status'] !== 'paid'): ?>
                                                    <a href="record_payment.php?id=<?php echo $schedule['schedule_id']; ?>" 
                                                       class="btn btn-sm btn-<?php echo $schedule['status'] === 'overdue' ? 'danger' : 'primary'; ?>">
                                                        💳 Pay
                                                    </a>
                                                    <?php else: ?>
                                                    <span class="badge bg-success">✓ Paid</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state" style="padding: 3rem 1rem;">
                    <div class="empty-icon" style="font-size: 4rem;">🏠</div>
                    <h5 class="text-muted">
                        <?php echo $has_filters ? 'No results found matching your filters.' : 'No properties with payment schedules found.'; ?>
                    </h5>
                    <p class="text-muted mb-3">
                        <?php echo $has_filters ? 'Try adjusting your search or filters.' : 'Generate payment schedules for properties first.'; ?>
                    </p>
                    <?php if (!$has_filters): ?>
                    <a href="properties.php" class="btn btn-primary">🏠 Go to Properties</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div><!-- /.container-fluid -->
</div><!-- /.main-content -->

<!-- Recent Payments Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="paymentHistoryPanel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">📜 Recent Payments</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <?php if (!empty($recent_payments)): ?>
        <div class="list-group list-group-flush">
            <?php foreach ($recent_payments as $payment): ?>
            <div class="list-group-item px-0">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1"><?php echo format_peso($payment['amount_paid']); ?></h6>
                    <small class="text-muted"><?php echo date('M d', strtotime($payment['date_paid'])); ?></small>
                </div>
                <p class="mb-1"><small><?php echo htmlspecialchars($payment['property_name']); ?> - Schedule #<?php echo $payment['schedule_number']; ?></small></p>
                <small class="text-muted">👤 <?php echo htmlspecialchars($payment['client_name']); ?></small>
                <?php if ($payment['receipt_no']): ?>
                <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($payment['receipt_no']); ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted">No recent payments recorded.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Client-First Payment Flow JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const clientSelect = document.getElementById('quickClientSelect');
    const propertySelect = document.getElementById('quickPropertySelect');
    const scheduleList = document.getElementById('quickScheduleList');
    
    // Real-Time Search Implementation
    const searchInput = document.getElementById('realTimeSearch');
    const searchStatus = document.getElementById('searchStatus');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout = null;
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            // Hide results if empty
            if (query.length === 0) {
                searchResults.style.display = 'none';
                searchStatus.style.display = 'none';
                return;
            }
            
            // Show loading spinner
            searchStatus.style.display = 'inline-block';
            
            // Debounce search - wait 500ms after user stops typing
            searchTimeout = setTimeout(function() {
                fetch('../api/search_payments.php?search=' + encodeURIComponent(query) + '&limit=20')
                    .then(response => response.json())
                    .then(result => {
                        searchStatus.style.display = 'none';
                        
                        if (result.error) {
                            searchResults.innerHTML = '<div class="alert alert-danger">Error: ' + result.error + '</div>';
                            searchResults.style.display = 'block';
                            return;
                        }
                        
                        // API returns {count, total_amount, payments: []}
                        const data = result.payments || [];
                        
                        if (data.length === 0) {
                            searchResults.innerHTML = '<div class="alert alert-info">🔍 No payments found matching "' + query + '"</div>';
                            searchResults.style.display = 'block';
                            return;
                        }
                        
                        // Build results table
                        let html = '<div class="card"><div class="card-header bg-success text-white">';
                        html += '✨ Found ' + result.count + ' result(s) - ';
                        html += result.paid_count + ' Paid (₱' + parseFloat(result.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '), ';
                        html += result.pending_count + ' Pending</div>';
                        html += '<div class="table-responsive"><table class="table table-sm table-hover mb-0">';
                        html += '<thead><tr><th>Client</th><th>Property</th><th>Schedule</th><th>Status</th><th>Amount</th><th>Receipt/Due Date</th><th>Action</th></tr></thead><tbody>';
                        
                        data.forEach(function(item) {
                            html += '<tr>';
                            // CLIENT NAME FIRST
                            html += '<td><strong>👤 ' + (item.client_name || 'N/A') + '</strong></td>';
                            // PROPERTY SECOND
                            html += '<td><small class="text-muted">🏠 ' + item.property_name + '</small></td>';
                            html += '<td><span class="badge bg-info">#' + item.schedule_number + '</span></td>';
                            
                            // Status badge
                            let statusBadge = 'secondary';
                            let statusText = 'Paid';
                            if (item.record_type === 'schedule') {
                                statusBadge = item.status === 'overdue' ? 'danger' : 'warning';
                                statusText = item.status === 'overdue' ? 'Overdue' : 'Pending';
                            }
                            html += '<td><span class="badge bg-' + statusBadge + '">' + statusText + '</span></td>';
                            
                            // Amount
                            let amount = item.record_type === 'completed' ? item.amount_paid : item.amount_due;
                            html += '<td><strong>₱' + parseFloat(amount).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</strong></td>';
                            
                            // Receipt or Due Date
                            if (item.record_type === 'completed') {
                                html += '<td><code>' + (item.receipt_no || 'N/A') + '</code><br><small>' + item.date_paid + '</small></td>';
                            } else {
                                html += '<td><small class="text-muted">Due: ' + item.due_date + '</small></td>';
                            }
                            
                            // Action button
                            if (item.record_type === 'completed') {
                                html += '<td><a href="record_payment.php?id=' + item.schedule_id + '" class="btn btn-sm btn-outline-info">📝 View</a></td>';
                            } else {
                                html += '<td><a href="record_payment.php?id=' + item.schedule_id + '" class="btn btn-sm btn-outline-primary">💳 Pay Now</a></td>';
                            }
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table></div></div>';
                        searchResults.innerHTML = html;
                        searchResults.style.display = 'block';
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                        searchStatus.style.display = 'none';
                        searchResults.innerHTML = '<div class="alert alert-danger">⚠️ Search failed. Please try again.</div>';
                        searchResults.style.display = 'block';
                    });
            }, 500);
        });
        
        // Hide results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                setTimeout(function() {
                    searchResults.style.display = 'none';
                }, 200);
            }
        });
    }
    
    // Client selection handler
    clientSelect.addEventListener('change', function() {
        const clientId = this.value;
        propertySelect.innerHTML = '<option value="">-- Select Property --</option>';
        propertySelect.disabled = true;
        scheduleList.style.display = 'none';
        
        if (!clientId) return;
        
        fetch('../api/get_client_properties.php?client_id=' + clientId)
            .then(response => response.json())
            .then(properties => {
                if (properties.error) return;
                
                properties.forEach(prop => {
                    const opt = document.createElement('option');
                    opt.value = prop.property_id;
                    opt.textContent = prop.property_name + 
                        (prop.unpaid_count > 0 ? ' (' + prop.unpaid_count + ' unpaid)' : ' (All Paid)');
                    propertySelect.appendChild(opt);
                });
                propertySelect.disabled = false;
            })
            .catch(err => console.error('Error loading properties:', err));
    });
    
    // Property selection handler
    propertySelect.addEventListener('change', function() {
        const propertyId = this.value;
        scheduleList.style.display = 'none';
        
        if (!propertyId) return;
        
        fetch('../api/get_schedules.php?property_id=' + propertyId)
            .then(response => response.json())
            .then(schedules => {
                if (schedules.error || schedules.length === 0) {
                    scheduleList.innerHTML = '<div class="alert alert-info">No unpaid schedules for this property.</div>';
                    scheduleList.style.display = 'block';
                    return;
                }
                
                let html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
                html += '<thead><tr><th>#</th><th>Due Date</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>';
                
                schedules.forEach(s => {
                    const badge = s.status === 'overdue' ? 'danger' : 'warning';
                    html += '<tr>';
                    html += '<td>' + s.schedule_number + '/' + s.term_months + '</td>';
                    html += '<td>' + s.due_date + '</td>';
                    html += '<td>₱' + parseFloat(s.amount_due).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</td>';
                    html += '<td><span class="badge bg-' + badge + '">' + s.status + '</span></td>';
                    html += '<td><a href="record_payment.php?id=' + s.schedule_id + '" class="btn btn-sm btn-primary">💳 Pay</a></td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
                scheduleList.innerHTML = html;
                scheduleList.style.display = 'block';
            })
            .catch(err => console.error('Error loading schedules:', err));
    });
});
</script>

<?php include '../templates/footer.php'; ?>
