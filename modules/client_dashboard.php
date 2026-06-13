<?php
/**
 * Client Account Dashboard
 * Real Estate Receivable System - Phase 4
 * 
 * Comprehensive client financial overview with Due vs Paid stats
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

require_login();

// Get client ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid client ID.');
    header('Location: clients.php');
    exit();
}

$client_id = (int) $_GET['id'];

try {
    // Fetch client data with comprehensive stats
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            COUNT(DISTINCT p.property_id) as property_count,
            COALESCE(SUM(p.total_price), 0) as total_contract_value,
            COALESCE(SUM(p.security_deposit), 0) as total_security_deposits
        FROM clients c
        LEFT JOIN properties p ON c.client_id = p.client_id
        WHERE c.client_id = ?
        GROUP BY c.client_id
    ");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    if (!$client) {
        set_flash_message('error', 'Client not found.');
        header('Location: clients.php');
        exit();
    }

    // Get payment statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(ps.schedule_id) as total_schedules,
            COUNT(CASE WHEN ps.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN ps.status = 'overdue' THEN 1 END) as overdue_count,
            COUNT(CASE WHEN ps.status = 'paid' THEN 1 END) as paid_count,
            COALESCE(SUM(ps.amount_due), 0) as total_due,
            COALESCE(SUM(CASE WHEN ps.status = 'pending' THEN ps.amount_due ELSE 0 END), 0) as pending_amount,
            COALESCE(SUM(CASE WHEN ps.status = 'overdue' THEN ps.amount_due ELSE 0 END), 0) as overdue_amount,
            COALESCE(SUM(CASE WHEN ps.status = 'paid' THEN ps.amount_due ELSE 0 END), 0) as paid_amount
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

    // Get properties with status
    $properties_stmt = $pdo->prepare("
        SELECT 
            p.*,
            COUNT(ps.schedule_id) as total_schedules,
            SUM(CASE WHEN ps.status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
            SUM(CASE WHEN ps.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN ps.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            COALESCE(SUM(ps.amount_due), 0) as total_amount,
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

    // Get recent payments (last 10)
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
        LIMIT 10
    ");
    $recent_payments_stmt->execute([$client_id]);
    $recent_payments = $recent_payments_stmt->fetchAll();

    // Get overdue schedules
    $overdue_stmt = $pdo->prepare("
        SELECT 
            ps.*,
            p.property_name,
            DATEDIFF(CURDATE(), ps.due_date) as days_overdue,
            COALESCE(SUM(pay.amount_paid), 0) as total_paid
        FROM payment_schedules ps
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN payments pay ON ps.schedule_id = pay.schedule_id
        WHERE p.client_id = ? AND ps.status = 'overdue'
        GROUP BY ps.schedule_id
        ORDER BY ps.due_date ASC
        LIMIT 10
    ");
    $overdue_stmt->execute([$client_id]);
    $overdue_schedules = $overdue_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Client dashboard error: " . $e->getMessage());
    set_flash_message('error', 'Failed to load client data.');
    header('Location: clients.php');
    exit();
}

$page_title = 'Dashboard - ' . $client['name'];

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
                    <li class="breadcrumb-item active">
                        <?php echo htmlspecialchars($client['name']); ?>
                    </li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2>
                            <span style="color: var(--primary-maroon);">👤</span>
                            <?php echo htmlspecialchars($client['name']); ?>
                        </h2>
                        <p class="text-muted mb-0">Client Account Dashboard</p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <a href="client_edit.php?id=<?php echo $client_id; ?>" class="btn btn-outline-primary me-2">
                            ✏️ Edit Client
                        </a>
                        <a href="client_ledger.php?client_id=<?php echo $client_id; ?>" class="btn btn-success me-2">
                            📒 Account Ledger
                        </a>
                        <a href="payment_ledger.php?client_id=<?php echo $client_id; ?>" class="btn btn-info">
                            📋 Payment History
                        </a>
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

            <!-- Summary Cards Row 1 -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card border-primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4>
                                    <?php echo format_peso($stats['total_due']); ?>
                                </h4>
                                <p>Total Due</p>
                            </div>
                            <div style="font-size: 2.5rem; opacity: 0.3;">📊</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card border-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4>
                                    <?php echo format_peso($total_paid); ?>
                                </h4>
                                <p>Total Paid</p>
                            </div>
                            <div style="font-size: 2.5rem; opacity: 0.3;">✅</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card border-danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4>
                                    <?php echo format_peso($remaining_balance); ?>
                                </h4>
                                <p>Remaining Balance</p>
                            </div>
                            <div style="font-size: 2.5rem; opacity: 0.3;">💳</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card border-warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4>
                                    <?php echo format_peso($stats['overdue_amount']); ?>
                                </h4>
                                <p>Overdue Amount</p>
                            </div>
                            <div style="font-size: 2.5rem; opacity: 0.3;">⚠️</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Progress -->
            <div class="card mb-4">
                <div class="card-header">
                    <span>📈</span> Payment Progress
                </div>
                <div class="card-body">
                    <?php
                    $progress_percent = $stats['total_due'] > 0 ? ($total_paid / $stats['total_due']) * 100 : 0;
                    ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><strong>Overall Progress</strong></span>
                        <span><strong>
                                <?php echo number_format($progress_percent, 1); ?>%
                            </strong> Complete</span>
                    </div>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar bg-success" role="progressbar"
                            style="width: <?php echo $progress_percent; ?>%;">
                            <?php echo format_peso($total_paid); ?> Paid
                        </div>
                    </div>
                    <div class="row mt-3 text-center">
                        <div class="col-md-3">
                            <span class="badge bg-success fs-6">
                                <?php echo $stats['paid_count']; ?>
                            </span>
                            <small class="d-block text-muted">Paid</small>
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-warning text-dark fs-6">
                                <?php echo $stats['pending_count']; ?>
                            </span>
                            <small class="d-block text-muted">Pending</small>
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-danger fs-6">
                                <?php echo $stats['overdue_count']; ?>
                            </span>
                            <small class="d-block text-muted">Overdue</small>
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-secondary fs-6">
                                <?php echo $stats['total_schedules']; ?>
                            </span>
                            <small class="d-block text-muted">Total</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Properties Column -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>🏠 Properties</span>
                            <span class="badge bg-light text-dark">
                                <?php echo count($properties); ?>
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($properties) > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($properties as $prop):
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
                                                        <a href="property_edit.php?id=<?php echo $prop['property_id']; ?>"
                                                            class="text-decoration-none">
                                                            <?php echo htmlspecialchars($prop['property_name']); ?>
                                                        </a>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo format_peso($prop['amount_paid']); ?> of
                                                        <?php echo format_peso($prop['total_amount']); ?>
                                                    </small>
                                                    <div class="progress mt-2" style="height: 5px;">
                                                        <div class="progress-bar bg-success"
                                                            style="width: <?php echo $prop_progress; ?>%;"></div>
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
                                <div class="empty-state p-4 text-center">
                                    <p class="text-muted mb-0">No properties found for this client.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Payments Column -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>💳 Recent Payments</span>
                            <a href="payment_ledger.php?client_id=<?php echo $client_id; ?>"
                                class="btn btn-sm btn-outline-primary">
                                View All
                            </a>
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
                                                    <?php echo htmlspecialchars($pay['property_name']); ?> - 
                                                    <?php if ($pay['schedule_number'] == 0): ?>
                                                        <span class="badge bg-info text-dark" style="font-size:0.75em;">🔐 Security Deposit</span>
                                                    <?php else: ?>
                                                        Schedule #<?php echo $pay['schedule_number']; ?>
                                                    <?php endif; ?>
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

            <!-- Overdue Alert -->
            <?php if (count($overdue_schedules) > 0): ?>
                <div class="card border-danger mb-4">
                    <div class="card-header bg-danger text-white">
                        <span>⚠️</span> Overdue Payments Requiring Attention
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-danger">
                                    <tr>
                                        <th>Property</th>
                                        <th>Schedule</th>
                                        <th>Due Date</th>
                                        <th>Days Late</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Estimated Penalty</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overdue_schedules as $od):
                                        $remaining = $od['amount_due'] - $od['total_paid'];
                                        $penalty = calculate_penalty($od['amount_due'], $od['days_overdue']);
                                        ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($od['property_name']); ?>
                                            </td>
                                            <td>
                                                <?php if ($od['schedule_number'] == 0): ?>
                                                    <span class="badge bg-info text-dark">🔐 Deposit</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">#<?php echo $od['schedule_number']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($od['due_date'])); ?>
                                            </td>
                                            <td><strong class="text-danger">
                                                    <?php echo $od['days_overdue']; ?> days
                                                </strong></td>
                                            <td class="text-end">
                                                <?php echo format_peso($remaining); ?>
                                            </td>
                                            <td class="text-end"><span class="text-warning">
                                                    <?php echo format_peso($penalty); ?>
                                                </span></td>
                                            <td class="text-center">
                                                <a href="record_payment.php?id=<?php echo $od['schedule_id']; ?>"
                                                    class="btn btn-sm btn-danger">💳 Pay</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Client Contact Info -->
            <div class="card">
                <div class="card-header">
                    <span>📞</span> Contact Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-muted">Email</h6>
                            <p>
                                <?php echo $client['email'] ? htmlspecialchars($client['email']) : 'Not provided'; ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Contact Number</h6>
                            <p>
                                <?php echo $client['contact_no'] ? htmlspecialchars($client['contact_no']) : 'Not provided'; ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Client Since</h6>
                            <p>
                                <?php echo date('F d, Y', strtotime($client['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php if ($client['address']): ?>
                        <div class="row">
                            <div class="col-12">
                                <h6 class="text-muted">Address</h6>
                                <p>
                                    <?php echo nl2br(htmlspecialchars($client['address'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <?php include '../templates/footer.php'; ?>