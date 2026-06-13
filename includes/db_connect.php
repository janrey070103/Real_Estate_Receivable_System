<?php
/**
 * Database Connection File
 * Real Estate Receivable System
 * 
 * This file establishes a PDO connection to MySQL database
 * Uses UTF-8 charset for international character support
 */

// Prevent direct access
if (!defined('DB_INCLUDE')) {
    define('DB_INCLUDE', true);
}

// Debug mode (set to false in production)
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', false);
}

// Database configuration
// In production, use environment variables or a separate config file
$db_host = 'localhost';
$db_name = 'real_estate_receivable_db';
$db_user = 'root';
$db_pass = '';  // Default XAMPP password is empty
$db_charset = 'utf8mb4';

// DSN (Data Source Name)
$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";

// PDO options for better error handling and security
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,     // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,           // Fetch associative arrays by default
    PDO::ATTR_EMULATE_PREPARES   => false,                      // Use real prepared statements
    PDO::ATTR_PERSISTENT         => false,                      // Don't use persistent connections
    PDO::ATTR_TIMEOUT            => 5,                          // Connection timeout (5 seconds)
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$db_charset}"   // Set charset on connection
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // Optional: Set timezone (adjust to your timezone)
    // Wrapped in try-catch to prevent crashes if timezone tables are not loaded
    try {
        $pdo->exec("SET time_zone = '+08:00'");  // Asia/Manila timezone
    } catch (PDOException $tz_error) {
        // Timezone setting failed - log but continue (not critical)
        error_log("Timezone setting failed: " . $tz_error->getMessage());
        // System will use MySQL server's default timezone
    }
    
} catch (PDOException $e) {
    // Log error (in production, log to file instead of displaying)
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Display user-friendly error message
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        // Show detailed error in development
        die("Database Connection Failed: " . $e->getMessage());
    } else {
        // Show generic error in production
        die("Database connection failed. Please contact the system administrator.");
    }
}

/**
 * Optional: Helper function to close connection
 * Call this at the end of scripts if needed
 */
function closeConnection() {
    global $pdo;
    $pdo = null;
}

// Connection successful - $pdo is now available for use
?>
