<?php
/**
 * Enhanced Dashboard Page
 * Real Estate Receivable System
 * 
 * Main dashboard with statistics, charts, and notifications
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

// Require user to be logged in
require_login();

// Set page title
$page_title = 'Dashboard';

// Fetch dashboard statistics
try {
    // Initialize variables to prevent undefined errors
    $monthly_revenue = [];
    $payment_status = [];
    $recent_notifications = [];
    $recent_payments = [];

    // Initialize stats variables with defaults
    $total_clients = 0;
    $total_properties = 0;
    $total_receivables = 0;
    $total_revenue = 0;
    $total_outstanding = 0;
    $paid_count = 0;
    $unpaid_count = 0;
    $overdue_count = 0;
    $pending_notifications = 0;

    // Split complex query into individual queries to prevent MySQL crashes
    // Use prepared statements to reduce load
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM clients");
        $total_clients = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Dashboard clients count error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM properties");
        $total_properties = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Dashboard properties count error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as total FROM properties");
        $total_receivables = (float) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Dashboard receivables error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM payments");
        $total_revenue = (float) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Dashboard revenue error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount_due), 0) as total FROM payment_schedules WHERE status IN ('pending', 'overdue')");
        $total_outstanding = (float) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Dashboard outstanding error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM payment_schedules WHERE status = 'paid'");
        $paid_count = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Dashboard paid count error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM payment_schedules WHERE status IN ('pending', 'overdue')");
        $unpaid_count = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Dashboard unpaid count error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM payment_schedules WHERE status = 'overdue'");
        $overdue_count = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Dashboard overdue count error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications WHERE status = 'pending'");
        $pending_notifications = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Dashboard notifications count error: " . $e->getMessage());
    }

    // Chart data - Monthly revenue (last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(date_paid, '%b %Y') as month,
            SUM(amount_paid) as total
        FROM payments
        WHERE date_paid >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_paid, '%Y-%m')
        ORDER BY date_paid ASC
        LIMIT 6
    ");
    $monthly_revenue = $stmt->fetchAll();

    // Chart data - Payment status distribution
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(amount_due) as total_amount
        FROM payment_schedules
        GROUP BY status
    ");
    $payment_status = $stmt->fetchAll();

    // Recent notifications (last 10)
    $stmt = $pdo->query("
        SELECT 
            n.*,
            c.name as client_name
        FROM notifications n
        INNER JOIN clients c ON n.client_id = c.client_id
        ORDER BY n.date_created DESC
        LIMIT 10
    ");
    $recent_notifications = $stmt->fetchAll();

    // Recent payments (last 5)
    // Use try-catch to prevent crashes if view doesn't exist
    try {
        $stmt = $pdo->query("SELECT * FROM vw_payment_history LIMIT 5");
        $recent_payments = $stmt->fetchAll();
    } catch (PDOException $view_error) {
        // View doesn't exist or failed - use direct query as fallback
        error_log("Payment history view error: " . $view_error->getMessage());
        try {
            $stmt = $pdo->query("
                SELECT 
                    pay.payment_id,
                    pay.receipt_no,
                    pay.date_paid,
                    pay.amount_paid,
                    c.client_id,
                    c.name as client_name,
                    p.property_name,
                    ps.due_date,
                    ps.amount_due
                FROM payments pay
                INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
                INNER JOIN properties p ON ps.property_id = p.property_id
                LEFT JOIN clients c ON p.client_id = c.client_id
                ORDER BY pay.date_paid DESC
                LIMIT 5
            ");
            $recent_payments = $stmt->fetchAll();
        } catch (PDOException $fallback_error) {
            // Even fallback failed - set empty array
            error_log("Payment history fallback failed: " . $fallback_error->getMessage());
            $recent_payments = [];
        }
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Prepare chart data for JavaScript
$chart_months = [];
$chart_revenue = [];
if (!empty($monthly_revenue)) {
    foreach ($monthly_revenue as $data) {
        $chart_months[] = $data['month'];
        $chart_revenue[] = (float) $data['total'];
    }
}

$chart_status_labels = [];
$chart_status_values = [];
$chart_status_colors = [];
if (!empty($payment_status)) {
    foreach ($payment_status as $data) {
        $chart_status_labels[] = ucfirst($data['status']);
        $chart_status_values[] = (int) $data['count'];

        // Assign colors
        if ($data['status'] === 'paid')
            $chart_status_colors[] = '#2A9D8F';
        elseif ($data['status'] === 'pending')
            $chart_status_colors[] = '#E9C46A';
        elseif ($data['status'] === 'overdue')
            $chart_status_colors[] = '#dc3545';
        else
            $chart_status_colors[] = '#6c757d';
    }
}

// Include header
include 'templates/header.php';
?>

<!-- Include Sidebar Navigation -->
<?php include 'templates/sidebar.php'; ?>

<!-- Main Content Wrapper -->
<div class="main-wrapper">
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 style="color: var(--slate-gray); font-weight: 700;">
                        <span style="color: var(--primary-blue);">📊</span> Dashboard Overview
                    </h2>
                    <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Here's
                        your
                        business summary.</p>
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

            <!-- Main Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo number_format($total_clients ?? 0); ?></h3>
                                <p>Total Clients</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">👥</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo number_format($total_properties ?? 0); ?></h3>
                                <p>Total Properties</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">🏘️</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 style="font-size: 1.5rem;">₱<?php echo number_format($total_receivables ?? 0, 2); ?>
                                </h3>
                                <p>Total Receivables</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">💰</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 style="font-size: 1.5rem;">₱<?php echo number_format($total_outstanding ?? 0, 2); ?>
                                </h3>
                                <p>Outstanding</p>
                            </div>
                            <div style="font-size: 3rem; opacity: 0.3;">📊</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Status Cards -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card border-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="text-success mb-0"><?php echo number_format($paid_count ?? 0); ?></h4>
                                    <p class="text-muted mb-0 small">Paid Schedules</p>
                                </div>
                                <div style="font-size: 2.5rem; opacity: 0.2;">✅</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="text-warning mb-0"><?php echo number_format($unpaid_count ?? 0); ?></h4>
                                    <p class="text-muted mb-0 small">Unpaid Schedules</p>
                                </div>
                                <div style="font-size: 2.5rem; opacity: 0.2;">⏳</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="text-danger mb-0"><?php echo number_format($overdue_count ?? 0); ?></h4>
                                    <p class="text-muted mb-0 small">Overdue Payments</p>
                                </div>
                                <div style="font-size: 2.5rem; opacity: 0.2;">⚠️</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($overdue_count) && $overdue_count > 0): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong>⚠️ Attention!</strong> You have <strong><?php echo $overdue_count; ?></strong> overdue
                            payment(s) that require immediate attention.
                            <a href="modules/payments.php" class="alert-link">View Overdue Payments</a>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($pending_notifications) && $pending_notifications > 0): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <strong>🔔 Reminder!</strong> You have <strong><?php echo $pending_notifications; ?></strong>
                            pending notification(s) ready to be sent.
                            <a href="modules/notifications.php" class="alert-link">View Notifications</a>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Charts Row -->
            <div class="row mb-4">
                <!-- Bar Chart - Monthly Revenue -->
                <div class="col-lg-8 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <span>📊</span> Monthly Revenue Trend (Last 6 Months)
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" style="max-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Pie Chart - Payment Status -->
                <div class="col-lg-4 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <span>📈</span> Payment Status Distribution
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" style="max-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Notifications Table -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><span>🔔</span> Recent Notifications (SMS/Email Logs)</span>
                                <a href="modules/notifications.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($recent_notifications)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 60px;">ID</th>
                                                <th style="width: 80px;">Type</th>
                                                <th>Client</th>
                                                <th>Message</th>
                                                <th style="width: 140px;">Date</th>
                                                <th style="width: 80px;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_notifications as $notif): ?>
                                                <tr>
                                                    <td><small><?php echo $notif['notif_id']; ?></small></td>
                                                    <td>
                                                        <?php if ($notif['type'] === 'sms'): ?>
                                                            <span class="badge bg-info">📱 SMS</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-primary">📧 Email</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($notif['client_name']); ?></td>
                                                    <td><small><?php echo htmlspecialchars(substr($notif['message'], 0, 80)) . '...'; ?></small>
                                                    </td>
                                                    <td><small><?php echo date('M d, Y h:i A', strtotime($notif['date_created'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($notif['status'] === 'sent'): ?>
                                                            <span class="badge bg-success">Sent</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <p class="text-muted">No notifications yet.</p>
                                    <a href="modules/notifications.php" class="btn btn-primary">Generate Reminders</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Payments & Quick Actions -->
            <div class="row">
                <div class="col-lg-8 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <span>💳</span> Recent Payments
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_payments)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>Receipt No.</th>
                                                <th>Client</th>
                                                <th>Property</th>
                                                <th>Amount</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_payments as $payment): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($payment['receipt_no']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                                                    <td><strong
                                                            style="color: var(--primary-blue);">₱<?php echo number_format($payment['amount_paid'], 2); ?></strong>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($payment['date_paid'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="modules/payments.php" class="btn btn-outline-primary">View All Payments</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <p class="text-muted">No payment records found.</p>
                                    <a href="modules/payments.php" class="btn btn-primary">Record New Payment</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <span>⚡</span> Quick Actions
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="modules/clients.php" class="btn btn-outline-primary">
                                    <span>👥</span> Manage Clients
                                </a>
                                <a href="modules/properties.php" class="btn btn-outline-primary">
                                    <span>🏘️</span> Add Property
                                </a>
                                <a href="modules/payments.php" class="btn btn-outline-primary">
                                    <span>💳</span> Record Payment
                                </a>
                                <a href="modules/notifications.php" class="btn btn-outline-primary">
                                    <span>🔔</span> Send Reminders
                                    <?php if ($pending_notifications > 0): ?>
                                        <span class="badge bg-danger"><?php echo $pending_notifications; ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="reports/aging_report.php" class="btn btn-outline-danger">
                                    <span>📅</span> Aging Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Library (Offline) -->
    <script src="assets/chartjs/chart.umd.min.js"></script>

    <script>
        // Revenue Chart (Bar Chart)
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_months); ?>,
                datasets: [{
                    label: 'Monthly Revenue (₱)',
                    data: <?php echo json_encode($chart_revenue); ?>,
                    backgroundColor: 'rgba(42, 157, 143, 0.7)',
                    borderColor: '#2A9D8F',
                    borderWidth: 2,
                    hoverBackgroundColor: 'rgba(233, 196, 106, 0.8)',
                    hoverBorderColor: '#E9C46A'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        backgroundColor: 'rgba(42, 157, 143, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#E9C46A',
                        borderWidth: 2
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return '₱' + value.toLocaleString();
                            },
                            color: '#4B4359'
                        },
                        grid: {
                            color: 'rgba(42, 157, 143, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#4B4359'
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Status Chart (Pie Chart)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($chart_status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_status_values); ?>,
                    backgroundColor: <?php echo json_encode($chart_status_colors); ?>,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverBorderColor: '#E9C46A',
                    hoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            color: '#4B4359',
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(42, 157, 143, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#E9C46A',
                        borderWidth: 2,
                        callbacks: {
                            label: function (context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.parsed + ' schedules';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>

    <?php include 'templates/footer.php'; ?>