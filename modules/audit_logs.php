<?php
/**
 * Audit Logs Viewer
 * Real Estate Receivable System
 * 
 * Displays all system activity and user actions
 * Admin-only access
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// IMPORTANT: Require admin role
require_role('admin');

// Set page title
$page_title = 'Audit Logs';

// Pagination settings
$records_per_page = 50;
$current_page = (int) max(1, isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1);
$offset = ($current_page - 1) * $records_per_page;

// Filter settings
$filter_action = isset($_GET['action']) ? $_GET['action'] : 'all';
$filter_user = isset($_GET['user']) ? (int) $_GET['user'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build filter query
$where_conditions = [];
$params = [];

if ($filter_action !== 'all') {
    $where_conditions[] = "al.action = ?";
    $params[] = strtoupper($filter_action);
}

if ($filter_user !== 'all') {
    $where_conditions[] = "al.user_id = ?";
    $params[] = $filter_user;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(al.timestamp) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(al.timestamp) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = "(al.target LIKE ? OR al.details LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM audit_log al {$where_clause}";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = (int) $count_stmt->fetch()['total'];
    $total_pages = (int) ceil($total_records / $records_per_page);

    // Fetch audit logs with user info (use separate params array for this query)
    $query = "
        SELECT 
            al.*,
            u.username,
            u.role
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.user_id
        {$where_clause}
        ORDER BY al.timestamp DESC
        LIMIT ? OFFSET ?
    ";

    // Create new params array for the main query (includes LIMIT params)
    $query_params = array_merge($params, [$records_per_page, $offset]);

    $stmt = $pdo->prepare($query);
    $stmt->execute($query_params);
    $logs = $stmt->fetchAll();

    // Get unique actions for filter dropdown
    $actions_stmt = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action");
    $actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get all users for filter dropdown
    $users_stmt = $pdo->query("SELECT user_id, username, role FROM users ORDER BY username");
    $users = $users_stmt->fetchAll();

    // Get statistics
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_logs,
            COUNT(DISTINCT user_id) as unique_users,
            COUNT(DISTINCT DATE(timestamp)) as active_days,
            MAX(timestamp) as last_activity
        FROM audit_log
    ");
    $stats = $stats_stmt->fetch();

} catch (PDOException $e) {
    error_log("Audit logs error: " . $e->getMessage());
    $error_message = "Failed to load audit logs.";
    $logs = [];
    $actions = [];
    $users = [];
    $stats = ['total_logs' => 0, 'unique_users' => 0, 'active_days' => 0, 'last_activity' => null];
}

// Include header
include '../templates/header.php';
?>

<!-- Include Navigation -->
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
                            <span style="color: var(--primary-blue);">📋</span> Audit Logs
                        </h2>
                        <p class="text-muted mb-0">System activity and user action history</p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <span class="badge bg-info fs-6">Admin Only</span>
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
                                <h3><?php echo number_format($stats['total_logs']); ?></h3>
                                <p>Total Logs</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">📊</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo number_format($stats['unique_users']); ?></h3>
                                <p>Active Users</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">👥</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo number_format($stats['active_days']); ?></h3>
                                <p>Active Days</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">📅</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo $stats['last_activity'] ? date('M d, H:i', strtotime($stats['last_activity'])) : 'N/A'; ?>
                                </h3>
                                <p>Last Activity</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">🕒</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-header">
                    <span>🔍</span> Filters
                </div>
                <div class="card-body">
                    <form method="GET" action="audit_logs.php" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Action</label>
                            <select name="action" class="form-select form-select-sm">
                                <option value="all" <?php echo $filter_action === 'all' ? 'selected' : ''; ?>>All Actions
                                </option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filter_action === $action ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($action); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">User</label>
                            <select name="user" class="form-select form-select-sm">
                                <option value="all" <?php echo $filter_user === 'all' ? 'selected' : ''; ?>>All Users
                                </option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>" <?php echo $filter_user == $user['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        (<?php echo htmlspecialchars($user['role']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control form-control-sm"
                                value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control form-control-sm"
                                value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control form-control-sm"
                                placeholder="Search target or details..."
                                value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                            </div>
                        </div>
                        <?php if ($filter_action !== 'all' || $filter_user !== 'all' || !empty($date_from) || !empty($date_to) || !empty($search)): ?>
                            <div class="col-12">
                                <a href="audit_logs.php" class="btn btn-outline-secondary btn-sm">
                                    <span>✖</span> Clear Filters
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Audit Logs Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><span>📋</span> Activity Log</span>
                    <span class="badge bg-light text-dark"><?php echo count($logs); ?> of
                        <?php echo number_format($total_records); ?> records</span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($logs) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 150px;">Timestamp</th>
                                        <th style="width: 100px;">User</th>
                                        <th style="width: 120px;">Action</th>
                                        <th style="width: 150px;">Target</th>
                                        <th>Details</th>
                                        <th style="width: 120px;">IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log):
                                        // Determine row class based on action
                                        $row_class = '';
                                        $action_badge = 'secondary';
                                        $action_icon = '📝';

                                        if (strpos($log['action'], 'LOGIN') !== false) {
                                            $action_badge = 'success';
                                            $action_icon = '🔓';
                                        } elseif (strpos($log['action'], 'LOGOUT') !== false) {
                                            $action_badge = 'info';
                                            $action_icon = '🔒';
                                        } elseif (strpos($log['action'], 'DELETE') !== false) {
                                            $action_badge = 'danger';
                                            $action_icon = '🗑️';
                                            $row_class = 'table-danger';
                                        } elseif (strpos($log['action'], 'ADD') !== false || strpos($log['action'], 'CREATE') !== false) {
                                            $action_badge = 'success';
                                            $action_icon = '➕';
                                        } elseif (strpos($log['action'], 'UPDATE') !== false) {
                                            $action_badge = 'warning';
                                            $action_icon = '✏️';
                                        } elseif (strpos($log['action'], 'FAILED') !== false) {
                                            $action_badge = 'danger';
                                            $action_icon = '❌';
                                            $row_class = 'table-warning';
                                        }
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></td>
                                            <td>
                                                <?php if ($log['user_id'] == 0): ?>
                                                    <span class="badge bg-secondary">System</span>
                                                <?php else: ?>
                                                    <strong><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></strong>
                                                    <br><span
                                                        class="text-muted"><?php echo htmlspecialchars($log['role'] ?? ''); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $action_badge; ?>">
                                                    <?php echo $action_icon; ?>         <?php echo htmlspecialchars($log['action']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['target'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($log['details'] ?? '-'); ?></td>
                                            <td><span
                                                    class="text-muted"><?php echo htmlspecialchars($log['ip_address'] ?? 'Unknown'); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-light">
                                <nav aria-label="Audit log pagination">
                                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link"
                                                href="?page=<?php echo max(1, $current_page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_action !== 'all' ? '&action=' . urlencode($filter_action) : ''; ?><?php echo $filter_user !== 'all' ? '&user=' . $filter_user : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>">
                                                <span aria-hidden="true">&laquo;</span> Previous
                                            </a>
                                        </li>

                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <?php if ($i == 1 || $i == $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)): ?>
                                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                                    <a class="page-link"
                                                        href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_action !== 'all' ? '&action=' . urlencode($filter_action) : ''; ?><?php echo $filter_user !== 'all' ? '&user=' . $filter_user : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php elseif ($i == $current_page - 3 || $i == $current_page + 3): ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                            <a class="page-link"
                                                href="?page=<?php echo min($total_pages, $current_page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_action !== 'all' ? '&action=' . urlencode($filter_action) : ''; ?><?php echo $filter_user !== 'all' ? '&user=' . $filter_user : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>">
                                                Next <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <h5 class="text-muted">No audit logs found</h5>
                            <p class="text-muted mb-3">
                                <?php echo (!empty($search) || $filter_action !== 'all' || $filter_user !== 'all' || !empty($date_from) || !empty($date_to)) ? 'Try adjusting your filters.' : 'System activity will be logged here.'; ?>
                            </p>
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