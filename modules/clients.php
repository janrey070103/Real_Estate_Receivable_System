<?php
/**
 * Clients Management - Listing Page
 * Real Estate Receivable System
 * 
 * Displays all clients with search, pagination, and action buttons
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
$page_title = 'Client Management';

// Handle client deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];

    try {
        // Check if client has properties
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM properties WHERE client_id = ?");
        $stmt->execute([$delete_id]);
        $property_count = $stmt->fetch()['count'];

        if ($property_count > 0) {
            set_flash_message('warning', "Cannot delete client. They have {$property_count} associated property/properties. Delete properties first.");
        } else {
            // Delete client
            $stmt = $pdo->prepare("DELETE FROM clients WHERE client_id = ?");
            $stmt->execute([$delete_id]);

            // Log client deletion
            log_audit($pdo, 'DELETE_CLIENT', 'client_id:' . $delete_id, 'Deleted client (had no properties)');

            set_flash_message('success', 'Client deleted successfully!');
        }
    } catch (PDOException $e) {
        error_log("Delete client error: " . $e->getMessage());
        set_flash_message('error', 'Failed to delete client. Please try again.');
    }

    header('Location: clients.php');
    exit();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $client_id = (int) ($_POST['client_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($client_id > 0 && !empty($new_password)) {
        if ($new_password !== $confirm_password) {
            set_flash_message('error', 'Passwords do not match!');
        } elseif (strlen($new_password) < 6) {
            set_flash_message('error', 'Password must be at least 6 characters long!');
        } else {
            try {
                // Get client name for logging
                $stmt = $pdo->prepare("SELECT name FROM clients WHERE client_id = ?");
                $stmt->execute([$client_id]);
                $client = $stmt->fetch();

                if (!$client) {
                    set_flash_message('error', 'Client not found.');
                } else {
                    // Hash and update password in USERS table (not clients table)
                    $hashed_password = hash_password($new_password);
                    
                    // Update password in users table where client_id matches
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE client_id = ? AND role = 'client'");
                    $stmt->execute([$hashed_password, $client_id]);
                    
                    $rows_affected = $stmt->rowCount();
                    
                    if ($rows_affected > 0) {
                        // Log the action
                        log_audit($pdo, 'CHANGE_CLIENT_PASSWORD', 'client_id:' . $client_id, 'Admin changed password for client: ' . $client['name']);
                        
                        set_flash_message('success', 'Password changed successfully for ' . htmlspecialchars($client['name']) . '!');
                    } else {
                        // No user account found - client might not have login credentials yet
                        set_flash_message('warning', 'No user account found for this client. Client may not have registered yet.');
                    }
                }
            } catch (PDOException $e) {
                error_log("Change password error: " . $e->getMessage());
                set_flash_message('error', 'Failed to change password. Please try again.');
            }
        }
    } else {
        set_flash_message('error', 'Invalid request. Please try again.');
    }

    header('Location: clients.php');
    exit();
}

// Pagination settings
$records_per_page = 10;
// Fix: Enforce positive integer to prevent SQL injection via negative page numbers
$current_page = max(1, isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1);
$offset = ($current_page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_query = '';
$search_params = [];

if (!empty($search)) {
    $search_query = " WHERE name LIKE ? OR email LIKE ? OR contact_no LIKE ?";
    $search_param = "%{$search}%";
    $search_params = [$search_param, $search_param, $search_param];
}

try {
    // Get total number of clients
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM clients" . $search_query);
    $count_stmt->execute($search_params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);

    // Fetch clients with statistics and account status
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            COUNT(DISTINCT p.property_id) as property_count,
            COALESCE(SUM(p.total_price), 0) as total_value,
            COUNT(DISTINCT CASE WHEN ps.status = 'overdue' THEN ps.schedule_id END) as overdue_count,
            COUNT(DISTINCT CASE WHEN ps.status = 'pending' THEN ps.schedule_id END) as pending_count,
            COALESCE(SUM(CASE WHEN ps.status IN ('pending', 'overdue') THEN ps.amount_due ELSE 0 END), 0) as outstanding_balance,
            MIN(CASE WHEN ps.status IN ('pending', 'overdue') THEN ps.due_date END) as next_due_date
        FROM clients c
        LEFT JOIN properties p ON c.client_id = p.client_id
        LEFT JOIN payment_schedules ps ON p.property_id = ps.property_id
        {$search_query}
        GROUP BY c.client_id
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ");

    $params = array_merge($search_params, [$records_per_page, $offset]);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();

    // Get dashboard statistics
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT c.client_id) as total_clients,
            COUNT(DISTINCT p.property_id) as total_properties,
            COALESCE(SUM(p.total_price), 0) as total_contracts_value
        FROM clients c
        LEFT JOIN properties p ON c.client_id = p.client_id
    ");
    $stats = $stats_stmt->fetch();

} catch (PDOException $e) {
    error_log("Clients listing error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again later.";
    $clients = [];
    $stats = ['total_clients' => 0, 'total_properties' => 0, 'total_contracts_value' => 0];
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
                            <span style="color: var(--primary-blue);">👥</span> Client Management
                        </h2>
                        <p class="text-muted mb-0">Manage your real estate clients and their information</p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <a href="client_approvals.php" class="btn btn-warning me-2">
                            <span>⏳</span> Registration Requests
                        </a>
                        <a href="client_add.php" class="btn btn-primary">
                            <span>➕</span> Add New Client
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
                <div class="col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo number_format($stats['total_clients']); ?></h3>
                                <p>Total Clients</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">👥</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
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
                <div class="col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 style="font-size: 1.3rem;">
                                    ₱<?php echo number_format($stats['total_contracts_value'], 2); ?></h3>
                                <p>Total Contract Value</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">💰</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="search-form">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="clients.php" class="row g-3 align-items-center">
                            <div class="col-md-8 col-lg-9">
                                <input type="text" name="search" class="form-control"
                                    placeholder="🔍 Search by name, email, or contact number..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <div class="d-grid d-md-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-fill">
                                        <span>🔍</span> Search
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="clients.php" class="btn btn-outline-secondary flex-fill">
                                            <span>✖</span> Clear
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Clients Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><span>📋</span> Client List</span>
                    <span class="badge bg-light text-dark"><?php echo count($clients); ?> of
                        <?php echo $total_records; ?>
                        clients</span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($clients) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th>Name</th>
                                        <th style="width: 140px;" class="text-center">Account Status</th>
                                        <th style="width: 140px;" class="text-center">Payment Status</th>
                                        <th>Email</th>
                                        <th style="width: 140px;">Contact</th>
                                        <th style="width: 100px;" class="text-center">Properties</th>
                                        <th style="width: 150px;" class="text-end">Outstanding</th>
                                        <th style="width: 120px;">Registered</th>
                                        <th style="width: 180px;" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): 
                                        // Determine payment status based on overdue and pending schedules
                                        $payment_status_badge = 'success';
                                        $payment_status_icon = '🟢';
                                        $payment_status_text = 'Good Standing';
                                        
                                        if ($client['overdue_count'] > 0) {
                                            $payment_status_badge = 'danger';
                                            $payment_status_icon = '🔴';
                                            $payment_status_text = $client['overdue_count'] . ' Overdue';
                                        } elseif ($client['pending_count'] > 0) {
                                            $payment_status_badge = 'warning';
                                            $payment_status_icon = '🟡';
                                            $payment_status_text = $client['pending_count'] . ' Pending';
                                        } elseif ($client['property_count'] == 0) {
                                            $payment_status_badge = 'secondary';
                                            $payment_status_icon = '⚪';
                                            $payment_status_text = 'No Properties';
                                        }
                                        
                                        // Determine account approval status
                                        $account_badge = 'success';
                                        $account_icon = '✅';
                                        $account_text = 'Approved';
                                        
                                        if ($client['account_status'] === 'pending') {
                                            $account_badge = 'warning';
                                            $account_icon = '⏳';
                                            $account_text = 'Pending Review';
                                        } elseif ($client['account_status'] === 'rejected') {
                                            $account_badge = 'danger';
                                            $account_icon = '❌';
                                            $account_text = 'Rejected';
                                        }
                                    ?>
                                        <tr>
                                            <td><strong class="text-muted">#<?php echo $client['client_id']; ?></strong></td>
                                            <td>
                                                <strong class="text-dark"><?php echo htmlspecialchars($client['name']); ?></strong>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $account_badge; ?>">
                                                    <?php echo $account_icon; ?> <?php echo $account_text; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $payment_status_badge; ?>">
                                                    <?php echo $payment_status_icon; ?> <?php echo $payment_status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($client['email']): ?>
                                                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>"
                                                        class="text-decoration-none">
                                                        <?php echo htmlspecialchars($client['email']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($client['contact_no'] ?? 'N/A'); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary rounded-pill"><?php echo $client['property_count']; ?></span>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($client['outstanding_balance'] > 0): ?>
                                                <strong class="text-danger">₱<?php echo number_format($client['outstanding_balance'], 2); ?></strong>
                                                <?php else: ?>
                                                <span class="text-success">✔ Paid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small
                                                    class="text-muted"><?php echo date('M d, Y', strtotime($client['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="client_edit.php?id=<?php echo $client['client_id']; ?>"
                                                        class="btn btn-outline-primary" title="Edit Client"
                                                        data-bs-toggle="tooltip">
                                                        ✏️
                                                    </a>
                                                    <a href="client_properties.php?id=<?php echo $client['client_id']; ?>"
                                                        class="btn btn-outline-info" title="View Properties"
                                                        data-bs-toggle="tooltip">
                                                        🏘️
                                                    </a>
                                                    <a href="client_documents.php?id=<?php echo $client['client_id']; ?>"
                                                        class="btn btn-outline-secondary" title="Upload Document"
                                                        data-bs-toggle="tooltip">
                                                        📎
                                                    </a>
                                                    <button type="button" 
                                                        class="btn btn-outline-warning" 
                                                        title="Change Password"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#changePasswordModal"
                                                        data-client-id="<?php echo $client['client_id']; ?>"
                                                        data-client-name="<?php echo htmlspecialchars($client['name']); ?>">
                                                        🔑
                                                    </button>
                                                    <a href="clients.php?delete=<?php echo $client['client_id']; ?>"
                                                        class="btn btn-outline-danger" title="Delete Client"
                                                        data-bs-toggle="tooltip"
                                                        onclick="return confirm('Are you sure you want to delete this client? This action cannot be undone.');">
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
                                <nav aria-label="Client pagination">
                                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link"
                                                href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                                                tabindex="-1">
                                                <span aria-hidden="true">&laquo;</span> Previous
                                            </a>
                                        </li>

                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <?php if ($i == 1 || $i == $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)): ?>
                                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                                    <a class="page-link"
                                                        href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php elseif ($i == $current_page - 3 || $i == $current_page + 3): ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                            <a class="page-link"
                                                href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                                Next <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">👥</div>
                            <h5 class="text-muted">
                                <?php echo !empty($search) ? 'No clients found matching your search.' : 'No clients found.'; ?>
                            </h5>
                            <p class="text-muted mb-3">
                                <?php echo !empty($search) ? 'Try adjusting your search terms.' : 'Add your first client to get started!'; ?>
                            </p>
                            <?php if (empty($search)): ?>
                                <a href="client_add.php" class="btn btn-primary">➕ Add New Client</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /.container-fluid -->
    </div><!-- /.main-content -->

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="changePasswordModalLabel">🔑 Change Client Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="changePasswordForm">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="client_id" id="modal_client_id">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>👤 Client:</strong> <span id="modal_client_name"></span>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   required minlength="6" placeholder="Enter new password">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   required minlength="6" placeholder="Confirm new password">
                        </div>
                        <div class="alert alert-warning mb-0">
                            <small><strong>⚠️ Warning:</strong> The client will need to use this new password to login to their account.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">🔑 Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Populate change password modal with client data
        const changePasswordModal = document.getElementById('changePasswordModal');
        if (changePasswordModal) {
            changePasswordModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const clientId = button.getAttribute('data-client-id');
                const clientName = button.getAttribute('data-client-name');
                
                // Update modal content
                document.getElementById('modal_client_id').value = clientId;
                document.getElementById('modal_client_name').textContent = clientName;
                
                // Clear password fields
                document.getElementById('new_password').value = '';
                document.getElementById('confirm_password').value = '';
            });
        }

        // Validate passwords match before submit
        document.getElementById('changePasswordForm')?.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('❌ Passwords do not match! Please try again.');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('❌ Password must be at least 6 characters long!');
                return false;
            }
            
            return confirm('🔑 Are you sure you want to change the password for this client?');
        });
    </script>

    <?php
    // Include footer (closes main-wrapper)
    include '../templates/footer.php';
    ?>