<?php
/**
 * Sidebar Navigation Template
 * Real Estate Receivable System - Phase 7
 * 
 * Collapsible sidebar menu with role-based navigation
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Determine base path
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$dir_name = basename($script_dir);

if ($dir_name === 'real_estate_receivable_system') {
    $base_path = '';
} elseif (in_array($dir_name, ['modules', 'auth', 'reports', 'api', 'client'])) {
    $base_path = '../';
} else {
    $base_path = '';
}

// Fetch pending notifications count
$pending_notifications_count = 0;
if (isset($pdo)) {
    try {
        $notif_count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE status = 'pending' LIMIT 1");
        $notif_count_stmt->execute();
        $result = $notif_count_stmt->fetch();
        $pending_notifications_count = (int) ($result['count'] ?? 0);
    } catch (PDOException $e) {
        error_log("Sidebar notifications count error: " . $e->getMessage());
        $pending_notifications_count = 0;
    }
}

// Determine user role
$user_role = $_SESSION['role'] ?? 'guest';
$is_client = ($user_role === 'client');
?>

<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <span class="brand-icon">🏢</span>
            <span class="brand-text">RERS</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
            <span>☰</span>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
        </div>
        <div class="user-info">
            <span class="user-name">
                <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
            </span>
            <span
                class="user-role badge bg-<?php echo $is_client ? 'info' : ($user_role === 'admin' ? 'danger' : 'warning'); ?>">
                <?php echo ucfirst($user_role); ?>
            </span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php if (!$is_client): ?>
                <!-- Admin/Finance Navigation -->
                <li class="nav-section">Main</li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>dashboard.php"
                        class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-section">Management</li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>modules/clients.php"
                        class="nav-link <?php echo (strpos($current_page, 'client') !== false) ? 'active' : ''; ?>">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">Clients</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>modules/properties.php"
                        class="nav-link <?php echo (strpos($current_page, 'propert') !== false) ? 'active' : ''; ?>">
                        <span class="nav-icon">🏘️</span>
                        <span class="nav-text">Properties</span>
                    </a>
                </li>

                <li class="nav-section">Finance</li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>modules/payments.php"
                        class="nav-link <?php echo (strpos($current_page, 'payment') !== false && strpos($current_page, 'ledger') === false) ? 'active' : ''; ?>">
                        <span class="nav-icon">💳</span>
                        <span class="nav-text">Payment</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>modules/payment_ledger.php"
                        class="nav-link <?php echo ($current_page == 'payment_ledger.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">📒</span>
                        <span class="nav-text">Payment Ledger</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>modules/invoices.php"
                        class="nav-link <?php echo (strpos($current_page, 'invoice') !== false) ? 'active' : ''; ?>">
                        <span class="nav-icon">🧾</span>
                        <span class="nav-text">Invoices</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>modules/inquiries.php"
                        class="nav-link <?php echo (strpos($current_page, 'inquir') !== false) ? 'active' : ''; ?>">
                        <span class="nav-icon">📧</span>
                        <span class="nav-text">Inquiries</span>
                        <?php
                        // Fetch pending inquiries count
                        $pending_inquiries_count = 0;
                        if (isset($pdo)) {
                            try {
                                $inq_count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM inquiries WHERE status = 'pending'");
                                $inq_count_stmt->execute();
                                $pending_inquiries_count = (int) $inq_count_stmt->fetch()['count'];
                            } catch (PDOException $e) { /* ignore */
                            }
                        }
                        if ($pending_inquiries_count > 0): ?>
                            <span class="nav-badge bg-danger">
                                <?php echo $pending_inquiries_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-section">Reports & Alerts</li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>reports/aging_report.php"
                        class="nav-link <?php echo (strpos($current_page, 'report') !== false || strpos($current_page, 'aging') !== false) ? 'active' : ''; ?>">
                        <span class="nav-icon">📈</span>
                        <span class="nav-text">Aging Report</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>modules/notifications.php"
                        class="nav-link <?php echo (strpos($current_page, 'notification') !== false) ? 'active' : ''; ?>">
                        <span class="nav-icon">🔔</span>
                        <span class="nav-text">Notifications</span>
                        <?php if ($pending_notifications_count > 0): ?>
                            <span class="nav-badge">
                                <?php echo $pending_notifications_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <?php if (function_exists('is_admin') && is_admin()): ?>
                    <li class="nav-section">Administration</li>

                    <li class="nav-item">
                        <a href="<?php echo $base_path; ?>modules/users.php"
                            class="nav-link <?php echo (strpos($current_page, 'user') !== false) ? 'active' : ''; ?>">
                            <span class="nav-icon">👤</span>
                            <span class="nav-text">Users</span>
                            <span class="nav-tag">Admin</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?php echo $base_path; ?>modules/audit_logs.php"
                            class="nav-link <?php echo (strpos($current_page, 'audit') !== false) ? 'active' : ''; ?>">
                            <span class="nav-icon">📋</span>
                            <span class="nav-text">Audit Logs</span>
                        </a>
                    </li>
                <?php endif; ?>

            <?php else: ?>
                <!-- Client Portal Navigation -->
                <li class="nav-section">My Account</li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>client/dashboard.php"
                        class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">🏠</span>
                        <span class="nav-text">My Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>client/my_properties.php"
                        class="nav-link <?php echo (strpos($current_page, 'propert') !== false) ? 'active' : ''; ?>">
                        <span class="nav-icon">🏘️</span>
                        <span class="nav-text">My Properties</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>client/my_payments.php"
                        class="nav-link <?php echo (strpos($current_page, 'payment') !== false) ? 'active' : ''; ?>">
                        <span class="nav-icon">💳</span>
                        <span class="nav-text">Due</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>client/my_ledger.php"
                        class="nav-link <?php echo (strpos($current_page, 'ledger') !== false) ? 'active' : ''; ?>">
                        <span class="nav-icon">📒</span>
                        <span class="nav-text">Account Ledger</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="<?php echo $base_path; ?>auth/logout.php" class="logout-btn">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</aside>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        // Toggle sidebar
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                sidebar.classList.toggle('collapsed');
                document.body.classList.toggle('sidebar-collapsed');
            });
        }

        // Mobile overlay click
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function () {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
            });
        }

        // Mobile toggle
        const mobileToggle = document.getElementById('mobileMenuToggle');
        if (mobileToggle) {
            mobileToggle.addEventListener('click', function () {
                sidebar.classList.toggle('mobile-open');
                sidebarOverlay.classList.toggle('active');
            });
        }
    });
</script>