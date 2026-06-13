<?php
/**
 * Index Page - Main Entry Point
 * Real Estate Receivable System
 * 
 * Redirects to login if not authenticated, otherwise to dashboard
 */

// Start session
session_start();

// Define application constants
define('APP_NAME', 'Real Estate Receivable System');

// Check if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // User is logged in, redirect to dashboard
    header('Location: dashboard.php');
    exit();
} else {
    // User is not logged in, redirect to login page
    header('Location: auth/login.php');
    exit();
}
