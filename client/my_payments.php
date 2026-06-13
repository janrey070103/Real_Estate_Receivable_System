<?php
/**
 * Client My Payments Page
 * Real Estate Receivable System - Phase 8
 * 
 * Shows payment history for the logged-in client
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

// Require client role
require_client();

$client_id = get_client_id();

// Pagination
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

try {
    // Fetch client data
    $client_stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $client_stmt->execute([$client_id]);
    $client = $client_stmt->fetch();

    // Get total count
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM payments pay
        INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
        INNER JOIN properties p ON ps.property_id = p.property_id
        WHERE p.client_id = ?
    ");
    $count_stmt->execute([$client_id]);
    $total_count = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_count / $per_page);

    // Get total paid amount
    $total_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(pay.amount_paid), 0) as total_paid
        FROM payments pay
        INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
        INNER JOIN properties p ON ps.property_id = p.property_id
        WHERE p.client_id = ?
    ");
    $total_stmt->execute([$client_id]);
    $total_paid = $total_stmt->fetch()['total_paid'];

    // Fetch payments with pagination
    $payments_stmt = $pdo->prepare("
        SELECT 
            pay.*,
            ps.schedule_number,
            ps.due_date,
            ps.amount_due,
            p.property_id,
            p.property_name
        FROM payments pay
        INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
        INNER JOIN properties p ON ps.property_id = p.property_id
        WHERE p.client_id = ?
        ORDER BY pay.date_paid DESC, pay.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $payments_stmt->execute([$client_id, $per_page, $offset]);
    $payments = $payments_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("My payments error: " . $e->getMessage());
    set_flash_message('error', 'Failed to load payment history.');
    $payments = [];
    $total_count = 0;
    $total_pages = 0;
    $total_paid = 0;
}

$page_title = 'Payment History';

include '../templates/client_header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">My Dashboard</a></li>
        <li class="breadcrumb-item active">Payment History</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h2>
                <span style="color: var(--primary-maroon);">💳</span>
                Payment History
            </h2>
            <p class="text-muted mb-0">All your recorded payments</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="stats-card border-success d-inline-block px-4 py-2">
                <small class="text-muted">Total Paid</small>
                <h5 class="mb-0 text-success">
                    <?php echo format_peso($total_paid); ?>
                </h5>
            </div>
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

<!-- Payments Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>📋 Payment Records</span>
        <span class="badge bg-light text-dark">
            <?php echo $total_count; ?> payments
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (count($payments) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Property</th>
                            <th>Schedule</th>
                            <th class="text-end">Amount</th>
                            <th>Receipt #</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $pay): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php echo date('M d, Y', strtotime($pay['date_paid'])); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($pay['property_name']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">#
                                        <?php echo $pay['schedule_number']; ?>
                                    </span>
                                    <small class="text-muted d-block">
                                        Due:
                                        <?php echo date('M d, Y', strtotime($pay['due_date'])); ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success">
                                        <?php echo format_peso($pay['amount_paid']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($pay['receipt_no']): ?>
                                        <span class="badge bg-light text-dark">
                                            <?php echo htmlspecialchars($pay['receipt_no']); ?>
                                        </span>
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
                <div class="card-footer">
                    <nav aria-label="Payment pagination">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5">
                <div style="font-size: 4rem;">💳</div>
                <h5 class="text-muted">No Payments Yet</h5>
                <p class="text-muted">Your payment history will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</div>

<?php include '../templates/client_footer.php'; ?>