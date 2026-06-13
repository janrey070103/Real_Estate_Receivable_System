<?php
/**
 * Client Account Ledger
 * Real Estate Receivable System - Phase 8
 * 
 * Client view of their account statements with running balance
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

// Require client role
require_client();

$client_id = get_client_id();
$property_id = isset($_GET['property_id']) && is_numeric($_GET['property_id']) ? (int) $_GET['property_id'] : 0;
$print_mode = isset($_GET['print']) && $_GET['print'] === '1';

try {
    // Fetch client data
    $client_stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $client_stmt->execute([$client_id]);
    $client = $client_stmt->fetch();

    if (!$client) {
        set_flash_message('error', 'Client profile not found.');
        header('Location: dashboard.php');
        exit();
    }

    // Fetch client's properties
    $props_stmt = $pdo->prepare("
        SELECT 
            p.*,
            COALESCE(SUM(ps.amount_due + COALESCE(ps.penalty_amount, 0)), 0) as total_amount,
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

    // Verify property belongs to client if specified
    $selected_property = null;
    if ($property_id > 0) {
        foreach ($properties as $prop) {
            if ($prop['property_id'] == $property_id) {
                $selected_property = $prop;
                break;
            }
        }
        if (!$selected_property) {
            set_flash_message('error', 'Property not found or access denied.');
            header('Location: my_ledger.php');
            exit();
        }
    }

    // Build ledger entries
    $where_property = $property_id > 0 ? "AND p.property_id = ?" : "";
    $params = $property_id > 0 ? [$client_id, $property_id] : [$client_id];

    // Get schedules
    $schedules_stmt = $pdo->prepare("
        SELECT 
            ps.*,
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

    // Get payments
    $payments_stmt = $pdo->prepare("
        SELECT 
            pay.*,
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

    // Build ledger
    $running_balance = 0;
    $schedule_payments = [];

    foreach ($payments as $pay) {
        if (!isset($schedule_payments[$pay['schedule_id']])) {
            $schedule_payments[$pay['schedule_id']] = [];
        }
        $schedule_payments[$pay['schedule_id']][] = $pay;
    }

    $final_ledger = [];
    $upcoming_limit = 3;
    $upcoming_count = 0;
    $future_hidden_count = 0;
    $future_hidden_debit = 0;

    foreach ($schedules as $sched) {
        $sched_amount = $sched['amount_due'] + ($sched['penalty_amount'] ?? 0);
        $running_balance += $sched_amount;

        $is_future_pending = ($sched['status'] === 'pending' && $sched['due_date'] > date('Y-m-d'));
        $has_payments = isset($schedule_payments[$sched['schedule_id']]);

        // Logic: Hide if future pending, limit exceeded, and NO associated payments
        if ($is_future_pending && !$has_payments) {
            if ($upcoming_count >= $upcoming_limit) {
                $future_hidden_count++;
                $future_hidden_debit += $sched_amount;
                continue; // Skip adding to display ledger
            }
            $upcoming_count++;
        }

        $final_ledger[] = [
            'date' => $sched['due_date'],
            'type' => 'schedule',
            'description' => "Installment #{$sched['schedule_number']}/{$sched['term_months']}",
            'property_name' => $sched['property_name'],
            'debit' => $sched_amount,
            'credit' => 0,
            'principal' => $sched['principal_amount'],
            'interest' => $sched['interest_amount'],
            'penalty' => $sched['penalty_amount'] ?? 0,
            'status' => $sched['status'],
            'balance' => $running_balance
        ];

        if ($has_payments) {
            foreach ($schedule_payments[$sched['schedule_id']] as $pay) {
                $running_balance -= $pay['amount_paid'];

                $final_ledger[] = [
                    'date' => $pay['date_paid'],
                    'type' => 'payment',
                    'description' => "Payment received" . ($pay['receipt_no'] ? " (#{$pay['receipt_no']})" : ""),
                    'property_name' => $sched['property_name'],
                    'debit' => 0,
                    'credit' => $pay['amount_paid'],
                    'principal' => 0,
                    'interest' => 0,
                    'penalty' => 0,
                    'status' => null,
                    'balance' => $running_balance
                ];
            }
        }
    }

    // Add Summary Row for Hidden Future Installments
    if ($future_hidden_count > 0) {
        $final_ledger[] = [
            'date' => '', // No specific date
            'type' => 'future_summary',
            'description' => "Remaining Future Installments ({$future_hidden_count} months)",
            'property_name' => $schedules[0]['property_name'] ?? '',
            'debit' => $future_hidden_debit,
            'credit' => 0,
            'principal' => 0,
            'interest' => 0,
            'penalty' => 0,
            'status' => 'pending',
            'balance' => $running_balance
        ];
    }

    // Always sort chronologically to ensure accurate ledger
    $future_summary = null;
    if ($future_hidden_count > 0) {
        $future_summary = array_pop($final_ledger);
    }
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

    // Recalculate strict running balances chronologically
    $running_balance = 0;
    foreach ($final_ledger as &$entry) {
        $running_balance += $entry['debit'];
        $running_balance -= $entry['credit'];
        $entry['balance'] = $running_balance;
    }
    unset($entry);

    if ($future_summary) {
        $future_summary['balance'] = $running_balance;
        $final_ledger[] = $future_summary;
    }

    // Calculate global totals BEFORE filtering
    $total_debits = array_sum(array_column($final_ledger, 'debit'));
    $total_credits = array_sum(array_column($final_ledger, 'credit'));
    $ending_balance = $total_debits - $total_credits;

    // Apply User Filters
    $filter_month = isset($_GET['month']) ? $_GET['month'] : '';
    $sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'desc' : 'asc';

    if ($filter_month) {
        $filtered = [];
        foreach ($final_ledger as $entry) {
            if ($entry['type'] === 'future_summary')
                continue;
            if (strpos($entry['date'], $filter_month) === 0) {
                $filtered[] = $entry;
            }
        }
        $final_ledger = $filtered;
    }

    if ($sort_order === 'desc') {
        $future = null;
        if (!$filter_month && $future_hidden_count > 0 && count($final_ledger) > 0) {
            $future = array_pop($final_ledger);
        }
        $final_ledger = array_reverse($final_ledger);
        if ($future && count($final_ledger) > 0) {
            array_unshift($final_ledger, $future);
        }
    }

} catch (PDOException $e) {
    error_log("My ledger error: " . $e->getMessage());
    set_flash_message('error', 'Failed to load ledger.');
    $final_ledger = [];
    $total_debits = 0;
    $total_credits = 0;
    $ending_balance = 0;
    $properties = [];
}

$page_title = 'Account Ledger';

// Print mode
if ($print_mode) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Statement of Account - <?php echo htmlspecialchars($client['name']); ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                margin: 20px;
            }

            h1 {
                font-size: 18px;
                margin-bottom: 5px;
            }

            h2 {
                font-size: 14px;
                color: #666;
                margin-top: 0;
            }

            .header {
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }

            .client-info {
                margin-bottom: 20px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 6px;
                text-align: left;
            }

            th {
                background-color: #f5f5f5;
            }

            .text-end {
                text-align: right;
            }

            .credit {
                color: green;
            }

            .debit {
                color: #333;
            }

            .totals {
                font-weight: bold;
                background-color: #f0f0f0;
            }

            @media print {
                .no-print {
                    display: none;
                }
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
            <strong>Contact:</strong> <?php echo htmlspecialchars($client['contact_no'] ?? 'N/A'); ?><br>
            <strong>Statement Date:</strong> <?php echo date('F d, Y'); ?><br>
            <?php if ($selected_property): ?>
                <strong>Property:</strong> <?php echo htmlspecialchars($selected_property['property_name']); ?>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <?php if (!$property_id): ?>
                        <th>Property</th><?php endif; ?>
                    <th class="text-end">Due of Payments</th>
                    <th class="text-end">Due</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($final_ledger as $entry): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($entry['date'])); ?></td>
                        <td><?php echo htmlspecialchars($entry['description']); ?></td>
                        <?php if (!$property_id): ?>
                            <td><?php echo htmlspecialchars($entry['property_name']); ?></td><?php endif; ?>
                        <td class="text-end"><?php echo $entry['debit'] > 0 ? format_peso($entry['debit']) : ''; ?></td>
                        <td class="text-end credit"><?php echo $entry['credit'] > 0 ? format_peso($entry['credit']) : ''; ?>
                        </td>
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

        <div style="margin-top: 30px; font-size: 10px; color: #666;">
            Generated on <?php echo date('F d, Y h:i A'); ?>
        </div>
    </body>

    </html>
    <?php
    exit();
}

include '../templates/client_header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">My Dashboard</a></li>
        <li class="breadcrumb-item active">Account Ledger</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h2>
                <span style="color: var(--primary-maroon);">📒</span>
                Account Ledger
            </h2>
            <p class="text-muted mb-0">Your transaction history</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="?<?php echo $property_id ? "property_id={$property_id}&" : ''; ?>print=1" target="_blank"
                class="btn btn-success">
                🖨️ Print Statement
            </a>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid var(--primary-maroon) !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Due of Payments</p>
                        <h4 class="mb-0" style="color: var(--primary-maroon); font-weight: 700;">
                            <?php echo format_peso($total_debits); ?>
                        </h4>
                    </div>
                    <div class="bg-light rounded-circle p-3">
                        <span style="font-size: 1.5rem;">📊</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #28a745 !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Due</p>
                        <h4 class="mb-0" style="color: #28a745; font-weight: 700;">
                            <?php echo format_peso($total_credits); ?>
                        </h4>
                    </div>
                    <div class="bg-light rounded-circle p-3">
                        <span style="font-size: 1.5rem;">💳</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #dc3545 !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Outstanding Balance</p>
                        <h4 class="mb-0" style="color: #dc3545; font-weight: 700;">
                            <?php echo format_peso($ending_balance); ?>
                        </h4>
                    </div>
                    <div class="bg-light rounded-circle p-3">
                        <span style="font-size: 1.5rem;">💰</span>
                    </div>
                </div>
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
            <?php if (count($properties) > 1): ?>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Property</label>
                    <select class="form-select" id="propertyFilter" onchange="applyFilters()">
                        <option value="">All Properties</option>
                        <?php foreach ($properties as $prop): ?>
                            <option value="<?php echo $prop['property_id']; ?>" <?php echo ($property_id == $prop['property_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prop['property_name']); ?>
                                (Balance: <?php echo format_peso($prop['total_amount'] - $prop['total_paid']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-md-<?php echo count($properties) > 1 ? '4' : '6'; ?>">
                <label class="form-label small text-muted mb-1">Month / Year</label>
                <input type="month" class="form-control" id="monthFilter"
                    value="<?php echo htmlspecialchars($filter_month); ?>" onchange="applyFilters()">
            </div>

            <div class="col-md-<?php echo count($properties) > 1 ? '4' : '6'; ?>">
                <label class="form-label small text-muted mb-1">Sort Order</label>
                <select class="form-select" id="sortOrder" onchange="applyFilters()">
                    <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Oldest First
                        (Chronological)</option>
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
                            <?php if (!$property_id): ?>
                                <th>Property</th><?php endif; ?>
                            <th class="text-end" style="width: 120px;">Due of Payments</th>
                            <th class="text-end" style="width: 120px;">Due</th>
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
                            } elseif ($entry['type'] === 'future_summary') {
                                $row_class = 'table-light text-muted fst-italic';
                            }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><small><?php echo $entry['date'] ? date('M d, Y', strtotime($entry['date'])) : 'Future'; ?></small>
                                </td>
                                <td>
                                    <?php if ($entry['type'] === 'schedule'): ?>
                                        <strong><?php echo htmlspecialchars($entry['description']); ?></strong>
                                    <?php elseif ($entry['type'] === 'future_summary'): ?>
                                        <span><?php echo htmlspecialchars($entry['description']); ?></span>
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
                                <td class="text-end"><strong><?php echo format_peso($entry['balance']); ?></strong></td>
                                <td class="text-center">
                                    <?php if ($entry['type'] === 'schedule' || $entry['type'] === 'future_summary'): ?>
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
            <div class="text-center py-5">
                <div style="font-size: 4rem;">📒</div>
                <h5 class="text-muted">No Transactions</h5>
                <p class="text-muted">Your account ledger will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function applyFilters() {
        const propertyFilter = document.getElementById('propertyFilter');
        const monthFilter = document.getElementById('monthFilter').value;
        const sortOrder = document.getElementById('sortOrder').value;

        let url = 'my_ledger.php?';
        const params = [];

        if (propertyFilter && propertyFilter.value) {
            params.push('property_id=' + encodeURIComponent(propertyFilter.value));
        }
        if (monthFilter) {
            params.push('month=' + encodeURIComponent(monthFilter));
        }
        if (sortOrder && sortOrder !== 'asc') {
            params.push('sort=' + encodeURIComponent(sortOrder));
        }

        window.location.href = url + params.join('&');
    }
</script>

<?php include '../templates/client_footer.php'; ?>