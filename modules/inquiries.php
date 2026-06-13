<?php
/**
 * Inquiry Management Module
 * Real Estate Receivable System
 * 
 * Lists property inquiries with status filtering
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include options
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Require admin or finance role
require_role(['admin', 'finance']);

$page_title = 'Inquiry Management';

// Handle Delete Action
if (isset($_POST['delete_inquiry']) && is_admin()) {
    $inquiry_id = filter_input(INPUT_POST, 'inquiry_id', FILTER_VALIDATE_INT);
    if ($inquiry_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM inquiries WHERE inquiry_id = ?");
            $stmt->execute([$inquiry_id]);
            set_flash_message('success', 'Inquiry deleted successfully.');
        } catch (PDOException $e) {
            set_flash_message('error', 'Error deleting inquiry: ' . $e->getMessage());
        }
    }
    header('Location: inquiries.php');
    exit;
}

// Get filter status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build Query with client account status
$sql = "
    SELECT 
        i.*,
        p.property_name,
        p.status as property_status,
        c.name as client_name,
        c.account_status
    FROM inquiries i
    JOIN properties p ON i.property_id = p.property_id
    LEFT JOIN clients c ON i.client_id = c.client_id
";

$params = [];
if ($status_filter !== 'all') {
    $sql .= " WHERE i.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY i.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inquiries = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Inquiry list error: " . $e->getMessage());
    $inquiries = [];
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4 align-items-center">
                <div class="col-12">
                    <h2 style="color: var(--slate-gray); font-weight: 700;">
                        <span>📧</span> Inquiry Management
                    </h2>
                    <p class="text-muted">View and process property inquiries from guests and clients.</p>
                </div>
            </div>

            <?php
            // Flash Messages
            $flash = get_flash_message();
            if ($flash): ?>
                <div
                    class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-auto">
                            <label class="form-label fw-bold mb-0 me-2">Filter Status:</label>
                        </div>
                        <div class="col-auto">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All
                                    Inquiries</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>
                                    Pending</option>
                                <option value="contacted" <?php echo $status_filter === 'contacted' ? 'selected' : ''; ?>>
                                    Contacted</option>
                                <option value="converted" <?php echo $status_filter === 'converted' ? 'selected' : ''; ?>>
                                    Converted to Client</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed
                                </option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Inquiries Table -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle w-100">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Property</th>
                                    <th>Inquirer</th>
                                    <th>Contact Info</th>
                                    <th>Message</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($inquiries)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="text-muted">
                                                <div style="font-size: 3rem;">📭</div>
                                                <p>No inquiries found.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($inquiries as $row): ?>
                                        <tr>
                                            <td>
                                                <small class="fw-bold">
                                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                                </small>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                $badges = [
                                                    'pending' => 'bg-warning text-dark',
                                                    'contacted' => 'bg-info text-white',
                                                    'converted' => 'bg-success',
                                                    'closed' => 'bg-secondary'
                                                ];
                                                $badge_class = $badges[$row['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge rounded-pill <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($row['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../catalog.php?search=<?php echo urlencode($row['property_name']); ?>"
                                                    target="_blank" class="text-decoration-none fw-bold">
                                                    <?php echo htmlspecialchars($row['property_name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['name']); ?>
                                                <?php if ($row['client_id']): ?>
                                                    <?php if ($row['account_status'] === 'approved'): ?>
                                                        <span class="badge bg-success ms-1" title="Registered & Approved Client">✓ Approved</span>
                                                    <?php elseif ($row['account_status'] === 'pending'): ?>
                                                        <span class="badge bg-warning ms-1" title="Awaiting Approval">⏳ Pending</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary ms-1" title="Existing Client">Client</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark border ms-1">Guest</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><small>📧
                                                        <?php echo htmlspecialchars($row['email']); ?>
                                                    </small></div>
                                                <div><small>📱
                                                        <?php echo htmlspecialchars($row['contact_no']); ?>
                                                    </small></div>
                                            </td>
                                            <td>
                                                <span title="<?php echo htmlspecialchars($row['message']); ?>">
                                                    <?php echo htmlspecialchars(substr($row['message'], 0, 50)) . (strlen($row['message']) > 50 ? '...' : ''); ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group">
                                                    <?php if ($row['status'] === 'pending' && $row['client_id'] && $row['account_status'] === 'approved' && $row['property_status'] === 'available'): ?>
                                                        <!-- Fast-track: Direct to sell -->
                                                        <a href="property_sell.php?property_id=<?php echo $row['property_id']; ?>&client_id=<?php echo $row['client_id']; ?>&inquiry_id=<?php echo $row['inquiry_id']; ?>" 
                                                           class="btn btn-sm btn-success" title="Ready to Sell">
                                                            💰 Sell Now
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="inquiry_process.php?id=<?php echo $row['inquiry_id']; ?>"
                                                        class="btn btn-sm btn-outline-primary">
                                                        Process
                                                    </a>
                                                    <?php if (is_admin()): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                            onclick="if(confirm('Are you sure you want to delete this inquiry?')) { document.getElementById('delete-form-<?php echo $row['inquiry_id']; ?>').submit(); }">
                                                            🗑️
                                                        </button>
                                                        <form id="delete-form-<?php echo $row['inquiry_id']; ?>" method="POST"
                                                            style="display: none;">
                                                            <input type="hidden" name="inquiry_id"
                                                                value="<?php echo $row['inquiry_id']; ?>">
                                                            <input type="hidden" name="delete_inquiry" value="1">
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
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

    <?php include '../templates/footer.php'; ?>