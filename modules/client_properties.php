<?php
/**
 * Client Properties Page
 * Real Estate Receivable System
 * 
 * View all properties owned by a specific client
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Require user to be logged in
require_login();

// Get client ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid client ID.');
    header('Location: clients.php');
    exit();
}

$client_id = (int) $_GET['id'];

// Fetch client data
try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    if (!$client) {
        set_flash_message('error', 'Client not found.');
        header('Location: clients.php');
        exit();
    }

    // Fetch properties with payment statistics
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            COUNT(ps.schedule_id) as total_schedules,
            SUM(CASE WHEN ps.status = 'paid' THEN ps.amount_due ELSE 0 END) as total_paid,
            SUM(CASE WHEN ps.status = 'pending' THEN ps.amount_due ELSE 0 END) as total_pending,
            SUM(CASE WHEN ps.status = 'overdue' THEN ps.amount_due ELSE 0 END) as total_overdue,
            SUM(ps.amount_due) as total_scheduled
        FROM properties p
        LEFT JOIN payment_schedules ps ON p.property_id = ps.property_id
        WHERE p.client_id = ?
        GROUP BY p.property_id
        ORDER BY p.contract_date DESC
    ");
    $stmt->execute([$client_id]);
    $properties = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Fetch properties error: " . $e->getMessage());
    $error_message = "Database error occurred.";
    $properties = [];
}

$page_title = 'Client Properties - ' . $client['name'];

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
                    <li class="breadcrumb-item"><a href="clients.php">Clients</a></li>
                    <li class="breadcrumb-item"><a
                            href="client_edit.php?id=<?php echo $client_id; ?>"><?php echo htmlspecialchars($client['name']); ?></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Properties</li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2>
                            <span style="color: var(--primary-blue);">🏘️</span> Properties
                        </h2>
                        <p class="text-muted mb-0">
                            Client: <strong><?php echo htmlspecialchars($client['name']); ?></strong>
                            <?php if ($client['email']): ?>
                                | <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>"
                                    class="text-decoration-none"><?php echo htmlspecialchars($client['email']); ?></a>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <a href="client_edit.php?id=<?php echo $client_id; ?>" class="btn btn-outline-secondary">
                            <span>◀</span> Back to Client
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Properties List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><span>🏘️</span> Property Portfolio</span>
                    <span class="badge bg-light text-dark"><?php echo count($properties); ?> Properties</span>
                </div>
                <div class="card-body">
                    <?php if (count($properties) > 0): ?>
                        <div class="row g-3">
                            <?php foreach ($properties as $property): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title" style="color: var(--primary-blue);">
                                                <?php echo htmlspecialchars($property['property_name']); ?>
                                            </h5>

                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Total Price</small>
                                                    <h6>₱<?php echo number_format($property['total_price'], 2); ?></h6>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Contract Date</small>
                                                    <h6><?php echo date('M d, Y', strtotime($property['contract_date'])); ?>
                                                    </h6>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Term</small>
                                                    <h6><?php echo $property['term_months']; ?> months</h6>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Schedules</small>
                                                    <h6><?php echo $property['total_schedules']; ?> payments</h6>
                                                </div>
                                            </div>

                                            <!-- Payment Progress -->
                                            <div class="mb-3">
                                                <small class="text-muted d-block mb-1">Payment Progress</small>
                                                <?php
                                                $total = $property['total_scheduled'] > 0 ? $property['total_scheduled'] : 1;
                                                $paid_percent = ($property['total_paid'] / $total) * 100;
                                                $pending_percent = ($property['total_pending'] / $total) * 100;
                                                $overdue_percent = ($property['total_overdue'] / $total) * 100;
                                                ?>
                                                <div class="progress" style="height: 25px;">
                                                    <div class="progress-bar bg-success" role="progressbar"
                                                        style="width: <?php echo $paid_percent; ?>%"
                                                        title="Paid: ₱<?php echo number_format($property['total_paid'], 2); ?>">
                                                        <?php if ($paid_percent > 10): ?>
                                                            <?php echo round($paid_percent); ?>%
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="progress-bar bg-warning" role="progressbar"
                                                        style="width: <?php echo $pending_percent; ?>%"
                                                        title="Pending: ₱<?php echo number_format($property['total_pending'], 2); ?>">
                                                        <?php if ($pending_percent > 10): ?>
                                                            <?php echo round($pending_percent); ?>%
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="progress-bar bg-danger" role="progressbar"
                                                        style="width: <?php echo $overdue_percent; ?>%"
                                                        title="Overdue: ₱<?php echo number_format($property['total_overdue'], 2); ?>">
                                                        <?php if ($overdue_percent > 10): ?>
                                                            <?php echo round($overdue_percent); ?>%
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-2">
                                                    <small class="text-success">
                                                        <strong>Paid:</strong>
                                                        ₱<?php echo number_format($property['total_paid'], 2); ?>
                                                    </small>
                                                    <small class="text-warning">
                                                        <strong>Pending:</strong>
                                                        ₱<?php echo number_format($property['total_pending'], 2); ?>
                                                    </small>
                                                    <small class="text-danger">
                                                        <strong>Overdue:</strong>
                                                        ₱<?php echo number_format($property['total_overdue'], 2); ?>
                                                    </small>
                                                </div>
                                            </div>

                                            <div class="d-grid">
                                                <a href="properties.php?id=<?php echo $property['property_id']; ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    View Details →
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">🏘️</div>
                            <h5 class="text-muted">No Properties Found</h5>
                            <p class="text-muted mb-3">This client doesn't have any properties yet.</p>
                            <a href="properties.php?client_id=<?php echo $client_id; ?>" class="btn btn-primary">
                                <span>➕</span> Add Property
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <?php
    // Include footer
    include '../templates/footer.php';
    ?>