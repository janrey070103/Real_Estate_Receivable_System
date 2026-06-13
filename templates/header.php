<?php

/**
 * Header Template
 * Real Estate Receivable System
 * 
 * Included in all pages for consistent layout
 */

// Prevent direct access
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Real Estate Receivable System');
}

// Determine asset path based on directory level
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$dir_name = basename($script_dir);

// Check if we're in root or subdirectory
if ($dir_name === 'real_estate_receivable_system') {
    $asset_path = '';
} elseif (in_array($dir_name, ['modules', 'auth', 'reports', 'api', 'client'])) {
    $asset_path = '../';
} else {
    $asset_path = '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>

    <!-- Favicon (optional) -->
    <link rel="icon" type="image/x-icon" href="<?php echo $asset_path; ?>assets/img/favicon.ico">

    <!-- Bootstrap CSS (Offline) -->
    <link rel="stylesheet" href="<?php echo $asset_path; ?>assets/bootstrap/bootstrap.min.css">

    <!-- Select2 CSS for searchable dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $asset_path; ?>assets/css/custom.css">

    <!-- Custom Styles for Beige/Maroon/Mulled Wine Theme -->
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
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--beige);
            color: var(--mulled-wine);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--mulled-wine) 0%, var(--dark-wine) 100%);
            color: var(--white);
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 20px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-icon {
            font-size: 1.5rem;
        }

        .brand-text {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--beige);
        }

        .sidebar.collapsed .brand-text,
        .sidebar.collapsed .user-info,
        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .nav-section,
        .sidebar.collapsed .nav-badge,
        .sidebar.collapsed .nav-tag {
            display: none;
        }

        .sidebar-toggle {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: var(--white);
            width: 35px;
            height: 35px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: var(--primary-maroon);
        }

        .sidebar-user {
            padding: 20px 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: var(--primary-maroon);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
            overflow: hidden;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.75rem;
            padding: 2px 8px;
            width: fit-content;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 15px 0;
        }

        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-section {
            padding: 15px 20px 8px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
        }

        .nav-item {
            margin: 2px 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
        }

        .nav-link.active {
            background: var(--primary-maroon);
            color: var(--white);
        }

        .nav-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
        }

        .nav-text {
            flex: 1;
            font-weight: 500;
            white-space: nowrap;
        }

        .nav-badge {
            background: #dc3545;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        .nav-tag {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .sidebar-footer {
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-footer .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: var(--primary-maroon);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            justify-content: center;
        }

        .sidebar-footer .logout-btn:hover {
            background: var(--dark-maroon);
            transform: translateY(-2px);
        }

        .sidebar.collapsed .logout-btn .nav-text {
            display: none;
        }

        /* Main Content Wrapper */
        .main-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        body.sidebar-collapsed .main-wrapper {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Mobile Sidebar */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Mobile Menu Toggle Button */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 998;
            background: var(--primary-maroon);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
        }

        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main-wrapper {
                margin-left: 0 !important;
            }

            .mobile-menu-toggle {
                display: block;
            }
        }

        /* Header Styles */
        .top-header {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            color: var(--white);
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .top-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .top-header .user-info {
            text-align: right;
            font-size: 0.9rem;
        }

        /* Content Area */
        .main-content {
            padding: 30px 0;
            min-height: calc(100vh - 200px);
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
            border-radius: 5px;
            padding: 10px 20px;
            font-weight: 500;
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-maroon);
            border-color: var(--primary-maroon);
            color: var(--white);
        }

        /* Table Styles */
        .table thead {
            background-color: var(--mulled-wine);
            color: var(--white);
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(128, 0, 0, 0.05);
        }

        /* Badge Styles */
        .badge-maroon {
            background-color: var(--primary-maroon);
            color: var(--white);
        }

        /* Alert Styles */
        .alert-primary {
            background-color: rgba(128, 0, 0, 0.1);
            border-color: var(--primary-maroon);
            color: var(--dark-maroon);
        }

        /* Form Controls */
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
        }

        /* Stats Card */
        .stats-card {
            background: var(--white);
            border-left: 4px solid var(--primary-maroon);
            padding: 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        /* Prevent chart cards from transforming on hover */
        .card:has(canvas) {
            transition: none;
        }

        .card:has(canvas):hover {
            transform: none;
        }

        /* Chart container - fixed height to prevent resize */
        .card canvas {
            max-height: 300px;
            width: 100% !important;
            height: 300px !important;
        }

        .stats-card h3,
        .stats-card h4 {
            color: var(--primary-maroon);
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            word-break: break-word;
        }

        .stats-card h3 {
            font-size: 2rem;
        }

        .stats-card p {
            color: var(--mulled-wine);
            margin: 5px 0 0 0;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .stats-card .d-flex {
            min-height: 80px;
        }

        .stats-card .d-flex>div:first-child {
            flex: 1;
            min-width: 0;
        }

        .stats-card .d-flex>div:last-child {
            flex-shrink: 0;
            display: flex;
            align-items: center;
        }

        /* Page Header Styles */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h2 {
            color: var(--mulled-wine);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header .text-muted {
            font-size: 1rem;
        }

        /* Breadcrumb Styles */
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

        .breadcrumb-item.active {
            color: var(--mulled-wine);
        }

        /* Search Form Styles */
        .search-form {
            margin-bottom: 1.5rem;
        }

        .search-form .form-control {
            border-radius: 5px;
        }

        .search-form .btn {
            white-space: nowrap;
        }

        /* Action Buttons */
        .btn-group-sm>.btn,
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        /* Table Action Buttons */
        .table .btn-group {
            gap: 0.25rem;
        }

        /* Empty State */
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
        }

        .empty-state .empty-icon {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-card h3 {
                font-size: 1.5rem;
            }

            .page-header h2 {
                font-size: 1.5rem;
            }

            .search-form .btn {
                margin-top: 0.5rem;
                width: 100%;
            }
        }

        /* Navigation Styles */
        .main-nav {
            background-color: var(--mulled-wine);
            padding: 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            margin-bottom: 0;
        }

        .main-nav .navbar-brand {
            color: var(--primary-maroon);
            font-weight: 700;
            font-size: 1.3rem;
            padding: 15px 20px;
        }

        .main-nav .navbar-brand:hover {
            color: var(--light-maroon);
        }

        .main-nav .navbar-nav {
            width: auto;
        }

        .main-nav .nav-link {
            color: var(--white) !important;
            padding: 15px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            text-decoration: none;
        }

        .main-nav .nav-link:hover {
            background-color: var(--dark-wine);
            color: var(--primary-maroon) !important;
            border-bottom-color: var(--primary-maroon);
        }

        .main-nav .nav-link.active {
            background-color: var(--dark-wine);
            color: var(--primary-maroon) !important;
            border-bottom-color: var(--primary-maroon);
        }

        .main-nav .nav-link span {
            margin-right: 8px;
            font-size: 1.1rem;
        }

        /* Fix nav item alignment */
        .main-nav .nav-item {
            display: flex;
            align-items: center;
        }

        .main-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .main-nav .nav-link .badge {
            display: inline-flex;
            align-items: center;
            vertical-align: middle;
        }

        .main-nav .navbar-nav {
            align-items: center;
        }

        .main-nav .logout-btn {
            color: var(--white) !important;
            background-color: var(--primary-maroon);
            margin: 8px 10px;
            padding: 8px 20px;
            border-radius: 5px;
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .main-nav .logout-btn:hover {
            background-color: var(--dark-maroon);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(128, 0, 0, 0.3);
        }

        /* Mobile responsiveness */
        @media (max-width: 991px) {
            .main-nav .nav-link {
                border-bottom: none;
                border-left: 3px solid transparent;
            }

            .main-nav .nav-link:hover,
            .main-nav .nav-link.active {
                border-left-color: var(--primary-maroon);
                border-bottom-color: transparent;
            }

            .main-nav .logout-btn {
                width: calc(100% - 20px);
                margin: 10px;
            }
        }
    </style>

    <?php if (isset($extra_css))
        echo $extra_css; ?>
</head>

<body>
    <!-- Mobile Menu Toggle (visible on small screens) -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰ Menu</button>