<?php
/**
 * Authentication Helper Functions
 * Real Estate Receivable System
 * 
 * Provides session management and authentication functions
 */

// Prevent direct access
if (!defined('DB_INCLUDE')) {
    define('DB_INCLUDE', true);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings for security and longevity
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

    // Session lifetime settings (8 hours = 28800 seconds)
    ini_set('session.gc_maxlifetime', 28800);
    ini_set('session.cookie_lifetime', 28800);

    // Ensure session data is not garbage collected too early
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);

    session_start();
}

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']) &&
        isset($_SESSION['username']) &&
        isset($_SESSION['role']);
}

/**
 * Require user to be logged in
 * Redirects to login page if not authenticated
 * 
 * @param string $redirect_to Optional page to redirect after login
 */
function require_login($redirect_to = '')
{
    if (!is_logged_in()) {
        // Store the current page for redirect after login
        if (!empty($redirect_to)) {
            $_SESSION['redirect_after_login'] = $redirect_to;
        } else {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }

        // Redirect to login page
        header('Location: ' . get_base_url() . 'auth/login.php');
        exit();
    }
}

/**
 * Require specific role
 * Redirects to access denied page if user doesn't have required role
 * 
 * @param string|array $required_role Role(s) required
 */
function require_role($required_role)
{
    require_login();

    $user_role = $_SESSION['role'] ?? '';

    // Check if required role is an array
    if (is_array($required_role)) {
        if (!in_array($user_role, $required_role)) {
            header('Location: ' . get_base_url() . 'access_denied.php');
            exit();
        }
    } else {
        if ($user_role !== $required_role) {
            header('Location: ' . get_base_url() . 'access_denied.php');
            exit();
        }
    }
}

/**
 * Get current logged-in user ID
 * @return int|null User ID or null if not logged in
 */
function get_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current logged-in username
 * @return string|null Username or null if not logged in
 */
function get_username()
{
    return $_SESSION['username'] ?? null;
}

/**
 * Get current logged-in user role
 * @return string|null Role or null if not logged in
 */
function get_user_role()
{
    return $_SESSION['role'] ?? null;
}

/**
 * Login user and create session
 * 
 * @param array $user User data array from database
 */
