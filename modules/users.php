<?php
/**
 * Users Management Module
 * Real Estate Receivable System
 * 
 * Admin-only page for managing system users
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// IMPORTANT: Require admin role for this page
require_role('admin');

// Set page title
$page_title = 'User Management';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'finance';

        if (!empty($username) && !empty($password)) {
            try {
                $hashed_password = hash_password($password);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $role]);

                // Log the action
                log_audit($pdo, 'ADD_USER', 'user:' . $username, 'Created new user with role: ' . $role);

                set_flash_message('success', 'User created successfully!');
            } catch (PDOException $e) {
                set_flash_message('error', 'Error creating user: ' . $e->getMessage());
            }
        }

        header('Location: users.php');
        exit();
    }

    if ($action === 'delete_user') {
        $user_id = (int) ($_POST['user_id'] ?? 0);

        // Prevent deleting own account
        if ($user_id != get_user_id() && $user_id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);

                // Log the action
                log_audit($pdo, 'DELETE_USER', 'user_id:' . $user_id, 'Deleted user: ' . $user['username']);

                set_flash_message('success', 'User deleted successfully!');
            } catch (PDOException $e) {
                set_flash_message('error', 'Error deleting user: ' . $e->getMessage());
            }
        }

        header('Location: users.php');
        exit();
    }
}

// Fetch all users
try {
    $stmt = $pdo->query("SELECT user_id, username, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $users = [];
}

// Include header
include '../templates/header.php';
?>

<!-- Include Navigation -->
<?php include '../templates/sidebar.php'; ?>

<!-- Main Content Wrapper -->
<div class="main-wrapper">
    <div class="main-content">
        <div class="container-fluid py-4">

            <!-- Page Header -->
            <div class="page-header mb-4">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2 class="mb-0"><span>👥</span> User Management</h2>
                        <p class="text-muted mb-0">Manage system users and roles (Admin Only)</p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <span>➕</span> Add New User
                        </button>
                    </div>
                </div>
            </div>

            <?php
            $flash = get_flash_message();
            if ($flash):
                $alert_class = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
                ?>
                <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <span>📋</span> System Users
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Created Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No users found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['user_id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                            <td>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <span class="badge bg-danger">👑 Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">💼 Finance</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($user['created_at'])); ?></td>
                                            <td class="text-center">
                                                <?php if ($user['user_id'] != get_user_id()): ?>
                                                    <form method="POST" class="d-inline"
                                                        onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <span>🗑️</span> Delete
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Current User</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">➕ Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="text-muted">Minimum 6 characters recommended</small>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="finance">Finance</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../templates/footer.php'; ?>