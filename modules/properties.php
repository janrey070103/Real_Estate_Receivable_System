<?php
/**
 * Properties Management - Listing Page
 * Real Estate Receivable System
 * 
 * Displays all properties with search, pagination, and action buttons
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Require user to be logged in
require_login();

// Set page title
$page_title = 'Property Management';

// Handle property deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];

    try {
        // Get property name for confirmation message
        $stmt = $pdo->prepare("SELECT property_name FROM properties WHERE property_id = ?");
        $stmt->execute([$delete_id]);
        $property = $stmt->fetch();

        if ($property) {
            // Delete property (CASCADE will automatically delete payment_schedules and payments)
            $stmt = $pdo->prepare("DELETE FROM properties WHERE property_id = ?");
            $stmt->execute([$delete_id]);

            // Log property deletion
            log_audit($pdo, 'DELETE_PROPERTY', 'property_id:' . $delete_id, 'Deleted property: ' . $property['property_name'] . ' and all associated schedules');

            set_flash_message('success', "Property '{$property['property_name']}' and all associated schedules deleted successfully!");
        } else {
            set_flash_message('error', 'Property not found.');
        }
    } catch (PDOException $e) {
        error_log("Delete property error: " . $e->getMessage());
        set_flash_message('error', 'Failed to delete property. Please try again.');
    }

    header('Location: properties.php');
    exit();
}

// Pagination settings
$records_per_page = 10;
// Fix: Enforce positive integer to prevent SQL injection via negative page numbers
$current_page = (int) max(1, isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1);
$offset = ($current_page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_query = '';
$search_params = [];

if (!empty($search)) {
    $search_query = " WHERE p.property_name LIKE ? OR c.name LIKE ?";
    $search_param = "%{$search}%";
    $search_params = [$search_param, $search_param];
}

try {
    // Get total number of properties
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM properties p
        LEFT JOIN clients c ON p.client_id = c.client_id
        {$search_query}
    ");
    $count_stmt->execute($search_params);
    $total_records = (int) $count_stmt->fetch()['total'];
    $total_pages = (int) ceil($total_records / $records_per_page);

    // Fetch properties with client info and payment statistics
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.name as client_name,
            COUNT(ps.schedule_id) as total_schedules,
            SUM(CASE WHEN ps.status = 'paid' THEN ps.amount_due ELSE 0 END) as total_paid,
            SUM(CASE WHEN ps.status = 'pending' THEN ps.amount_due ELSE 0 END) as total_pending,
            SUM(CASE WHEN ps.status = 'overdue' THEN ps.amount_due ELSE 0 END) as total_overdue
        FROM properties p
        LEFT JOIN clients c ON p.client_id = c.client_id
        LEFT JOIN payment_schedules ps ON p.property_id = ps.property_id
        {$search_query}
        GROUP BY p.property_id
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");

    $params = array_merge($search_params, [$records_per_page, $offset]);
    $stmt->execute($params);
    $properties = $stmt->fetchAll();

    // Get dashboard statistics
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(p.property_id) as total_properties,
            COUNT(DISTINCT p.client_id) as total_clients,
            COALESCE(SUM(p.total_price), 0) as total_value,
            (SELECT COUNT(*) FROM payment_schedules) as total_schedules
        FROM properties p
    ");
    $stats = $stats_stmt->fetch();

} catch (PDOException $e) {
    error_log("Properties listing error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again later.";
    $properties = [];
    $stats = ['total_properties' => 0, 'total_clients' => 0, 'total_value' => 0, 'total_schedules' => 0];
}

// Include header
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
                            <span style="color: var(--primary-blue);">🏘️</span> Property Management
                        </h2>
                        <p class="text-muted mb-0">Manage real estate properties and payment schedules</p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <a href="property_add.php" class="btn btn-primary">
                            <span>➕</span> Add New Property
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

            <?php
            $flash = get_flash_message();
            if ($flash):
                $alert_class = 'alert-info';
                if ($flash['type'] === 'success')
                    $alert_class = 'alert-success';
                if ($flash['type'] === 'error')
                    $alert_class = 'alert-danger';
                if ($flash['type'] === 'warning')
                    $alert_class = 'alert-warning';
                ?>
                <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
                    <strong><?php echo ucfirst($flash['type']); ?>!</strong>
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo number_format($stats['total_properties']); ?></h3>
                                <p>Total Properties</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">🏘️</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo number_format($stats['total_clients']); ?></h3>
                                <p>Active Clients</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">👥</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 style="font-size: 1.3rem;">₱<?php echo number_format($stats['total_value'], 2); ?>
                                </h3>
                                <p>Total Property Value</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">💰</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo number_format($stats['total_schedules']); ?></h3>
                                <p>Payment Schedules</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">📅</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="search-form">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="properties.php" class="row g-3 align-items-center">
                            <div class="col-md-8 col-lg-9">
                                <input type="text" name="search" class="form-control"
                                    placeholder="🔍 Search by property name or client name..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <div class="d-grid d-md-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-fill">
                                        <span>🔍</span> Search
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="properties.php" class="btn btn-outline-secondary flex-fill">
                                            <span>✖</span> Clear
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Properties Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><span>📋</span> Property List</span>
                    <span class="badge bg-light text-dark"><?php echo count($properties); ?> of
                        <?php echo $total_records; ?> properties</span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($properties) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th>Property Name</th>
                                        <th>Client</th>
                                        <th style="width: 150px;" class="text-end">Total Price</th>
                                        <th style="width: 120px;">Contract Date</th>
                                        <th style="width: 100px;" class="text-center">Term</th>
                                        <th style="width: 100px;" class="text-center">Schedules</th>
                                        <th style="width: 120px;">Status</th>
                                        <th style="width: 220px;" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($properties as $property): ?>
                                        <?php
                                        $total_scheduled = $property['total_paid'] + $property['total_pending'] + $property['total_overdue'];
                                        $payment_progress = $total_scheduled > 0 ? ($property['total_paid'] / $total_scheduled) * 100 : 0;

                                        $status_badge = 'secondary';
                                        $status_text = 'No Schedule';
                                        if ($property['total_schedules'] > 0) {
                                            if ($property['total_overdue'] > 0) {
                                                $status_badge = 'danger';
                                                $status_text = 'Overdue';
                                            } elseif ($property['total_pending'] > 0) {
                                                $status_badge = 'warning';
                                                $status_text = 'Pending';
                                            } else {
                                                $status_badge = 'success';
                                                $status_text = 'Paid Up';
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td><strong class="text-muted">#<?php echo $property['property_id']; ?></strong>
                                            </td>
                                            <td>
                                                <strong
                                                    class="text-dark"><?php echo htmlspecialchars($property['property_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($property['client_id']) && !empty($property['client_name'])): ?>
                                                    <a href="client_edit.php?id=<?php echo $property['client_id']; ?>"
                                                        class="text-decoration-none">
                                                        <?php echo htmlspecialchars($property['client_name']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-success">🏷️ Available</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <strong
                                                    style="color: var(--primary-blue);">₱<?php echo number_format($property['total_price'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <small
                                                    class="text-muted"><?php echo date('M d, Y', strtotime($property['contract_date'])); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?php echo $property['term_months']; ?> mos</span>
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="badge bg-primary rounded-pill"><?php echo $property['total_schedules']; ?></span>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-<?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if (empty($property['client_id'])): ?>
                                                        <a href="property_sell.php?property_id=<?php echo $property['property_id']; ?>"
                                                            class="btn btn-success" title="Sell This Property"
                                                            data-bs-toggle="tooltip">
                                                            🏷️ Sell
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="property_edit.php?id=<?php echo $property['property_id']; ?>"
                                                        class="btn btn-outline-primary" title="Edit Property"
                                                        data-bs-toggle="tooltip">
                                                        ✏️
                                                    </a>
                                                    <?php if (!empty($property['client_id'])): ?>
                                                    <a href="property_edit.php?id=<?php echo $property['property_id']; ?>#schedules"
                                                        class="btn btn-outline-info" title="View Schedules"
                                                        data-bs-toggle="tooltip">
                                                        📅
                                                    </a>
                                                    <?php endif; ?>
                                                    <a href="properties.php?delete=<?php echo $property['property_id']; ?>"
                                                        class="btn btn-outline-danger" title="Delete Property"
                                                        data-bs-toggle="tooltip"
                                                        onclick="return confirm('⚠️ WARNING: Delete this property?\n\nThis will also delete:\n• <?php echo $property['total_schedules']; ?> payment schedule(s)\n• All associated payments\n\nThis action CANNOT be undone!');">
                                                        🗑️
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-light">
                                <nav aria-label="Property pagination">
                                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link"
                                                href="?page=<?php echo max(((int) $current_page - 1), 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                                                tabindex="-1">
                                                <span aria-hidden="true">&laquo;</span> Previous
                                            </a>
                                        </li>

                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <?php if ($i == 1 || $i == $total_pages || ($i >= ((int) $current_page - 2) && $i <= ((int) $current_page + 2))): ?>
                                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                                    <a class="page-link"
                                                        href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php elseif ($i == ((int) $current_page - 3) || $i == ((int) $current_page + 3)): ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                            <a class="page-link"
                                                href="?page=<?php echo min(((int) $current_page + 1), $total_pages); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                                Next <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">🏘️</div>
                            <h5 class="text-muted">
                                <?php echo !empty($search) ? 'No properties found matching your search.' : 'No properties found.'; ?>
                            </h5>
                            <p class="text-muted mb-3">
                                <?php echo !empty($search) ? 'Try adjusting your search terms.' : 'Add your first property to get started!'; ?>
                            </p>
                            <?php if (empty($search)): ?>
                                <a href="property_add.php" class="btn btn-primary">➕ Add New Property</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div> <!-- Close container-fluid -->
    </div> <!-- Close main-content -->

    <?php
    // Include footer
    include '../templates/footer.php';
    ?>