function login_user($user)
{
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['client_id'] = $user['client_id'] ?? null;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    // Set additional security tokens
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * Logout user and destroy session
 */
function logout_user()
{
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destroy the session
    session_destroy();
}

/**
 * Verify user credentials
 * 
 * @param PDO $pdo Database connection
 * @param string $username Username
 * @param string $password Password (plain text)
 * @return array|false User data array on success, false on failure
 */
function verify_credentials($pdo, $username, $password)
{
    try {
        // Prepare statement to prevent SQL injection
        $stmt = $pdo->prepare("
            SELECT user_id, username, password, role, client_id, created_at 
            FROM users 
            WHERE username = ? 
            LIMIT 1
        ");

        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if user exists and password is correct
        if ($user && password_verify($password, $user['password'])) {
            // Check if password needs rehashing (algorithm upgrade)
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                try {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $update_stmt->execute([$new_hash, $user['user_id']]);
                    error_log("Password rehashed for user_id: " . $user['user_id']);
                } catch (PDOException $e) {
                    // Log error but don't fail login if rehash fails
                    error_log("Password rehash failed: " . $e->getMessage());
                }
            }

            // Remove password from user array for security
            unset($user['password']);
            return $user;
        }

        return false;

    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Hash password securely
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hash_password($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Check session timeout
 * Logout user if inactive for too long
 * 
 * @param int $timeout Timeout in seconds (default: 1 hour)
 */
function check_session_timeout($timeout = 3600)
{
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];

        if ($elapsed > $timeout) {
            logout_user();
            header('Location: ' . get_base_url() . 'auth/login.php?timeout=1');
            exit();
        }
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Verify session security
 * Check if session is from same user agent and IP
 * Note: User agent check is disabled as browsers may change user agent strings
 */
function verify_session_security()
{
    if (is_logged_in()) {
        $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $stored_user_agent = $_SESSION['user_agent'] ?? '';
        $stored_ip = $_SESSION['ip_address'] ?? '';

        // Disabled: User agent check (browsers can change UA strings)
        // Only check IP if it changes dramatically (same network is OK)
        // This prevents false logouts from normal browser behavior

        // Optional: Uncomment to enable strict IP checking
        // if (!empty($stored_ip) && $current_ip !== $stored_ip) {
        //     logout_user();
        //     header('Location: ' . get_base_url() . 'auth/login.php?security=1');
        //     exit();
        // }
    }
}

/**
 * Get base URL of the application
 * @return string Base URL
 */
function get_base_url()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = dirname($_SERVER['SCRIPT_NAME']);

    // Remove trailing slash if present
    $script = rtrim($script, '/');

    // Calculate base URL
    $base = $protocol . '://' . $host . $script;

    // Add trailing slash
    return rtrim($base, '/') . '/';
}

/**
 * Set flash message for next page load
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message content
 */
function set_flash_message($type, $message)
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 * 
 * @return array|null Flash message array or null
 */
function get_flash_message()
{
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Sanitize input data
 * 
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generate_csrf_token()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verify_csrf_token($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is admin
 * @return bool True if admin, false otherwise
 */
function is_admin()
{
    return is_logged_in() && get_user_role() === 'admin';
}

/**
 * Check if user is finance
 * @return bool True if finance, false otherwise
 */
function is_finance()
{
    return is_logged_in() && get_user_role() === 'finance';
}

/**
 * Check if user is client
 * @return bool True if client, false otherwise
 */
function is_client()
{
    return is_logged_in() && get_user_role() === 'client';
}

/**
 * Get client ID linked to current user
 * @return int|null Client ID or null if not a client user
 */
function get_client_id()
{
    return $_SESSION['client_id'] ?? null;
}

/**
 * Require user to be a client
 * Redirects admins/finance back to their dashboard
 */
function require_client()
{
    require_login();

    if (!is_client()) {
        // Non-client users (admin/finance) should go back to their dashboard
        set_flash_message('error', 'Access denied. This section is for client users only.');

        // Determine redirect based on current script location
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        $dir_name = basename($script_dir);

        if ($dir_name === 'client') {
            header('Location: ../dashboard.php');
        } else {
            header('Location: dashboard.php');
        }
        exit();
    }

    if (!get_client_id()) {
        set_flash_message('error', 'Your account is not linked to a client profile. Please contact support.');

        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        $dir_name = basename($script_dir);

        if ($dir_name === 'client') {
            header('Location: ../auth/logout.php');
        } else {
            header('Location: auth/logout.php');
        }
        exit();
    }
}

/**
 * Log user action to audit log
 * 
 * @param PDO $pdo Database connection
 * @param string $action Action performed (e.g., 'LOGIN', 'ADD_CLIENT', 'RECORD_PAYMENT')
 * @param string $target Target of action (e.g., 'client_id:5', 'payment_id:10')
 * @param string $details Additional details about the action
 * @return bool True on success, false on failure
 */
function log_audit($pdo, $action, $target = null, $details = null)
{
    try {
        $user_id = get_user_id();

        // If no user logged in, use system user (id=0)
        if (!$user_id) {
            $user_id = 0;
        }

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, target, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $user_id,
            strtoupper($action),
            $target,
            $details,
            $ip_address,
            substr($user_agent, 0, 255) // Limit user agent length
        ]);

    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has access to a module
 * 
 * @param string $module Module name (e.g., 'users', 'clients', 'payments')
 * @return bool True if has access, false otherwise
 */
function has_module_access($module)
{
    if (!is_logged_in()) {
        return false;
    }

    $role = get_user_role();

    // Admin has access to everything
    if ($role === 'admin') {
        return true;
    }

    // Finance role access matrix
    $finance_modules = ['clients', 'properties', 'payments', 'invoices', 'reports', 'notifications', 'dashboard'];

    if ($role === 'finance') {
        return in_array($module, $finance_modules);
    }

    return false;
}

/**
 * Require module access
 * Redirects to access denied if user doesn't have access
 * 
 * @param string $module Module name
 */
function require_module_access($module)
{
    require_login();

    if (!has_module_access($module)) {
        header('Location: ' . get_base_url() . 'access_denied.php');
        exit();
    }
}

// Auto-check session timeout and security on every page load
if (is_logged_in()) {
    check_session_timeout(28800); // 8 hours timeout (28800 seconds)
    verify_session_security();
}
