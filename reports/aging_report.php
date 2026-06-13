<?php
/**
 * Aging Report
 * Real Estate Receivable System
 * 
 * Groups overdue accounts by age (30, 60, 90+ days)
 * Includes CSV export functionality
 */

// Include authentication
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        // Fetch aging data
        $stmt = $pdo->query("
            SELECT 
                c.name as client_name,
                c.email,
                c.contact_no,
                p.property_name,
                ps.schedule_id,
                ps.due_date,
                ps.amount_due,
                COALESCE(SUM(pay.amount_paid), 0) as amount_paid,
                ps.amount_due - COALESCE(SUM(pay.amount_paid), 0) as balance,
                DATEDIFF(CURDATE(), ps.due_date) as days_overdue,
                CASE
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 1 AND 30 THEN '1-30 days'
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 31 AND 60 THEN '31-60 days'
                    WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 61 AND 90 THEN '61-90 days'
                    WHEN DATEDIFF(CURDATE(), ps.due_date) > 90 THEN '90+ days'
                    ELSE 'Current'
                END as aging_bucket
            FROM payment_schedules ps
            INNER JOIN properties p ON ps.property_id = p.property_id
            LEFT JOIN clients c ON p.client_id = c.client_id
            LEFT JOIN payments pay ON ps.schedule_id = pay.schedule_id
            WHERE ps.status IN ('pending', 'overdue')
            AND ps.due_date < CURDATE()
            GROUP BY ps.schedule_id
            ORDER BY days_overdue DESC, c.name ASC
        ");

        $data = $stmt->fetchAll();

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=aging_report_' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Write BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write header row
        fputcsv($output, [
            'Client Name',
            'Email',
            'Contact',
            'Property',
            'Schedule ID',
            'Due Date',
            'Amount Due',
            'Amount Paid',
            'Balance',
            'Days Overdue',
            'Aging Bucket'
        ]);

        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, [
                $row['client_name'],
                $row['email'],
                $row['contact_no'],
                $row['property_name'],
                $row['schedule_id'],
                $row['due_date'],
                number_format($row['amount_due'], 2),
                number_format($row['amount_paid'], 2),
                number_format($row['balance'], 2),
                $row['days_overdue'],
                $row['aging_bucket']
            ]);
        }

        fclose($output);
        exit();

    } catch (PDOException $e) {
        error_log("Aging report export error: " . $e->getMessage());
        set_flash_message('error', 'Failed to export report.');
        header('Location: aging_report.php');
        exit();
    }
}

// Set page title
$page_title = 'Aging Report';

// Pagination settings
$records_per_page = 15;
// Fix: Enforce positive integer to prevent SQL injection via negative page numbers
$current_page = (int) max(1, isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1);
$offset = ($current_page - 1) * $records_per_page;

