<?php
/**
 * Client Portal Dashboard
 * Real Estate Receivable System - Phase 8
 * 
 * Personalized dashboard for client users showing their properties and payments
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

// Require client role
require_client();

$client_id = get_client_id();

try {
    // Fetch client data
    $client_stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $client_stmt->execute([$client_id]);
    $client = $client_stmt->fetch();

    if (!$client) {
        set_flash_message('error', 'Client profile not found.');
        header('Location: ../auth/logout.php');
        exit();
    }

    // Get payment statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(ps.schedule_id) as total_schedules,
            COUNT(CASE WHEN ps.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN ps.status = 'overdue' THEN 1 END) as overdue_count,
            COUNT(CASE WHEN ps.status = 'paid' THEN 1 END) as paid_count,
            COALESCE(SUM(ps.amount_due + COALESCE(ps.penalty_amount, 0)), 0) as total_due,
            COALESCE(SUM(CASE WHEN ps.status = 'overdue' THEN ps.amount_due + COALESCE(ps.penalty_amount, 0) ELSE 0 END), 0) as overdue_amount
        FROM payment_schedules ps
        INNER JOIN properties p ON ps.property_id = p.property_id
        WHERE p.client_id = ?
    ");
    $stats_stmt->execute([$client_id]);
    $stats = $stats_stmt->fetch();

    // Get total payments made
    $payments_total_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(pay.amount_paid), 0) as total_paid
        FROM payments pay
        INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
        INNER JOIN properties p ON ps.property_id = p.property_id
        WHERE p.client_id = ?
    ");
    $payments_total_stmt->execute([$client_id]);
    $total_paid = $payments_total_stmt->fetch()['total_paid'];

    // Calculate remaining balance
    $remaining_balance = $stats['total_due'] - $total_paid;

    // Get properties
    $properties_stmt = $pdo->prepare("
        SELECT 
            p.*,
            COUNT(ps.schedule_id) as total_schedules,
            SUM(CASE WHEN ps.status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
            SUM(CASE WHEN ps.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN ps.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            COALESCE(SUM(ps.amount_due + COALESCE(ps.penalty_amount, 0)), 0) as total_amount,
            COALESCE((
                SELECT SUM(pay.amount_paid) 
                FROM payments pay 
                INNER JOIN payment_schedules ps2 ON pay.schedule_id = ps2.schedule_id
                WHERE ps2.property_id = p.property_id
            ), 0) as amount_paid
        FROM properties p
        LEFT JOIN payment_schedules ps ON p.property_id = ps.property_id
        WHERE p.client_id = ?
        GROUP BY p.property_id
        ORDER BY p.created_at DESC
    ");
    $properties_stmt->execute([$client_id]);
    $properties = $properties_stmt->fetchAll();

    // Get next payment due
    $next_payment_stmt = $pdo->prepare("
        SELECT 
            ps.*,
            p.property_name,
            DATEDIFF(ps.due_date, CURDATE()) as days_until_due
        FROM payment_schedules ps
        INNER JOIN properties p ON ps.property_id = p.property_id
        WHERE p.client_id = ? AND ps.status IN ('pending', 'overdue')
        ORDER BY ps.due_date ASC
        LIMIT 1
    ");
    $next_payment_stmt->execute([$client_id]);
    $next_payment = $next_payment_stmt->fetch();

    // Get recent payments (last 5)
    $recent_payments_stmt = $pdo->prepare("
        SELECT 
            pay.*,
            ps.schedule_number,
            ps.due_date,
            p.property_name
        FROM payments pay
        INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
        INNER JOIN properties p ON ps.property_id = p.property_id
        WHERE p.client_id = ?
        ORDER BY pay.date_paid DESC, pay.created_at DESC
        LIMIT 5
    ");
    $recent_payments_stmt->execute([$client_id]);
    $recent_payments = $recent_payments_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Client dashboard error: " . $e->getMessage());
    set_flash_message('error', 'Failed to load dashboard data.');
    $stats = ['total_schedules' => 0, 'pending_count' => 0, 'overdue_count' => 0, 'paid_count' => 0, 'total_due' => 0, 'overdue_amount' => 0];
    $total_paid = 0;
    $remaining_balance = 0;
    $properties = [];
    $next_payment = null;
    $recent_payments = [];
}

$page_title = 'My Dashboard';

include '../templates/client_header.php';
?>

<!-- Welcome Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h2>
                <span style="color: var(--primary-maroon);">👋</span>
                Welcome,
                <?php echo htmlspecialchars($client['name']); ?>!
            </h2>
            <p class="text-muted mb-0">Your real estate account overview</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="text-muted">
                <?php echo date('l, F d, Y'); ?>
            </span>
        </div>
    </div>
</div>

<?php
$flash = get_flash_message();
if ($flash):
    $alert_class = $flash['type'] === 'success' ? 'alert-success' :
        ($flash['type'] === 'error' ? 'alert-danger' : 'alert-info');
    ?>
    <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Next Payment Alert -->
<?php if ($next_payment): ?>
    <div class="alert <?php echo $next_payment['status'] === 'overdue' ? 'alert-danger' : 'alert-warning'; ?> mb-4">
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0">
                <span style="font-size: 2rem;">
                    <?php echo $next_payment['status'] === 'overdue' ? '⚠️' : '📅'; ?>
                </span>
            </div>
            <div class="flex-grow-1 ms-3">
                <?php if ($next_payment['status'] === 'overdue'): ?>
                    <strong>Overdue Payment!</strong>
                    <p class="mb-0">
                        Schedule #
                        <?php echo $next_payment['schedule_number']; ?> for
                        <strong>
                            <?php echo htmlspecialchars($next_payment['property_name']); ?>
                        </strong>
                        was due
                        <?php echo abs($next_payment['days_until_due']); ?> days ago.
                        Amount: <strong>
                            <?php echo format_peso($next_payment['amount_due']); ?>
                        </strong>
                    </p>
                <?php else: ?>
                    <strong>Upcoming Payment</strong>
                    <p class="mb-0">
                        Schedule #
                        <?php echo $next_payment['schedule_number']; ?> for
                        <strong>
                            <?php echo htmlspecialchars($next_payment['property_name']); ?>
                        </strong>
                        is due in
                        <?php echo $next_payment['days_until_due']; ?> days
                        (
                        <?php echo date('M d, Y', strtotime($next_payment['due_date'])); ?>).
                        Amount: <strong>
                            <?php echo format_peso($next_payment['amount_due']); ?>
                        </strong>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid var(--primary-maroon) !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Amount Due (incl. penalties)</p>
                        <h4 class="mb-0 text-maroon" style="color: var(--primary-maroon); font-weight: 700;">
                            <?php echo format_peso($stats['total_due']); ?>
                        </h4>
                    </div>
                    <div class="bg-light rounded-circle p-3">
                        <span style="font-size: 1.5rem;">📊</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #28a745 !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Paid</p>
                        <h4 class="mb-0" style="color: #28a745; font-weight: 700;">
                            <?php echo format_peso($total_paid); ?>
                        </h4>
                    </div>
                    <div class="bg-light rounded-circle p-3">
                        <span style="font-size: 1.5rem;">✅</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #dc3545 !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Remaining Balance</p>
                        <h4 class="mb-0" style="color: #dc3545; font-weight: 700;">
                            <?php echo format_peso($remaining_balance); ?>
                        </h4>
                    </div>
                    <div class="bg-light rounded-circle p-3">
                        <span style="font-size: 1.5rem;">💳</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #17a2b8 !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Properties</p>
                        <h4 class="mb-0" style="color: #17a2b8; font-weight: 700;">
                            <?php echo count($properties); ?>
                        </h4>
                    </div>
                    <div class="bg-light rounded-circle p-3">
                        <span style="font-size: 1.5rem;">🏘️</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Progress -->
<div class="card mb-4">
    <div class="card-header">
        <span>📈</span> Overall Payment Progress
    </div>
    <div class="card-body">
        <?php
        $progress_percent = $stats['total_due'] > 0 ? ($total_paid / $stats['total_due']) * 100 : 0;
        ?>
        <div class="d-flex justify-content-between mb-2">
            <span>
                <strong>Progress:</strong>
                <span class="text-success"><?php echo format_peso($total_paid); ?></span>
                <span class="text-muted small">/ <?php echo format_peso($stats['total_due']); ?></span>
            </span>
            <span><strong>
                    <?php echo number_format($progress_percent, 1); ?>%
                </strong></span>
        </div>
        <div class="progress" style="height: 20px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progress_percent; ?>%;"
                aria-valuenow="<?php echo $progress_percent; ?>" aria-valuemin="0" aria-valuemax="100">
            </div>
        </div>
        <small class="text-muted d-block mt-2">
            Based on all scheduled amounts and penalties across your properties.
        </small>
        <div class="row mt-3 text-center">
            <div class="col-md-4">
                <span class="badge bg-success fs-6">
                    <?php echo $stats['paid_count']; ?>
                </span>
                <small class="d-block text-muted">Paid</small>
            </div>
            <div class="col-md-4">
                <span class="badge bg-warning text-dark fs-6">
                    <?php echo $stats['pending_count']; ?>
                </span>
                <small class="d-block text-muted">Pending</small>
            </div>
            <div class="col-md-4">
                <span class="badge bg-danger fs-6">
                    <?php echo $stats['overdue_count']; ?>
                </span>
                <small class="d-block text-muted">Overdue</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- My Properties -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>🏘️ My Properties</span>
                <a href="my_properties.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (count($properties) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($properties, 0, 3) as $prop):
                            $prop_balance = $prop['total_amount'] - $prop['amount_paid'];
                            $prop_progress = $prop['total_amount'] > 0 ? ($prop['amount_paid'] / $prop['total_amount']) * 100 : 0;

                            $badge_class = 'secondary';
                            $badge_text = 'No Schedule';
                            if ($prop['overdue_count'] > 0) {
                                $badge_class = 'danger';
                                $badge_text = $prop['overdue_count'] . ' Overdue';
                            } elseif ($prop['pending_count'] > 0) {
                                $badge_class = 'warning';
                                $badge_text = $prop['pending_count'] . ' Pending';
                            } elseif ($prop['paid_count'] > 0 && $prop['paid_count'] == $prop['total_schedules']) {
                                $badge_class = 'success';
                                $badge_text = 'Fully Paid';
                            }
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($prop['property_name']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo format_peso($prop['amount_paid']); ?> of
                                            <?php echo format_peso($prop['total_amount']); ?>
                                        </small>
                                        <div class="progress mt-2" style="height: 5px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $prop_progress; ?>%;">
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge bg-<?php echo $badge_class; ?> ms-2">
                                        <?php echo $badge_text; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state p-5 text-center">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🏛️</div>
                        <h5 class="text-muted mb-2">Property Assignment Pending</h5>
                        <p class="text-muted mb-3">
                            Your registration has been approved!<br>
                            Our team is processing your property acquisition request.
                        </p>
                        <div class="alert alert-info mb-0" style="font-size: 0.9rem;">
                            <strong>📞 What's Next:</strong><br>
                            Our sales team will contact you to:<br>
                            <ul class="text-start mt-2 mb-0" style="font-size: 0.85rem;">
                                <li>Finalize property details and pricing</li>
                                <li>Assign the property to your account</li>
                                <li>Generate your payment schedule</li>
                                <li>You can then track payments here</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>💳 Recent Payments</span>
                <a href="my_payments.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (count($recent_payments) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_payments as $pay): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-success">
                                        <?php echo format_peso($pay['amount_paid']); ?>
                                    </strong>
                                    <small class="d-block text-muted">
                                        <?php echo htmlspecialchars($pay['property_name']); ?> - Schedule #
                                        <?php echo $pay['schedule_number']; ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <small>
                                        <?php echo date('M d, Y', strtotime($pay['date_paid'])); ?>
                                    </small>
                                    <?php if ($pay['receipt_no']): ?>
                                        <br><span class="badge bg-light text-dark">
                                            <?php echo htmlspecialchars($pay['receipt_no']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state p-4 text-center">
                        <p class="text-muted mb-0">No payments recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</div>

<?php include '../templates/client_footer.php'; ?>