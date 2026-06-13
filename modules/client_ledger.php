<?php
/**
 * Client Account Ledger - Dedicated Transaction History
 * Real Estate Receivable System - Phase 5
 * 
 * Shows detailed per-property ledger with running balance
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

require_login();

// Get client ID and optional property ID
if (!isset($_GET['client_id']) || !is_numeric($_GET['client_id'])) {
    set_flash_message('error', 'Invalid client ID.');
    header('Location: clients.php');
    exit();
}

$client_id = (int)$_GET['client_id'];
$property_id = isset($_GET['property_id']) && is_numeric($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
$print_mode = isset($_GET['print']) && $_GET['print'] === '1';

try {
    // Fetch client data
    $client_stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $client_stmt->execute([$client_id]);
    $client = $client_stmt->fetch();
    
    if (!$client) {
        set_flash_message('error', 'Client not found.');
        header('Location: clients.php');
        exit();
    }
    
    // Fetch client's properties
    $props_stmt = $pdo->prepare("
        SELECT 
            p.*,
            COALESCE(SUM(ps.amount_due), 0) as total_amount,
            COALESCE((
                SELECT SUM(pay.amount_paid) 
                FROM payments pay 
                INNER JOIN payment_schedules ps2 ON pay.schedule_id = ps2.schedule_id
                WHERE ps2.property_id = p.property_id
            ), 0) as total_paid
        FROM properties p
        LEFT JOIN payment_schedules ps ON p.property_id = ps.property_id
        WHERE p.client_id = ?
        GROUP BY p.property_id
        ORDER BY p.created_at DESC
    ");
    $props_stmt->execute([$client_id]);
    $properties = $props_stmt->fetchAll();
    
    // If specific property selected, filter
    $selected_property = null;
    if ($property_id > 0) {
        foreach ($properties as $prop) {
            if ($prop['property_id'] == $property_id) {
                $selected_property = $prop;
                break;
            }
        }
    }
    
    // Build ledger entries query based on property filter
    $where_property = $property_id > 0 ? "AND p.property_id = ?" : "";
    $params = $property_id > 0 ? [$client_id, $property_id] : [$client_id];
    
    // Get all schedules for this client (or property)
    $schedules_stmt = $pdo->prepare("
        SELECT 
            ps.schedule_id,
            ps.schedule_number,
            ps.due_date,
            ps.amount_due,
            ps.principal_amount,
            ps.interest_amount,
            ps.penalty_amount,
            ps.status,
            p.property_id,
            p.property_name,
            p.term_months
        FROM payment_schedules ps
        INNER JOIN properties p ON ps.property_id = p.property_id
        WHERE p.client_id = ? {$where_property}
        ORDER BY p.property_id, ps.schedule_number ASC
    ");
    $schedules_stmt->execute($params);
    $schedules = $schedules_stmt->fetchAll();
    
    // Get all payments for this client (or property)
    $payments_stmt = $pdo->prepare("
        SELECT 
            pay.payment_id,
            pay.schedule_id,
            pay.amount_paid,
            pay.date_paid,
            pay.receipt_no,
            ps.schedule_number,
            p.property_id,
            p.property_name
        FROM payments pay
        INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
        INNER JOIN properties p ON ps.property_id = p.property_id
        WHERE p.client_id = ? {$where_property}
        ORDER BY pay.date_paid ASC, pay.payment_id ASC
    ");
    $payments_stmt->execute($params);
    $payments = $payments_stmt->fetchAll();
    
    // Build ledger entries (combine schedules and payments chronologically)
    $ledger_entries = [];
    $running_balance = 0;
    
    // First, add all scheduled amounts as DEBIT entries
    foreach ($schedules as $sched) {
        $amount = $sched['amount_due'] + $sched['penalty_amount'];
        $running_balance += $amount;
        
        $ledger_entries[] = [
            'date' => $sched['due_date'],
            'type' => 'schedule',
            'description' => "Schedule #{$sched['schedule_number']}/{$sched['term_months']} - {$sched['property_name']}",
            'property_name' => $sched['property_name'],
            'property_id' => $sched['property_id'],
            'schedule_id' => $sched['schedule_id'],
            'debit' => $amount,
            'credit' => 0,
            'principal' => $sched['principal_amount'],
            'interest' => $sched['interest_amount'],
            'penalty' => $sched['penalty_amount'],
            'status' => $sched['status'],
            'balance' => $running_balance,
            'receipt' => null
        ];
    }
    
    // Reset balance and recalculate with payments
    $running_balance = 0;
    $schedule_payments = [];
    
    // Group payments by schedule
    foreach ($payments as $pay) {
        if (!isset($schedule_payments[$pay['schedule_id']])) {
            $schedule_payments[$pay['schedule_id']] = [];
        }
        $schedule_payments[$pay['schedule_id']][] = $pay;
    }
    
    // Rebuild ledger with interleaved payments
    $final_ledger = [];
    foreach ($schedules as $sched) {
        $sched_amount = $sched['amount_due'] + $sched['penalty_amount'];
        $running_balance += $sched_amount;
        
        // Add schedule entry
        $final_ledger[] = [
            'date' => $sched['due_date'],
            'sort_key' => $sched['due_date'] . '_0_' . $sched['schedule_id'],
            'type' => 'schedule',
            'description' => "Installment #{$sched['schedule_number']}/{$sched['term_months']}",
            'property_name' => $sched['property_name'],
            'property_id' => $sched['property_id'],
            'schedule_id' => $sched['schedule_id'],
            'debit' => $sched_amount,
            'credit' => 0,
            'principal' => $sched['principal_amount'],
            'interest' => $sched['interest_amount'],
            'penalty' => $sched['penalty_amount'],
            'status' => $sched['status'],
            'balance' => $running_balance,
            'receipt' => null
        ];
        
        // Add payments for this schedule
        if (isset($schedule_payments[$sched['schedule_id']])) {
            foreach ($schedule_payments[$sched['schedule_id']] as $pay) {
                $running_balance -= $pay['amount_paid'];
                
                $final_ledger[] = [
                    'date' => $pay['date_paid'],
                    'sort_key' => $pay['date_paid'] . '_1_' . $pay['payment_id'],
                    'type' => 'payment',
                    'description' => "Payment received" . ($pay['receipt_no'] ? " (#{$pay['receipt_no']})" : ""),
                    'property_name' => $sched['property_name'],
                    'property_id' => $sched['property_id'],
                    'schedule_id' => $sched['schedule_id'],
                    'payment_id' => $pay['payment_id'],
                    'debit' => 0,
                    'credit' => $pay['amount_paid'],
                    'principal' => 0,
                    'interest' => 0,
                    'penalty' => 0,
                    'status' => null,
                    'balance' => $running_balance,
                    'receipt' => $pay['receipt_no']
                ];
            }
        }
    }
    
    // Sort chronologically
    usort($final_ledger, function ($a, $b) {
        $dateA = $a['date'] ?: '9999-12-31';
        $dateB = $b['date'] ?: '9999-12-31';
        if ($dateA == $dateB) {
            $typeA = $a['type'] === 'schedule' ? 0 : 1;
            $typeB = $b['type'] === 'schedule' ? 0 : 1;
            return $typeA - $typeB;
        }
        return $dateA <=> $dateB;
    });

    // Recalculate running balance
    $running_balance = 0;
    foreach ($final_ledger as &$entry) {
        $running_balance += $entry['debit'];
        $running_balance -= $entry['credit'];
        $entry['balance'] = $running_balance;
    }
    unset($entry);

    // Calculate global totals
    $total_debits = array_sum(array_column($final_ledger, 'debit'));
    $total_credits = array_sum(array_column($final_ledger, 'credit'));
    $ending_balance = $total_debits - $total_credits;
    
    // Filters
    $filter_month = isset($_GET['month']) ? $_GET['month'] : '';
    $sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

    if ($filter_month) {
        $filtered = [];
        foreach ($final_ledger as $entry) {
            if (strpos($entry['date'], $filter_month) === 0) {
                $filtered[] = $entry;
            }
        }
        $final_ledger = $filtered;
    }

    if ($sort_order === 'desc') {
        $final_ledger = array_reverse($final_ledger);
    }
    
    // Get next due schedule (Section B: Next Due)
    $next_due_stmt = $pdo->prepare("
        SELECT 
            ps.*,
            p.property_name,
            p.property_id,
            DATEDIFF(ps.due_date, CURDATE()) as days_until_due,
            DATEDIFF(CURDATE(), ps.due_date) as days_overdue,
            COALESCE(SUM(pay.amount_paid), 0) as total_paid,
            (ps.amount_due + COALESCE(ps.penalty_amount, 0) - COALESCE(SUM(pay.amount_paid), 0)) as remaining_amount
        FROM payment_schedules ps
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN payments pay ON ps.schedule_id = pay.schedule_id
        WHERE p.client_id = ? AND ps.status IN ('pending', 'overdue')
        GROUP BY ps.schedule_id
        HAVING remaining_amount > 0
        ORDER BY ps.due_date ASC
        LIMIT 1
    ");
    $next_due_stmt->execute([$client_id]);
    $next_due = $next_due_stmt->fetch();
    
    // Determine overall account status (Section A: Header)
    $account_status = 'Active';
    $status_badge = 'success';
    $status_icon = '🟢';
    
    if ($ending_balance <= 0) {
        $account_status = 'Paid in Full';
        $status_badge = 'success';
        $status_icon = '✅';
    } elseif ($next_due && $next_due['status'] === 'overdue') {
        $account_status = 'In Arrears';
        $status_badge = 'danger';
        $status_icon = '🔴';
    } elseif ($next_due && $next_due['days_until_due'] <= 7) {
        $account_status = 'Payment Due Soon';
        $status_badge = 'warning';
        $status_icon = '🟡';
    }
    
} catch (PDOException $e) {
    error_log("Client ledger error: " . $e->getMessage());
    set_flash_message('error', 'Database error occurred.');
    header('Location: clients.php');
    exit();
}

$page_title = 'Account Ledger - ' . $client['name'];

// Print mode - minimal layout
if ($print_mode) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement of Account - <?php echo htmlspecialchars($client['name']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        h1 { font-size: 18px; margin-bottom: 5px; }
        h2 { font-size: 14px; color: #666; margin-top: 0; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .client-info { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f5f5f5; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .credit { color: green; }
        .debit { color: #333; }
        .status-paid { background-color: #d4edda; }
        .status-overdue { background-color: #f8d7da; }
        .totals { font-weight: bold; background-color: #f0f0f0; }
        .footer { margin-top: 30px; font-size: 10px; color: #666; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">🖨️ Print</button>
        <button onclick="window.close()">Close</button>
    </div>
    
    <div class="header">
        <h1>Real Estate Receivable System</h1>
        <h2>Statement of Account</h2>
    </div>
    
    <div class="client-info">
        <strong>Client:</strong> <?php echo htmlspecialchars($client['name']); ?><br>
        <strong>Contact:</strong> <?php echo htmlspecialchars($client['contact_no'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($client['email'] ?? 'N/A'); ?><br>
        <strong>Address:</strong> <?php echo htmlspecialchars($client['address'] ?? 'N/A'); ?><br>
        <strong>Statement Date:</strong> <?php echo date('F d, Y'); ?><br>
        <?php if ($selected_property): ?>
        <strong>Property:</strong> <?php echo htmlspecialchars($selected_property['property_name']); ?>
        <?php endif; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Date</th>
                <th>Description</th>
                <?php if (!$property_id): ?><th>Property</th><?php endif; ?>
                <th class="text-end" style="width: 100px;">Due of Payments</th>
                <th class="text-end" style="width: 100px;">Due</th>
                <th class="text-end" style="width: 100px;">Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($final_ledger as $entry): ?>
            <tr class="<?php echo $entry['status'] === 'paid' ? 'status-paid' : ($entry['status'] === 'overdue' ? 'status-overdue' : ''); ?>">
                <td><?php echo date('M d, Y', strtotime($entry['date'])); ?></td>
                <td><?php echo htmlspecialchars($entry['description']); ?></td>
                <?php if (!$property_id): ?><td><?php echo htmlspecialchars($entry['property_name']); ?></td><?php endif; ?>
                <td class="text-end debit"><?php echo $entry['debit'] > 0 ? format_peso($entry['debit']) : ''; ?></td>
                <td class="text-end credit"><?php echo $entry['credit'] > 0 ? format_peso($entry['credit']) : ''; ?></td>
                <td class="text-end"><?php echo format_peso($entry['balance']); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="totals">
                <td colspan="<?php echo $property_id ? 2 : 3; ?>">TOTALS</td>
                <td class="text-end"><?php echo format_peso($total_debits); ?></td>
                <td class="text-end"><?php echo format_peso($total_credits); ?></td>
                <td class="text-end"><?php echo format_peso($ending_balance); ?></td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
    </div>
</body>
</html>
    <?php
    exit();
}

include '../templates/header.php';
?>

<?php include '../templates/sidebar.php'; ?>

<!-- Main Content Wrapper -->
<div class="main-wrapper">
    <div class="main-content">
    <div class="container-fluid">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="clients.php">Clients</a></li>
                <li class="breadcrumb-item"><a href="client_dashboard.php?id=<?php echo $client_id; ?>"><?php echo htmlspecialchars($client['name']); ?></a></li>
                <li class="breadcrumb-item active">Account Ledger</li>
            </ol>
        </nav>
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2>
                        <span style="color: var(--primary-maroon);">📒</span> Account Ledger
                    </h2>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($client['name']); ?></p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <a href="?client_id=<?php echo $client_id; ?><?php echo $property_id ? "&property_id={$property_id}" : ''; ?>&print=1" 
                       target="_blank" class="btn btn-success me-2">
                        🖨️ Print Statement
                    </a>
                    <a href="client_dashboard.php?id=<?php echo $client_id; ?>" class="btn btn-outline-secondary">
                        ◀ Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card border-primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4><?php echo format_peso($total_debits); ?></h4>
                            <p>Total Due of Payments</p>
                        </div>
                        <div style="font-size: 2rem; opacity: 0.3;">📊</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card border-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4><?php echo format_peso($total_credits); ?></h4>
                            <p>Total Due</p>
                        </div>
                        <div style="font-size: 2rem; opacity: 0.3;">💳</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card border-danger">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4><?php echo format_peso($ending_balance); ?></h4>
                            <p>Outstanding Balance</p>
                        </div>
                        <div style="font-size: 2rem; opacity: 0.3;">💰</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card border-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4><?php echo count($final_ledger); ?></h4>
                            <p>Transactions</p>
                        </div>
                        <div style="font-size: 2rem; opacity: 0.3;">📋</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Section A: Client Details & Overall Account Status -->
        <div class="row mb-4">
            <div class="col-lg-7 mb-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">👤 Client Account Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted mb-1">Client Name</h6>
                                <p class="mb-0"><strong><?php echo htmlspecialchars($client['name']); ?></strong></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted mb-1">Account Status</h6>
                                <p class="mb-0">
                                    <span class="badge bg-<?php echo $status_badge; ?> fs-6">
                                        <?php echo $status_icon; ?> <?php echo $account_status; ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted mb-1">Contact</h6>
                                <p class="mb-0">
                                    📞 <?php echo htmlspecialchars($client['contact_no'] ?? 'N/A'); ?><br>
                                    ✉️ <?php echo htmlspecialchars($client['email'] ?? 'N/A'); ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted mb-1">Properties Owned</h6>
                                <p class="mb-0"><strong><?php echo count($properties); ?></strong> property/properties</p>
                            </div>
                            <div class="col-12">
                                <h6 class="text-muted mb-1">Address</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($client['address'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section B: Next Due Payment -->
            <div class="col-lg-5 mb-3">
                <div class="card <?php echo $next_due ? 'border-' . ($next_due['status'] === 'overdue' ? 'danger' : 'warning') : 'border-success'; ?>">
                    <div class="card-header <?php echo $next_due ? 'bg-' . ($next_due['status'] === 'overdue' ? 'danger' : 'warning') : 'bg-success'; ?> text-white">
                        <h5 class="mb-0">📅 Next Due Payment</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($next_due): ?>
                            <div class="text-center mb-3">
                                <h2 class="mb-1"><?php echo format_peso($next_due['remaining_amount']); ?></h2>
                                <p class="text-muted mb-0">Amount Due</p>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <small class="text-muted d-block">Property</small>
                                    <strong><?php echo htmlspecialchars($next_due['property_name']); ?></strong>
                                </div>
                                <div class="col-6 mb-2">
                                    <small class="text-muted d-block">Schedule</small>
                                    <strong>#<?php echo $next_due['schedule_number']; ?></strong>
                                </div>
                                <div class="col-6 mb-2">
                                    <small class="text-muted d-block">Due Date</small>
                                    <strong><?php echo date('M d, Y', strtotime($next_due['due_date'])); ?></strong>
                                </div>
                                <div class="col-6 mb-2">
                                    <small class="text-muted d-block">Status</small>
                                    <?php if ($next_due['status'] === 'overdue'): ?>
                                        <span class="badge bg-danger">🔴 <?php echo $next_due['days_overdue']; ?> days late</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">🟡 Due in <?php echo $next_due['days_until_due']; ?> days</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <hr>
                            <div class="d-grid">
                                <a href="record_payment.php?id=<?php echo $next_due['schedule_id']; ?>" class="btn btn-<?php echo $next_due['status'] === 'overdue' ? 'danger' : 'primary'; ?> btn-lg">
                                    💳 Pay Now
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div style="font-size: 3rem;">✅</div>
                                <h5 class="text-success mt-2">All Paid Up!</h5>
                                <p class="text-muted mb-0">No pending payments at this time.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ledger Filters -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <span>🔍</span> Filter & Sort Ledger
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Property</label>
                        <select class="form-select" id="propertyFilter" onchange="applyFilters()">
                            <option value="">All Properties</option>
                            <?php foreach ($properties as $prop): ?>
                            <option value="<?php echo $prop['property_id']; ?>"
                                    <?php echo ($property_id == $prop['property_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prop['property_name']); ?> 
                                (Balance: <?php echo format_peso($prop['total_amount'] - $prop['total_paid']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Month / Year</label>
                        <input type="month" class="form-control" id="monthFilter" value="<?php echo htmlspecialchars($filter_month); ?>" onchange="applyFilters()">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Sort Order</label>
                        <select class="form-select" id="sortOrder" onchange="applyFilters()">
                            <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Oldest First (Chronological)</option>
                            <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Newest First</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ledger Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>📒 Transaction Ledger</span>
                <span class="badge bg-light text-dark"><?php echo count($final_ledger); ?> entries</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($final_ledger) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">Date</th>
                                <th>Description</th>
                                <?php if (!$property_id): ?><th>Property</th><?php endif; ?>
                                <th class="text-end" style="width: 120px;">Due of Payments (Dr)</th>
                                <th class="text-end" style="width: 120px;">Due (Cr)</th>
                                <th class="text-end" style="width: 120px;">Balance</th>
                                <th class="text-center" style="width: 80px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($final_ledger as $entry): 
                                $row_class = '';
                                if ($entry['type'] === 'payment') {
                                    $row_class = 'table-success';
                                } elseif ($entry['status'] === 'overdue') {
                                    $row_class = 'table-danger';
                                } elseif ($entry['status'] === 'paid') {
                                    $row_class = 'table-light';
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($entry['date'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($entry['type'] === 'schedule'): ?>
                                    <strong><?php echo htmlspecialchars($entry['description']); ?></strong>
                                    <?php if ($entry['principal'] > 0 || $entry['interest'] > 0): ?>
                                    <br><small class="text-muted">
                                        Principal: <?php echo format_peso($entry['principal']); ?> | 
                                        Interest: <?php echo format_peso($entry['interest']); ?>
                                        <?php if ($entry['penalty'] > 0): ?>
                                        | <span class="text-danger">Penalty: <?php echo format_peso($entry['penalty']); ?></span>
                                        <?php endif; ?>
                                    </small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <em class="text-success"><?php echo htmlspecialchars($entry['description']); ?></em>
                                    <?php endif; ?>
                                </td>
                                <?php if (!$property_id): ?>
                                <td><small><?php echo htmlspecialchars($entry['property_name']); ?></small></td>
                                <?php endif; ?>
                                <td class="text-end">
                                    <?php if ($entry['debit'] > 0): ?>
                                    <strong><?php echo format_peso($entry['debit']); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($entry['credit'] > 0): ?>
                                    <strong class="text-success"><?php echo format_peso($entry['credit']); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <strong><?php echo format_peso($entry['balance']); ?></strong>
                                </td>
                                <td class="text-center">
                                    <?php if ($entry['type'] === 'schedule'): ?>
                                        <?php if ($entry['status'] === 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                        <?php elseif ($entry['status'] === 'overdue'): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                    <span class="badge bg-success">✓</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="<?php echo $property_id ? 2 : 3; ?>">TOTALS</th>
                                <th class="text-end"><?php echo format_peso($total_debits); ?></th>
                                <th class="text-end"><?php echo format_peso($total_credits); ?></th>
                                <th class="text-end"><?php echo format_peso($ending_balance); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state p-5 text-center">
                    <div style="font-size: 4rem;">📒</div>
                    <h5 class="text-muted">No transactions found</h5>
                    <p class="text-muted">This client has no payment schedules or payments recorded.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<script>
function applyFilters() {
    const propertyFilter = document.getElementById('propertyFilter');
    const monthFilter = document.getElementById('monthFilter').value;
    const sortOrder = document.getElementById('sortOrder').value;
    
    let url = 'client_ledger.php?client_id=<?php echo $client_id; ?>';
    
    if (propertyFilter && propertyFilter.value) {
        url += '&property_id=' + encodeURIComponent(propertyFilter.value);
    }
    if (monthFilter) {
        url += '&month=' + encodeURIComponent(monthFilter);
    }
    if (sortOrder && sortOrder !== 'asc') {
        url += '&sort=' + encodeURIComponent(sortOrder);
    }
    
    window.location.href = url;
}
</script>

<?php include '../templates/footer.php'; ?>
