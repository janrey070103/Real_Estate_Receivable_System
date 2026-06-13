<?php
/**
 * Navigation Template
 * Real Estate Receivable System
 * 
 * Main navigation menu
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Determine base path (check if we're in a subdirectory)
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$dir_name = basename($script_dir);

// Check if we're in root or subdirectory
if ($dir_name === 'real_estate_receivable_system') {
    $base_path = '';
} elseif (in_array($dir_name, ['modules', 'auth', 'reports', 'api'])) {
    $base_path = '../';
} else {
    $base_path = '';
}

// Fetch pending notifications count for badge
$pending_notifications_count = 0;
if (isset($pdo)) {
    try {
        // Add timeout and error handling to prevent MySQL crashes
        $notif_count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE status = 'pending' LIMIT 1");
        $notif_count_stmt->execute();
        $result = $notif_count_stmt->fetch();
        $pending_notifications_count = (int)($result['count'] ?? 0);
    } catch (PDOException $e) {
        // Silently fail - notification badge not critical
        error_log("Nav notifications count error: " . $e->getMessage());
        $pending_notifications_count = 0;
    }
}
?>



<nav class="navbar navbar-expand-lg navbar-dark main-nav">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $base_path; ?>dashboard.php">
            <i>🏢</i> RERS
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" 
                aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>dashboard.php">
                        <span>📊</span> Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'clients.php' || strpos($current_page, 'client') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>modules/clients.php">
                        <span>👥</span> Clients
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'properties.php' || strpos($current_page, 'propert') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>modules/properties.php">
                        <span>🏘️</span> Properties
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'payments.php' || strpos($current_page, 'payment') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>modules/payments.php">
                        <span>💳</span> Payments
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'invoices.php' || strpos($current_page, 'invoice') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>modules/invoices.php">
                        <span>🧾</span> Invoices
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($current_page, 'report') !== false || strpos($current_page, 'aging') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>reports/aging_report.php">
                        <span>📈</span> Reports
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'notifications.php' || strpos($current_page, 'notification') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>modules/notifications.php" style="white-space: nowrap;">
                        <span>🔔</span> Notifications
                        <?php if ($pending_notifications_count > 0): ?>
                            <span class="badge rounded-pill bg-danger ms-2">
                                <?php echo $pending_notifications_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <?php if (is_admin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'users.php' || strpos($current_page, 'user') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>modules/users.php">
                        <span>👥</span> Users
                        <span class="badge rounded-pill bg-warning text-dark ms-1" style="font-size: 0.7rem;">Admin</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'audit_logs.php' || strpos($current_page, 'audit') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>modules/audit_logs.php">
                        <span>📋</span> Audit Logs
                        <span class="badge rounded-pill bg-info text-white ms-1" style="font-size: 0.7rem;">Admin</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="d-flex">
                <a href="<?php echo $base_path; ?>auth/logout.php" class="btn logout-btn">
                    <span>🚪</span> Logout
                </a>
            </div>
        </div>
    </div>
</nav>