// Fetch aging report data
try {
    // Summary by aging bucket (NOT PAGINATED - shows all data)
    $stmt = $pdo->query("
        SELECT 
            CASE
                WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 1 AND 30 THEN '1-30 days'
                WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 31 AND 60 THEN '31-60 days'
                WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 61 AND 90 THEN '61-90 days'
                WHEN DATEDIFF(CURDATE(), ps.due_date) > 90 THEN '90+ days'
                ELSE 'Current'
            END as aging_bucket,
            COUNT(*) as count,
            SUM(ps.amount_due - COALESCE((
                SELECT SUM(amount_paid) 
                FROM payments 
                WHERE schedule_id = ps.schedule_id
            ), 0)) as total_balance
        FROM payment_schedules ps
        WHERE ps.status IN ('pending', 'overdue')
        AND ps.due_date < CURDATE()
        GROUP BY aging_bucket
        ORDER BY 
            CASE aging_bucket
                WHEN '1-30 days' THEN 1
                WHEN '31-60 days' THEN 2
                WHEN '61-90 days' THEN 3
                WHEN '90+ days' THEN 4
                ELSE 5
            END
    ");
    $summary = $stmt->fetchAll();

    // Get total count for pagination
    $count_stmt = $pdo->query("
        SELECT COUNT(DISTINCT ps.schedule_id) as total
        FROM payment_schedules ps
        WHERE ps.status IN ('pending', 'overdue')
        AND ps.due_date < CURDATE()
    ");
    $total_records = (int) $count_stmt->fetch()['total'];
    $total_pages = (int) ceil($total_records / $records_per_page);

    // Detailed aging data (PAGINATED)
    $stmt = $pdo->query("
        SELECT 
            c.client_id,
            c.name as client_name,
            c.email,
            c.contact_no,
            p.property_name,
            ps.schedule_id,
            ps.due_date,
            ps.amount_due,
            COALESCE(SUM(pay.amount_paid), 0) as amount_paid,
            ps.amount_due - COALESCE(SUM(pay.amount_paid), 0) as balance,
            DATEDIFF(CURDATE(), ps.due_date) as days_overdue,
            CASE
                WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 1 AND 30 THEN '1-30 days'
                WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 31 AND 60 THEN '31-60 days'
                WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 61 AND 90 THEN '61-90 days'
                WHEN DATEDIFF(CURDATE(), ps.due_date) > 90 THEN '90+ days'
                ELSE 'Current'
            END as aging_bucket
        FROM payment_schedules ps
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        LEFT JOIN payments pay ON ps.schedule_id = pay.schedule_id
        WHERE ps.status IN ('pending', 'overdue')
        AND ps.due_date < CURDATE()
        GROUP BY ps.schedule_id
        ORDER BY days_overdue DESC, c.name ASC
        LIMIT {$records_per_page} OFFSET {$offset}
    ");
    $aging_data = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Aging report error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again later.";
    $summary = [];
    $aging_data = [];
}

// Include header
include '../templates/header.php';
?>

<!-- Include Sidebar Navigation -->
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
                                <h2 class="mb-0"><span>📅</span> Aging Report</h2>
                                <p class="text-muted mb-0">Overdue accounts grouped by age</p>
                            </div>
                            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                                <a href="?export=csv" class="btn btn-primary">
                                    <span>📥</span> Export to CSV
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

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <?php
                        $bucket_colors = [
                            '1-30 days' => ['bg' => 'warning', 'icon' => '⚠️', 'label' => '1-30 Days'],
                            '31-60 days' => ['bg' => 'danger', 'icon' => '🔥', 'label' => '31-60 Days'],
                            '61-90 days' => ['bg' => 'danger', 'icon' => '🚨', 'label' => '61-90 Days'],
                            '90+ days' => ['bg' => 'dark', 'icon' => '💀', 'label' => '90+ Days']
                        ];

                        foreach ($bucket_colors as $bucket => $style):
                            $bucket_data = null;
                            foreach ($summary as $s) {
                                if ($s['aging_bucket'] === $bucket) {
                                    $bucket_data = $s;
                                    break;
                                }
                            }
                            $count = $bucket_data ? $bucket_data['count'] : 0;
                            $balance = $bucket_data ? $bucket_data['total_balance'] : 0;
                            ?>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card border-<?php echo $style['bg']; ?>">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">
                                            <span><?php echo $style['icon']; ?></span> <?php echo $style['label']; ?>
                                            Overdue
                                        </h6>
                                        <h3 class="card-title text-<?php echo $style['bg']; ?>">
                                            <?php echo number_format($count); ?>
                                        </h3>
                                        <p class="mb-0"><small>Balance: ₱<?php echo number_format($balance, 2); ?></small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Detailed Aging Table -->
                    <div class="card">
                        <div class="card-header">
                            <span>📋</span> Detailed Aging Report
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($aging_data)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">📭</div>
                                    <h5>No Overdue Accounts</h5>
                                    <p class="text-muted">All payment schedules are current or paid.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Client</th>
                                                <th>Contact</th>
                                                <th>Property</th>
                                                <th>Due Date</th>
                                                <th class="text-end">Amount Due</th>
                                                <th class="text-end">Paid</th>
                                                <th class="text-end">Balance</th>
                                                <th class="text-center">Days Overdue</th>
                                                <th>Aging</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($aging_data as $row):
                                                $severity_class = '';
                                                if ($row['days_overdue'] > 90)
                                                    $severity_class = 'table-danger';
                                                elseif ($row['days_overdue'] > 60)
                                                    $severity_class = 'table-warning';
                                                elseif ($row['days_overdue'] > 30)
                                                    $severity_class = 'table-info';
                                                ?>
                                                <tr class="<?php echo $severity_class; ?>">
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($row['client_name']); ?></strong><br>
                                                        <small
                                                            class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['contact_no']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['property_name']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($row['due_date'])); ?></td>
                                                    <td class="text-end">₱<?php echo number_format($row['amount_due'], 2); ?>
                                                    </td>
                                                    <td class="text-end">₱<?php echo number_format($row['amount_paid'], 2); ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <strong>₱<?php echo number_format($row['balance'], 2); ?></strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-danger"><?php echo $row['days_overdue']; ?>
                                                            days</span>
                                                    </td>
                                                    <td><?php echo $row['aging_bucket']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <div class="card-footer bg-light">
                                        <nav aria-label="Aging report pagination">
                                            <ul class="pagination pagination-sm mb-0 justify-content-center">
                                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link"
                                                        href="?page=<?php echo max(((int) $current_page - 1), 1); ?>"
                                                        tabindex="-1">
                                                        <span aria-hidden="true">&laquo;</span> Previous
                                                    </a>
                                                </li>

                                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                    <?php if ($i == 1 || $i == $total_pages || ($i >= ((int) $current_page - 2) && $i <= ((int) $current_page + 2))): ?>
                                                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
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
                                                        href="?page=<?php echo min((int) $current_page + 1, $total_pages); ?>">
                                                        Next <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php
    include '../templates/footer.php';
    ?>