<?php
/**
 * Logout Page
 * Real Estate Receivable System
 * 
 * Destroys user session and redirects to login
 */

// Define constants
define('DB_INCLUDE', true);

// Include authentication functions
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';

// Log the logout action to audit trail
if (is_logged_in()) {
    $username = get_username();
    log_audit($pdo, 'LOGOUT', 'user:' . $username, 'User logged out');
    error_log("User logged out: " . $username);
}

// Destroy session and logout user
logout_user();

// Redirect to login page with logout message
header('Location: login.php?logout=1');
exit();
