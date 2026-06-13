<?php
/**
 * Invoices Listing Page
 * Displays all invoices with filtering and search capabilities
 */

// Include authentication
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// Set page title
$page_title = 'Invoice Management';

// Pagination settings
$records_per_page = 15;
// Fix: Enforce positive integer to prevent SQL injection via negative page numbers
$current_page = (int) max(1, isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1);
$offset = ($current_page - 1) * $records_per_page;

// Filter by status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$allowed_statuses = ['all', 'paid', 'unpaid', 'overdue'];
if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = 'all';
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_query = '';
$search_params = [];

$status_query = '';
$status_params = [];

// Initialize default values
$total_records = 0;
$total_pages = 0;
$invoices = [];
$stats = [
    'paid_count' => 0,
    'unpaid_count' => 0,
    'overdue_count' => 0,
    'paid_amount' => 0,
    'unpaid_amount' => 0
];

if (!empty($search)) {
    $search_query = " AND (i.invoice_no LIKE ? OR p.property_name LIKE ? OR c.name LIKE ?)";
    $search_param = "%{$search}%";
    $search_params = [$search_param, $search_param, $search_param];
}

// Handle status filter (including computed 'overdue' status)
if ($status_filter !== 'all') {
    if ($status_filter === 'overdue') {
        // Overdue is computed: unpaid AND past due date
        $status_query = " AND i.status = 'unpaid' AND i.due_date < CURDATE()";
        $status_params = [];
    } else {
        // Regular status filter
        $status_query = " AND i.status = ?";
        $status_params = [$status_filter];
    }
}

