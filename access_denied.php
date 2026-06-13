<?php
/**
 * Access Denied Page
 * Real Estate Receivable System
 * 
 * Displays when a user tries to access a page without proper permissions
 */

define('APP_NAME', 'Real Estate Receivable System');

// Start session if not started (but don't require login!)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Access Denied';
$role = $_SESSION['role'] ?? 'guest';
$username = $_SESSION['username'] ?? 'Guest';

// Determine where to redirect based on role
if ($role === 'client') {
    $home_url = 'client/dashboard.php';
    $home_label = 'Client Dashboard';
} elseif ($role === 'admin' || $role === 'finance') {
    $home_url = 'dashboard.php';
    $home_label = 'Dashboard';
} else {
    $home_url = 'auth/login.php';
    $home_label = 'Login';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . APP_NAME; ?></title>

    <link rel="stylesheet" href="assets/bootstrap/bootstrap.min.css">

    <style>
        :root {
            --primary-maroon: #800000;
            --dark-maroon: #5c0000;
            --mulled-wine: #4B4359;
            --dark-wine: #352f40;
            --beige: #F5F5DD;
        }

        body {
            background: linear-gradient(135deg, var(--mulled-wine) 0%, var(--dark-wine) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .access-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            padding: 50px 40px;
            text-align: center;
            max-width: 500px;
            margin: 20px;
        }

        .access-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }

        .access-title {
            color: var(--primary-maroon);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .access-info {
            background-color: var(--beige);
            border-left: 4px solid var(--primary-maroon);
            padding: 15px;
            margin: 20px 0;
            text-align: left;
            border-radius: 0 8px 8px 0;
        }

        .btn-home {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            transition: all 0.3s ease;
        }

        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(128, 0, 0, 0.4);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--mulled-wine) 0%, var(--dark-wine) 100%);
        }
    </style>
</head>

<body>
    <div class="access-card">
        <div class="access-icon">🚫</div>
        <h1 class="access-title">Access Denied</h1>
        <p class="text-muted">You don't have permission to access this page or feature.</p>

        <?php if ($role !== 'guest'): ?>
            <div class="access-info">
                <h6 class="mb-2">📋 Your Current Access Level:</h6>
                <ul class="mb-0" style="padding-left: 20px;">
                    <li><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></li>
                    <li><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($role)); ?></li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($role === 'finance'): ?>
            <div class="access-info">
                <h6 class="mb-2">✅ You Have Access To:</h6>
                <ul class="mb-0" style="padding-left: 20px;">
                    <li>Dashboard, Clients, Properties</li>
                    <li>Payments & Invoices</li>
                    <li>Reports & Notifications</li>
                </ul>
            </div>
            <div class="access-info">
                <h6 class="mb-2">❌ Admin-Only Features:</h6>
                <ul class="mb-0" style="padding-left: 20px;">
                    <li>User Management</li>
                    <li>System Settings, Audit Logs</li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($role === 'client'): ?>
            <div class="access-info">
                <h6 class="mb-2">ℹ️ Client Portal Access Only</h6>
                <p class="mb-0">As a client user, you can only access the Client Portal section.</p>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="<?php echo $home_url; ?>" class="btn-home">
                🏠 Back to <?php echo $home_label; ?>
            </a>
            <a href="javascript:history.back()" class="btn-home btn-secondary">
                ⬅️ Go Back
            </a>
        </div>

        <p class="mt-4 text-muted"><small>If you believe this is an error, please contact your administrator.</small>
        </p>
    </div>

    <script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
</body>

</html>