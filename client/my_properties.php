<?php
/**
 * Client My Properties Page
 * Real Estate Receivable System - Phase 8
 * 
 * Shows all properties owned by the logged-in client
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

    // Fetch properties with payment statistics
    $properties_stmt = $pdo->prepare("
        SELECT 
            p.*,
            COUNT(ps.schedule_id) as total_schedules,
            SUM(CASE WHEN ps.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN ps.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN ps.status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
            COALESCE(SUM(ps.amount_due + COALESCE(ps.penalty_amount, 0)), 0) as total_scheduled,
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
        ORDER BY p.contract_date DESC
    ");
    $properties_stmt->execute([$client_id]);
    $properties = $properties_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("My properties error: " . $e->getMessage());
    set_flash_message('error', 'Failed to load properties.');
    $properties = [];
}

$page_title = 'My Properties';

include '../templates/client_header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">My Dashboard</a></li>
        <li class="breadcrumb-item active">My Properties</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h2>
                <span style="color: var(--primary-maroon);">🏘️</span>
                My Properties
            </h2>
            <p class="text-muted mb-0">Your real estate portfolio</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <span class="badge bg-light text-dark fs-6">
                <?php echo count($properties); ?> Properties
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

<!-- Properties Grid -->
<div class="row">
    <?php if (count($properties) > 0): ?>
        <?php foreach ($properties as $property):
            $balance = $property['total_scheduled'] - $property['total_paid'];
            $progress = $property['total_scheduled'] > 0 ? ($property['total_paid'] / $property['total_scheduled']) * 100 : 0;

            // Determine status
            $status_class = 'secondary';
            $status_text = 'No Schedule';
            if ($property['overdue_count'] > 0) {
                $status_class = 'danger';
                $status_text = $property['overdue_count'] . ' Overdue';
            } elseif ($property['pending_count'] > 0) {
                $status_class = 'warning';
                $status_text = $property['pending_count'] . ' Pending';
            } elseif ($property['paid_count'] == $property['total_schedules'] && $property['total_schedules'] > 0) {
                $status_class = 'success';
                $status_text = '✓ Fully Paid';
            }
            ?>
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0" style="color: var(--primary-maroon);">
                            <?php echo htmlspecialchars($property['property_name']); ?>
                        </h5>
                        <span class="badge bg-<?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">Contract Value</small>
                                <h6>
                                    <?php echo format_peso($property['total_price']); ?>
                                </h6>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Contract Date</small>
                                <h6>
                                    <?php echo date('M d, Y', strtotime($property['contract_date'])); ?>
                                </h6>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">Term</small>
                                <h6>
                                    <?php echo $property['term_months']; ?> months
                                </h6>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Schedules</small>
                                <h6>
                                    <?php echo $property['paid_count']; ?> /
                                    <?php echo $property['total_schedules']; ?> paid
                                </h6>
                            </div>
                        </div>

                        <!-- Payment Progress -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Payment Progress</small>
                                <small><strong>
                                        <?php echo number_format($progress, 1); ?>%
                                    </strong></small>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar"
                                    style="width: <?php echo $progress; ?>%;">
                                </div>
                            </div>
                        </div>

                        <div class="row text-center">
                            <div class="col-4">
                                <small class="text-muted d-block">Total Due</small>
                                <strong>
                                    <?php echo format_peso($property['total_scheduled']); ?>
                                </strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Paid</small>
                                <strong class="text-success">
                                    <?php echo format_peso($property['total_paid']); ?>
                                </strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Balance</small>
                                <strong class="text-danger">
                                    <?php echo format_peso($balance); ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <a href="my_ledger.php?property_id=<?php echo $property['property_id']; ?>"
                            class="btn btn-sm btn-outline-primary">
                            📒 View Ledger
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <div style="font-size: 4rem;">🏘️</div>
                    <h5 class="text-muted">No Properties Found</h5>
                    <p class="text-muted">You don't have any properties linked to your account yet.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

</div>

<?php include '../templates/client_footer.php'; ?>