try {
    // Get total number of invoices
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM invoices i
        LEFT JOIN payment_schedules ps ON i.schedule_id = ps.schedule_id
        LEFT JOIN properties p ON i.property_id = p.property_id OR ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        WHERE 1=1 {$search_query} {$status_query}
    ");
    $count_stmt->execute(array_merge($search_params, $status_params));
    $total_records = (int) $count_stmt->fetch()['total'];
    $total_pages = (int) ceil($total_records / $records_per_page);

    // Fetch invoices with property and client info
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            p.property_name,
            p.property_id,
            c.name as client_name,
            c.client_id,
            ps.due_date as schedule_due_date,
            ps.amount_due as schedule_amount,
            CASE 
                WHEN i.status = 'unpaid' AND i.due_date < CURDATE() THEN 'overdue'
                ELSE i.status 
            END as computed_status
        FROM invoices i
        LEFT JOIN payment_schedules ps ON i.schedule_id = ps.schedule_id
        LEFT JOIN properties p ON i.property_id = p.property_id OR ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        WHERE 1=1 {$search_query} {$status_query}
        ORDER BY 
            CASE 
                WHEN i.status = 'unpaid' AND i.due_date < CURDATE() THEN 1
                WHEN i.status = 'unpaid' THEN 2
                WHEN i.status = 'paid' THEN 3
            END,
            i.invoice_date DESC
        LIMIT ? OFFSET ?
    ");

    $params = array_merge($search_params, $status_params, [$records_per_page, $offset]);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();

    // Get dashboard statistics
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN status = 'unpaid' AND due_date >= CURDATE() THEN 1 END) as unpaid_count,
            COUNT(CASE WHEN status = 'unpaid' AND due_date < CURDATE() THEN 1 END) as overdue_count,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
            COALESCE(SUM(CASE WHEN status = 'unpaid' THEN total_amount ELSE 0 END), 0) as unpaid_amount
        FROM invoices
    ");
    $stats = $stats_stmt->fetch();

} catch (PDOException $e) {
    error_log("Invoices listing error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again later.";
    // Default values already initialized above
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
            <div class="row">
                <div class="col-12">
                    <!-- Page Header -->
                    <div class="page-header mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h2 class="mb-0"><span>📄</span> Invoice Management</h2>
                                <p class="text-muted mb-0">Manage and track all invoices</p>
                            </div>
                            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                                <a href="invoice_create.php" class="btn btn-primary">
                                    <span>➕</span> Create New Invoice
                                </a>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['flash_message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show"
                            role="alert">
                            <?php
                            echo $_SESSION['flash_message'];
                            unset($_SESSION['flash_message']);
                            unset($_SESSION['flash_type']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card border-success">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1 small">Paid Invoices</p>
                                            <h4 class="mb-0 text-success"><?php echo $stats['paid_count']; ?></h4>
                                            <small
                                                class="text-success">₱<?php echo number_format($stats['paid_amount'], 2); ?></small>
                                        </div>
                                        <div class="fs-1 text-success opacity-25">✓</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1 small">Unpaid Invoices</p>
                                            <h4 class="mb-0 text-warning"><?php echo $stats['unpaid_count']; ?></h4>
                                            <small
                                                class="text-warning">₱<?php echo number_format($stats['unpaid_amount'], 2); ?></small>
                                        </div>
                                        <div class="fs-1 text-warning opacity-25">⏳</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-danger">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1 small">Overdue Invoices</p>
                                            <h4 class="mb-0 text-danger"><?php echo $stats['overdue_count']; ?></h4>
                                            <small class="text-muted">Past Due Date</small>
                                        </div>
                                        <div class="fs-1 text-danger opacity-25">⚠</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-primary">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1 small">Total Invoices</p>
                                            <h4 class="mb-0 text-primary"><?php echo $total_records; ?></h4>
                                            <small class="text-muted">All Time</small>
                                        </div>
                                        <div class="fs-1 text-primary opacity-25">📄</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters and Search -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="GET" action="invoices.php" class="row g-3 align-items-center">
                                <div class="col-md-3">
                                    <label class="form-label mb-1">Filter by Status:</label>
                                    <select name="status" class="form-select" onchange="this.form.submit()">
                                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All
                                            Invoices</option>
                                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>
                                            Paid Only</option>
                                        <option value="unpaid" <?php echo $status_filter === 'unpaid' ? 'selected' : ''; ?>>Unpaid Only</option>
                                        <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue Only</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1">Search:</label>
                                    <input type="text" name="search" class="form-control"
                                        placeholder="🔍 Search by invoice number, property, or client..."
                                        value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1">&nbsp;</label>
                                    <div class="d-grid d-md-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-fill">
                                            <span>🔍</span> Search
                                        </button>
                                        <?php if (!empty($search) || $status_filter !== 'all'): ?>
                                            <a href="invoices.php" class="btn btn-outline-secondary flex-fill">
                                                <span>✖</span> Clear
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Invoices Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><span>📄</span> Invoices</span>
                            <span class="badge bg-light text-dark"><?php echo count($invoices); ?> of
                                <?php echo $total_records; ?> invoices</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($invoices) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 120px;">Invoice No.</th>
                                                <th>Property</th>
                                                <th>Client</th>
                                                <th style="width: 110px;">Invoice Date</th>
                                                <th style="width: 110px;">Due Date</th>
                                                <th style="width: 130px;" class="text-end">Amount</th>
                                                <th style="width: 100px;">Status</th>
                                                <th style="width: 150px;" class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($invoices as $invoice): ?>
                                                <?php
                                                $status_badge = 'secondary';
                                                $status_icon = '⚪';
                                                $status_label = ucfirst($invoice['status']);
                                                $row_class = '';

                                                if ($invoice['computed_status'] === 'overdue') {
                                                    $status_badge = 'danger';
                                                    $status_icon = '🔴';
                                                    $status_label = 'Overdue';
                                                    $row_class = 'table-danger';
                                                } elseif ($invoice['status'] === 'unpaid') {
                                                    $status_badge = 'warning';
                                                    $status_icon = '🟡';
                                                } elseif ($invoice['status'] === 'paid') {
                                                    $status_badge = 'success';
                                                    $status_icon = '🟢';
                                                }
                                                ?>
                                                <tr class="<?php echo $row_class; ?>">
                                                    <td><strong><?php echo htmlspecialchars($invoice['invoice_no']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <a href="property_edit.php?id=<?php echo $invoice['property_id']; ?>"
                                                            class="text-decoration-none">
                                                            <?php echo htmlspecialchars($invoice['property_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <a href="client_edit.php?id=<?php echo $invoice['client_id']; ?>"
                                                            class="text-decoration-none">
                                                            <?php echo htmlspecialchars($invoice['client_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <small><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <small
                                                            class="<?php echo $invoice['computed_status'] === 'overdue' ? 'text-danger fw-bold' : ''; ?>">
                                                            <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?>
                                                        </small>
                                                    </td>
                                                    <td class="text-end">
                                                        <strong>₱<?php echo number_format($invoice['total_amount'], 2); ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $status_badge; ?>">
                                                            <?php echo $status_icon; ?>         <?php echo $status_label; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="invoice_view.php?id=<?php echo $invoice['invoice_id']; ?>"
                                                                class="btn btn-primary" title="View Invoice"
                                                                data-bs-toggle="tooltip">
                                                                👁️ View
                                                            </a>
                                                            <a href="invoice_view.php?id=<?php echo $invoice['invoice_id']; ?>&print=1"
                                                                class="btn btn-outline-secondary" title="Print Invoice"
                                                                data-bs-toggle="tooltip" target="_blank">
                                                                🖨️ Print
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
                                        <nav aria-label="Invoice pagination">
                                            <ul class="pagination pagination-sm mb-0 justify-content-center">
                                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link"
                                                        href="?page=<?php echo max(((int) $current_page - 1), 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter !== 'all' ? '&status=' . $status_filter : ''; ?>"
                                                        tabindex="-1">
                                                        <span aria-hidden="true">&laquo;</span> Previous
                                                    </a>
                                                </li>

                                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                    <?php if ($i == 1 || $i == $total_pages || ($i >= ((int) $current_page - 2) && $i <= ((int) $current_page + 2))): ?>
                                                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                                            <a class="page-link"
                                                                href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter !== 'all' ? '&status=' . $status_filter : ''; ?>"><?php echo $i; ?></a>
                                                        </li>
                                                    <?php elseif ($i == ((int) $current_page - 3) || $i == ((int) $current_page + 3)): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">...</span>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endfor; ?>

                                                <li
                                                    class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link"
                                                        href="?page=<?php echo min(((int) $current_page + 1), $total_pages); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter !== 'all' ? '&status=' . $status_filter : ''; ?>">
                                                        Next <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">📄</div>
                                    <h5 class="text-muted">
                                        <?php echo !empty($search) || $status_filter !== 'all' ? 'No invoices found matching your filters.' : 'No invoices found.'; ?>
                                    </h5>
                                    <p class="text-muted mb-3">
                                        <?php echo !empty($search) || $status_filter !== 'all' ? 'Try adjusting your search or filter criteria.' : 'Create your first invoice to get started.'; ?>
                                    </p>
                                    <?php if (empty($search) && $status_filter === 'all'): ?>
                                        <a href="invoice_create.php" class="btn btn-primary">
                                            <span>➕</span> Create New Invoice
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php
    // Include footer
    include '../templates/footer.php';
    ?>