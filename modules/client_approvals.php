<?php
/**
 * Client Approval Management
 * Real Estate Receivable System
 * 
 * Admin interface to review and approve/reject self-registered client accounts
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

// Require login and admin/finance access
require_module_access('clients');

// Set page title
$page_title = 'Client Approvals';

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$allowed_filters = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'pending';
}

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get highlight client (from inquiry redirect)
$highlight_client_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : 0;

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

try {
    // Build WHERE clause
    $where_clauses = [];
    $params = [];

    // Filter by status
    if ($filter !== 'all') {
        $where_clauses[] = "c.account_status = ?";
        $params[] = $filter;
    }

    // Search filter
    if (!empty($search)) {
        $where_clauses[] = "(c.name LIKE ? OR c.email LIKE ? OR c.contact_no LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM clients c $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);

    // Get clients with approval details
    $sql = "
        SELECT 
            c.*,
            u.username,
            u.user_id,
            approver.username as approved_by_username,
            (SELECT COUNT(*) FROM inquiries WHERE client_id = c.client_id) as inquiry_count
        FROM clients c
        LEFT JOIN users u ON c.client_id = u.client_id AND u.role = 'client'
        LEFT JOIN users approver ON c.approved_by = approver.user_id
        $where_sql
        ORDER BY 
            CASE 
                WHEN c.account_status = 'pending' THEN 1
                WHEN c.account_status = 'approved' THEN 2
                WHEN c.account_status = 'rejected' THEN 3
            END,
            c.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $per_page;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();

    // Get status counts for badges
    $status_counts = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'all' => 0
    ];

    $count_sql = "SELECT account_status, COUNT(*) as count FROM clients GROUP BY account_status";
    $count_stmt = $pdo->query($count_sql);
    while ($row = $count_stmt->fetch()) {
        $status_counts[$row['account_status']] = $row['count'];
        $status_counts['all'] += $row['count'];
    }

} catch (PDOException $e) {
    error_log("Client approvals error: " . $e->getMessage());
    $clients = [];
    $total_pages = 0;
    $status_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];
}

// Include header
include '../templates/header.php';
?>

<style>
/* Fix nav-pills text visibility */
.nav-pills .nav-link {
    color: #495057; /* Dark text for inactive tabs */
    font-weight: 500;
}

.nav-pills .nav-link.active {
    color: #fff; /* White text for active tab */
    background-color: #0d6efd; /* Bootstrap primary blue */
}

