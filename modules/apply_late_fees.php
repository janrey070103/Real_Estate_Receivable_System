<?php
/**
 * Apply Late Fees - Automated Penalty Application
 * Real Estate Receivable System - Phase 4
 * 
 * Applies late fees/penalties to overdue payment schedules
 * Can be run manually or via cron job
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

// Check if running from CLI or web
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    require_login();
    require_module_access('admin');
}

// Configuration
$penalty_rate = 3.0;       // 3% per month
$max_penalty_rate = 25.0;  // Max 25% of amount due
$min_days_overdue = 1;     // Apply penalty after this many days

try {
    // Get all overdue schedules that need penalty application
    $stmt = $pdo->prepare("
        SELECT 
            ps.schedule_id,
            ps.amount_due,
            ps.penalty_amount as current_penalty,
            ps.due_date,
            DATEDIFF(CURDATE(), ps.due_date) as days_overdue,
            p.property_name,
            c.name as client_name
        FROM payment_schedules ps
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        WHERE ps.status = 'overdue' 
          AND DATEDIFF(CURDATE(), ps.due_date) >= ?
        ORDER BY ps.due_date ASC
    ");
    $stmt->execute([$min_days_overdue]);
    $overdue_schedules = $stmt->fetchAll();

    $total_schedules = count($overdue_schedules);
    $updated_count = 0;
    $total_penalty_applied = 0;
    $results = [];

    // Begin transaction
    $pdo->beginTransaction();

    foreach ($overdue_schedules as $schedule) {
        // Calculate new penalty
        $new_penalty = calculate_penalty(
            $schedule['amount_due'],
            $schedule['days_overdue'],
            $penalty_rate,
            $max_penalty_rate
        );

        $current_penalty = floatval($schedule['current_penalty']);

        // Only update if penalty has increased
        if ($new_penalty > $current_penalty) {
            $penalty_increase = $new_penalty - $current_penalty;

            $update_stmt = $pdo->prepare("
                UPDATE payment_schedules 
                SET penalty_amount = ?
                WHERE schedule_id = ?
            ");
            $update_stmt->execute([$new_penalty, $schedule['schedule_id']]);

            $updated_count++;
            $total_penalty_applied += $penalty_increase;

            $results[] = [
                'schedule_id' => $schedule['schedule_id'],
                'client' => $schedule['client_name'],
                'property' => $schedule['property_name'],
                'days_overdue' => $schedule['days_overdue'],
                'amount_due' => $schedule['amount_due'],
                'old_penalty' => $current_penalty,
                'new_penalty' => $new_penalty,
                'increase' => $penalty_increase
            ];
        }
    }

    // Commit transaction
    $pdo->commit();

    // Log the action
    if ($updated_count > 0 && !$is_cli) {
        log_audit(
            $pdo,
            'APPLY_LATE_FEES',
            'schedules:' . $updated_count,
            'Applied late fees to ' . $updated_count . ' schedules. Total: ₱' . number_format($total_penalty_applied, 2)
        );
    }

    $success = true;
    $message = "Processed {$total_schedules} overdue schedules. Updated {$updated_count} with new penalties totaling " . format_peso($total_penalty_applied);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Apply late fees error: " . $e->getMessage());
    $success = false;
    $message = "Database error: " . $e->getMessage();
    $results = [];
}

// CLI Output
if ($is_cli) {
    echo "===================================\n";
    echo "RERS - Late Fee Application Report\n";
    echo "===================================\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n";
    echo "Penalty Rate: {$penalty_rate}% per month\n";
    echo "Max Penalty: {$max_penalty_rate}%\n\n";

    if ($success) {
        echo "Status: SUCCESS\n";
        echo "Overdue Schedules Found: {$total_schedules}\n";
        echo "Schedules Updated: {$updated_count}\n";
        echo "Total Penalty Applied: " . format_peso($total_penalty_applied) . "\n\n";

        if (count($results) > 0) {
            echo "Details:\n";
            echo str_repeat('-', 80) . "\n";
            foreach ($results as $r) {
                echo "Schedule #{$r['schedule_id']}: {$r['client']} - {$r['property']}\n";
                echo "  Days Overdue: {$r['days_overdue']}, Penalty: " . format_peso($r['old_penalty']) . " -> " . format_peso($r['new_penalty']) . " (+" . format_peso($r['increase']) . ")\n";
            }
        }
    } else {
        echo "Status: FAILED\n";
        echo "Error: {$message}\n";
    }
    exit($success ? 0 : 1);
}

// Web Output
$page_title = 'Apply Late Fees';
include '../templates/header.php';
?>

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
                    <li class="breadcrumb-item active">Apply Late Fees</li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="page-header">
                <h2>
                    <span style="color: var(--primary-maroon);">⚠️</span> Late Fee Application
                </h2>
                <p class="text-muted">Automatically calculate and apply penalties to overdue schedules</p>
            </div>

            <!-- Result Card -->
            <div class="card mb-4">
                <div class="card-header bg-<?php echo $success ? 'success' : 'danger'; ?> text-white">
                    <?php echo $success ? '✅ Late Fees Applied Successfully' : '❌ Error Applying Late Fees'; ?>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded text-center">
                                <h4>
                                    <?php echo $total_schedules; ?>
                                </h4>
                                <small class="text-muted">Overdue Found</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded text-center">
                                <h4>
                                    <?php echo $updated_count; ?>
                                </h4>
                                <small class="text-muted">Updated</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded text-center">
                                <h4>
                                    <?php echo format_peso($total_penalty_applied); ?>
                                </h4>
                                <small class="text-muted">Total Penalty</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded text-center">
                                <h4>
                                    <?php echo $penalty_rate; ?>%
                                </h4>
                                <small class="text-muted">Rate/Month</small>
                            </div>
                        </div>
                    </div>

                    <p class="mb-0">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                </div>
            </div>

            <!-- Results Table -->
            <?php if (count($results) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <span>📋</span> Penalty Updates Applied
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Schedule</th>
                                        <th>Client</th>
                                        <th>Property</th>
                                        <th class="text-center">Days Late</th>
                                        <th class="text-end">Amount Due</th>
                                        <th class="text-end">Old Penalty</th>
                                        <th class="text-end">New Penalty</th>
                                        <th class="text-end">Increase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $r): ?>
                                        <tr>
                                            <td><strong>#
                                                    <?php echo $r['schedule_id']; ?>
                                                </strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($r['client']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($r['property']); ?>
                                            </td>
                                            <td class="text-center"><span class="badge bg-danger">
                                                    <?php echo $r['days_overdue']; ?>
                                                </span></td>
                                            <td class="text-end">
                                                <?php echo format_peso($r['amount_due']); ?>
                                            </td>
                                            <td class="text-end"><span class="text-muted">
                                                    <?php echo format_peso($r['old_penalty']); ?>
                                                </span></td>
                                            <td class="text-end"><strong class="text-warning">
                                                    <?php echo format_peso($r['new_penalty']); ?>
                                                </strong></td>
                                            <td class="text-end"><span class="text-danger">+
                                                    <?php echo format_peso($r['increase']); ?>
                                                </span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($success): ?>
                <div class="alert alert-info">
                    <span>ℹ️</span> No schedules required penalty updates. All existing penalties are up to date.
                </div>
            <?php endif; ?>

            <!-- Configuration Info -->
            <div class="card mt-4">
                <div class="card-header">
                    <span>⚙️</span> Configuration
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6>Penalty Rate</h6>
                            <p class="mb-0">
                                <?php echo $penalty_rate; ?>% per month (pro-rated daily)
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6>Maximum Penalty</h6>
                            <p class="mb-0">
                                <?php echo $max_penalty_rate; ?>% of amount due
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6>Grace Period</h6>
                            <p class="mb-0">
                                <?php echo $min_days_overdue; ?> day(s) after due date
                            </p>
                        </div>
                    </div>
                    <hr>
                    <p class="text-muted mb-0">
                        <small>
                            <strong>Note:</strong> This page can be run via cron job for automatic daily penalty
                            updates.
                            Command: <code>php /path/to/modules/apply_late_fees.php</code>
                        </small>
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-4 text-center">
                <a href="apply_late_fees.php" class="btn btn-warning me-2">🔄 Run Again</a>
                <a href="payments.php" class="btn btn-primary">💳 Go to Payments</a>
            </div>

        </div>
    </div>

    <?php include '../templates/footer.php'; ?>