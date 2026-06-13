<?php
/**
 * Client Header Template
 * Real Estate Receivable System
 * 
 * Top navigation header for client portal pages
 */

// Prevent direct access
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Real Estate Receivable System');
}

// Get current user info
$current_user = $_SESSION['username'] ?? 'Client';
$client_id = $_SESSION['client_id'] ?? null;

// Get client name if available
$client_name = $current_user;
if ($client_id && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM clients WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $client_data = $stmt->fetch();
        if ($client_data) {
            $client_name = $client_data['name'];
        }
    } catch (Exception $e) {
        // Keep default
    }
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>
        <?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?>
    </title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../assets/bootstrap/bootstrap.min.css">

    <!-- Custom Styles for Client Portal -->
    <style>
        :root {
            --primary-maroon: #800000;
            --dark-maroon: #5c0000;
            --light-maroon: #a32929;
            --mulled-wine: #4B4359;
            --dark-wine: #352f40;
            --light-wine: #625970;
            --beige: #F5F5DD;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--mulled-wine);
            min-height: 100vh;
        }

        /* Top Navbar */
        .client-navbar {
            background: linear-gradient(135deg, var(--mulled-wine) 0%, var(--dark-maroon) 100%);
            padding: 0;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .client-navbar .navbar-brand {
            color: var(--beige) !important;
            font-weight: 700;
            font-size: 1.3rem;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .client-navbar .navbar-brand:hover {
            color: var(--white) !important;
        }

        .client-navbar .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            padding: 15px 20px !important;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .client-navbar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white) !important;
            border-bottom-color: var(--beige);
        }

        .client-navbar .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: var(--white) !important;
            border-bottom-color: var(--beige);
        }

        .client-navbar .nav-link .nav-icon {
            margin-right: 8px;
        }

        .user-dropdown .dropdown-toggle {
            color: var(--beige) !important;
            padding: 10px 20px !important;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            margin: 8px 15px;
        }

        .user-dropdown .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .user-dropdown .dropdown-menu {
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            border-radius: 10px;
        }

        .user-dropdown .dropdown-item {
            padding: 10px 20px;
        }

        .user-dropdown .dropdown-item:hover {
            background: var(--beige);
            color: var(--primary-maroon);
        }

        .logout-btn {
            background: var(--primary-maroon) !important;
            color: white !important;
        }

        .logout-btn:hover {
            background: var(--dark-maroon) !important;
        }

        /* Main Content */
        .client-main {
            padding: 30px 0;
            min-height: calc(100vh - 140px);
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            color: var(--white);
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
            padding: 15px 20px;
        }

        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            border: none;
            border-radius: 5px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--dark-maroon) 0%, var(--primary-maroon) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
        }

        .btn-outline-primary {
            color: var(--primary-maroon);
            border-color: var(--primary-maroon);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-maroon);
            border-color: var(--primary-maroon);
            color: var(--white);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h2 {
            color: var(--mulled-wine);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        /* Table Styles */
        .table thead {
            background-color: var(--mulled-wine);
            color: var(--white);
        }

        /* Form Controls */
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
        }

        /* Footer */
        .client-footer {
            background: var(--mulled-wine);
            color: rgba(255, 255, 255, 0.8);
            padding: 20px 0;
            text-align: center;
        }

        .client-footer a {
            color: var(--beige);
            text-decoration: none;
        }

        .client-footer a:hover {
            text-decoration: underline;
        }

        /* Mobile responsiveness */
        @media (max-width: 991px) {
            .client-navbar .nav-link {
                border-bottom: none;
                border-left: 3px solid transparent;
            }

            .client-navbar .nav-link.active,
            .client-navbar .nav-link:hover {
                border-left-color: var(--beige);
                border-bottom-color: transparent;
            }

            .user-dropdown .dropdown-toggle {
                margin: 10px 0;
                border-radius: 5px;
            }
        }

        /* Breadcrumb */
        .breadcrumb {
            background-color: transparent;
            padding: 0.75rem 0;
            margin-bottom: 1rem;
        }

        .breadcrumb-item a {
            color: var(--primary-maroon);
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            text-decoration: underline;
        }

        /* Progress bar */
        .progress {
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            transition: width 0.5s ease;
        }

        /* Alert improvements */
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>

    <?php if (isset($extra_css))
        echo $extra_css; ?>
</head>

<body>

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg client-navbar">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                🏢 RERS Client Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#clientNavbar"
                aria-controls="clientNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="clientNavbar">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>"
                            href="dashboard.php">
                            <span class="nav-icon">🏠</span> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'my_properties.php' ? 'active' : ''; ?>"
                            href="my_properties.php">
                            <span class="nav-icon">🏘️</span> My Properties
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'my_payments.php' ? 'active' : ''; ?>"
                            href="my_payments.php">
                            <span class="nav-icon">💳</span> Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'my_ledger.php' ? 'active' : ''; ?>"
                            href="my_ledger.php">
                            <span class="nav-icon">📒</span> Ledger
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'my_documents.php' ? 'active' : ''; ?>"
                            href="my_documents.php">
                            <span class="nav-icon">📁</span> Documents
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown user-dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            👤
                            <?php echo htmlspecialchars($client_name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="../catalog.php">🏠 Property Catalog</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item logout-btn" href="../auth/logout.php">🚪 Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="client-main">
        <div class="container">