.nav-pills .nav-link:hover:not(.active) {
    color: #0d6efd;
    background-color: #e7f1ff;
}
</style>

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
                            <span style="color: var(--primary-blue);">✅</span> Client Registrations
                        </h2>
                        <p class="text-muted mb-0">Review property acquisition requests from self-registered clients</p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <a href="client_add.php" class="btn btn-primary">
                            <span>➕</span> Add New Client (Pre-Approved)
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

            <!-- Filter Tabs -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <ul class="nav nav-pills">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'pending' ? 'active' : ''; ?>" 
                                       href="?filter=pending">
                                        ⏳ Pending 
                                        <?php if ($status_counts['pending'] > 0): ?>
                                            <span class="badge bg-warning text-dark"><?php echo $status_counts['pending']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'approved' ? 'active' : ''; ?>" 
                                       href="?filter=approved">
                                        ✅ Approved
                                        <?php if ($status_counts['approved'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $status_counts['approved']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'rejected' ? 'active' : ''; ?>" 
                                       href="?filter=rejected">
                                        ❌ Rejected
                                        <?php if ($status_counts['rejected'] > 0): ?>
                                            <span class="badge bg-danger"><?php echo $status_counts['rejected']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                                       href="?filter=all">
                                        📋 All
                                        <span class="badge bg-secondary"><?php echo $status_counts['all']; ?></span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <form method="GET" class="d-flex">
                                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search clients..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary ms-2">
                                    🔍
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clients Table -->
            <?php if (count($clients) > 0): ?>
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Status</th>
                                        <th>Client Details</th>
                                        <th>Contact Info</th>
                                        <th>Account Info</th>
                                        <th>Registered</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client):
                                        // Determine status badge
                                        $status_badge = 'warning';
                                        $status_icon = '⏳';
                                        $status_text = 'Pending Review';
                                        
                                        if ($client['account_status'] === 'approved') {
                                            $status_badge = 'success';
                                            $status_icon = '✅';
                                            $status_text = 'Approved';
                                        } elseif ($client['account_status'] === 'rejected') {
                                            $status_badge = 'danger';
                                            $status_icon = '❌';
                                            $status_text = 'Rejected';
                                        }
                                        
                                        // Highlight row if this is the target client
                                        $highlight_class = (isset($highlight_client_id) && $highlight_client_id > 0 && $highlight_client_id == $client['client_id']) ? 'table-warning' : '';
                                        ?>
                                        <tr class="<?php echo $highlight_class; ?>" id="client-<?php echo $client['client_id']; ?>">
                                            <td>
                                                <span class="badge bg-<?php echo $status_badge; ?>">
                                                    <?php echo $status_icon; ?> <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($client['name']); ?></strong><br>
                                                <small class="text-muted">ID: #<?php echo $client['client_id']; ?></small>
                                                <?php if ($client['inquiry_count'] > 0): ?>
                                                    <br><span class="badge bg-info text-white">
                                                        📋 Property Interest Inquiry
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div>📧 <?php echo htmlspecialchars($client['email'] ?: 'N/A'); ?></div>
                                                <div>📞 <?php echo htmlspecialchars($client['contact_no'] ?: 'N/A'); ?></div>
                                            </td>
                                            <td>
                                                <?php if ($client['username']): ?>
                                                    <div>👤 <code><?php echo htmlspecialchars($client['username']); ?></code></div>
                                                    <div><small class="text-success">✓ User account created</small></div>
                                                <?php else: ?>
                                                    <div><small class="text-muted">No user account</small></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?php echo date('M d, Y', strtotime($client['created_at'])); ?></div>
                                                <small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($client['created_at'])); ?>
                                                </small>
                                                <?php if ($client['account_status'] === 'approved' && $client['approved_at']): ?>
                                                    <br><small class="text-success">
                                                        Approved: <?php echo date('M d, Y', strtotime($client['approved_at'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group">
                                                    <a href="client_dashboard.php?id=<?php echo $client['client_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="View Details">
                                                        👁️
                                                    </a>
                                                    
                                                    <?php if ($client['account_status'] === 'pending'): ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-success" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#approveModal"
                                                                data-client-id="<?php echo $client['client_id']; ?>"
                                                                data-client-name="<?php echo htmlspecialchars($client['name']); ?>"
                                                                title="Approve">
                                                            ✅ Approve
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-danger" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#rejectModal"
                                                                data-client-id="<?php echo $client['client_id']; ?>"
                                                                data-client-name="<?php echo htmlspecialchars($client['name']); ?>"
                                                                title="Reject">
                                                            ❌ Reject
                                                        </button>
                                                    <?php elseif ($client['account_status'] === 'rejected'): ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-success" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#approveModal"
                                                                data-client-id="<?php echo $client['client_id']; ?>"
                                                                data-client-name="<?php echo htmlspecialchars($client['name']); ?>"
                                                                title="Re-approve">
                                                            ✅ Re-approve
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="?filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">📋</div>
                        <h4 class="text-muted">No <?php echo $filter !== 'all' ? ucfirst($filter) : ''; ?> Clients Found</h4>
                        <?php if (!empty($search)): ?>
                            <p class="text-muted">Try adjusting your search criteria.</p>
                            <a href="?filter=<?php echo $filter; ?>" class="btn btn-primary">Clear Search</a>
                        <?php elseif ($filter === 'pending'): ?>
                            <p class="text-muted">No pending registrations to review at the moment.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="client_approval_handler.php">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">✅ Approve Client Registration</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="client_id" id="approveClientId">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        
                        <p>Are you sure you want to approve the registration for:</p>
                        <p class="text-center">
                            <strong id="approveClientName" style="font-size: 1.2rem;"></strong>
                        </p>
                        
                        <div class="alert alert-info">
                            <strong>ℹ️ What happens next:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Client account status will be set to "Approved"</li>
                                <li>Client can login to their portal</li>
                                <li>You can assign/sell the property they're interested in</li>
                                <li>Payment schedules can be generated</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">✅ Approve Registration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="client_approval_handler.php">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">❌ Reject Client Registration</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="client_id" id="rejectClientId">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        
                        <p>Are you sure you want to reject the registration for:</p>
                        <p class="text-center">
                            <strong id="rejectClientName" style="font-size: 1.2rem;"></strong>
                        </p>
                        
                        <div class="mb-3">
                            <label for="rejectionReason" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejectionReason" name="rejection_reason" rows="3" 
                                      placeholder="Explain why this registration is being rejected..." required></textarea>
                            <small class="text-muted">This reason will be logged for audit purposes.</small>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>⚠️ Warning:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Client will not be able to login</li>
                                <li>User account will remain but be inactive</li>
                                <li>You can re-approve this client later if needed</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">❌ Reject Registration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Handle approve modal
        document.getElementById('approveModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const clientId = button.getAttribute('data-client-id');
            const clientName = button.getAttribute('data-client-name');
            
            document.getElementById('approveClientId').value = clientId;
            document.getElementById('approveClientName').textContent = clientName;
        });

        // Handle reject modal
        document.getElementById('rejectModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const clientId = button.getAttribute('data-client-id');
            const clientName = button.getAttribute('data-client-name');
            
            document.getElementById('rejectClientId').value = clientId;
            document.getElementById('rejectClientName').textContent = clientName;
        });
        
        // Scroll to highlighted client if present
        <?php if ($highlight_client_id > 0): ?>
        const highlightedRow = document.getElementById('client-<?php echo $highlight_client_id; ?>');
        if (highlightedRow) {
            highlightedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Flash animation
            highlightedRow.style.animation = 'highlight-pulse 2s ease-in-out';
        }
        <?php endif; ?>
    </script>
    
    <style>
    @keyframes highlight-pulse {
        0%, 100% { background-color: inherit; }
        50% { background-color: #fff3cd; }
    }
    </style>

    <?php
    // Include footer
    include '../templates/footer.php';
    ?>
