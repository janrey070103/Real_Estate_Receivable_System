<?php
/**
 * Login Page
 * Real Estate Receivable System
 * 
 * User authentication page
 */

// Define constants
define('DB_INCLUDE', true);
define('APP_NAME', 'Real Estate Receivable System');

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header('Location: ../dashboard.php');
    exit();
}

// Initialize variables
$error_message = '';
$username = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting check
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt'] = time();
    }
    
    // Check if account is locked out
    if (isset($_SESSION['lockout_until']) && time() < $_SESSION['lockout_until']) {
        $remaining = ceil(($_SESSION['lockout_until'] - time()) / 60);
        $error_message = "Too many failed login attempts. Please try again in {$remaining} minute(s).";
    } else {
        // Reset lockout if time has passed
        if (isset($_SESSION['lockout_until']) && time() >= $_SESSION['lockout_until']) {
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_attempt']);
            unset($_SESSION['lockout_until']);
        }
        
        // Get and sanitize input
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        // Validate input
        if (empty($username)) {
            $error_message = 'Please enter your username.';
        } elseif (empty($password)) {
            $error_message = 'Please enter your password.';
        } else {
            // Verify credentials using prepared statements
            $user = verify_credentials($pdo, $username, $password);
            
            if ($user) {
                // Check if user is a client and verify account status
                if ($user['role'] === 'client' && isset($user['client_id'])) {
                    // Fetch client account status
                    try {
                        $client_stmt = $pdo->prepare("SELECT account_status, rejection_reason FROM clients WHERE client_id = ?");
                        $client_stmt->execute([$user['client_id']]);
                        $client_data = $client_stmt->fetch();
                        
                        if ($client_data) {
                            if ($client_data['account_status'] === 'pending') {
                                // Account pending approval
                                $error_message = 'Your account is pending admin approval. Please wait for confirmation.';
                                
                                // Log attempt
                                log_audit($pdo, 'LOGIN_PENDING', 'user_id:' . $user['user_id'], 'Login attempt with pending account: ' . $username);
                                
                                $_SESSION['login_attempts']++;
                                $_SESSION['last_attempt'] = time();
                            } elseif ($client_data['account_status'] === 'rejected') {
                                // Account rejected
                                $error_message = 'Your account registration has been rejected. Please contact support for more information.';
                                
                                // Log attempt
                                log_audit($pdo, 'LOGIN_REJECTED', 'user_id:' . $user['user_id'], 'Login attempt with rejected account: ' . $username);
                                
                                $_SESSION['login_attempts']++;
                                $_SESSION['last_attempt'] = time();
                            } else {
                                // Account approved - proceed with login
                                // No action needed, $user is already populated
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Client status check error: " . $e->getMessage());
                        // Allow login to proceed if check fails (failsafe)
                    }
                }
                
                // If error_message is set, skip login
                if (!empty($error_message)) {
                    // Error already set above, don't proceed
                } else {
                    // Login successful - reset rate limiting
                    unset($_SESSION['login_attempts']);
                    unset($_SESSION['last_attempt']);
                    unset($_SESSION['lockout_until']);
                    
                    // Login user
                    login_user($user);
                    
                    // Log successful login to audit trail
                    log_audit($pdo, 'LOGIN', 'user_id:' . $user['user_id'], 'Successful login for user: ' . $username);
                    
                    // Set remember me cookie if checked (30 days)
                    if ($remember_me) {
                        setcookie('remember_username', $username, time() + (30 * 24 * 60 * 60), '/');
                    } else {
                        // Clear remember me cookie
                        setcookie('remember_username', '', time() - 3600, '/');
                    }
                    
                    // Set success message
                    set_flash_message('success', 'Welcome back, ' . htmlspecialchars($user['username']) . '!');
                    
                    // Redirect based on role
                    if ($user['role'] === 'client') {
                        // Clients go to their portal
                        $redirect = '../client/dashboard.php';
                    } else {
                        // Admin/Finance go to main dashboard or requested page
                        $redirect = $_SESSION['redirect_after_login'] ?? '../dashboard.php';
                    }
                    unset($_SESSION['redirect_after_login']);
                    
                    header('Location: ' . $redirect);
                    exit();
                }
            } else {
                // Login failed - increment attempt counter
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt'] = time();
                
                // Calculate lockout
                $attempts = $_SESSION['login_attempts'];
                if ($attempts >= 5) {
                    // Exponential backoff: 5 attempts = 15 min, 6-10 = 30 min, 10+ = 60 min
                    if ($attempts >= 10) {
                        $lockout_duration = 60 * 60; // 60 minutes
                    } elseif ($attempts >= 6) {
                        $lockout_duration = 30 * 60; // 30 minutes
                    } else {
                        $lockout_duration = 15 * 60; // 15 minutes
                    }
                    $_SESSION['lockout_until'] = time() + $lockout_duration;
                    $error_message = "Too many failed login attempts. Account locked for " . ($lockout_duration / 60) . " minutes.";
                } else {
                    $remaining_attempts = 5 - $attempts;
                    $error_message = "Invalid username or password. {$remaining_attempts} attempt(s) remaining.";
                }
                
                // Log failed login attempt
                try {
                    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, target, details, ip_address) VALUES (0, 'LOGIN_FAILED', ?, ?, ?)");
                    $stmt->execute([
                        'username:' . $username,
                        "Invalid credentials attempted (Attempt {$attempts})",
                        $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
                    ]);
                } catch (PDOException $e) {
                    error_log("Audit log error: " . $e->getMessage());
                }
                
                error_log("Failed login attempt for username: {$username} (Attempt {$attempts})");
            }
        }
    }
}

// Get remembered username if exists
$remembered_username = $_COOKIE['remember_username'] ?? '';
if (empty($username) && !empty($remembered_username)) {
    $username = $remembered_username;
}

// Check for timeout or security messages
$timeout_message = isset($_GET['timeout']) ? 'Your session has expired. Please login again.' : '';
$security_message = isset($_GET['security']) ? 'Security check failed. Please login again.' : '';
$logout_message = isset($_GET['logout']) ? 'You have been successfully logged out.' : '';
$registered_message = isset($_GET['registered']) ? 'Account created successfully! Your registration is pending admin approval.' : '';

$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . APP_NAME; ?></title>
    
    <!-- Bootstrap CSS (Offline) -->
    <link rel="stylesheet" href="../assets/bootstrap/bootstrap.min.css">
    
    <style>
        :root {
            --primary-maroon: #800000;
            --dark-maroon: #5c0000;
            --light-maroon: #a32929;
            --mulled-wine: #4B4359;
            --dark-wine: #352f40;
            --beige: #F5F5DD;
            --white: #ffffff;
        }
        
        body {
            background: linear-gradient(135deg, #F5F5DD 0%, #e8e8cc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            backdrop-filter: blur(10px);
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(245, 245, 221, 0.7) 0%, rgba(232, 232, 204, 0.7) 100%);
            filter: blur(8px);
            z-index: -1;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--mulled-wine) 0%, var(--dark-wine) 100%);
            color: var(--white);
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            color: var(--beige);
        }
        
        .login-header p {
            margin: 0;
            font-size: 0.9rem;
            color: #cccccc;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-label {
            color: var(--mulled-wine);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            border: none;
            color: var(--white);
            padding: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, var(--dark-maroon) 0%, var(--primary-maroon) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .remember-me {
            margin-top: 15px;
        }
        
        .remember-me label {
            font-weight: 500;
            color: #666;
            cursor: pointer;
        }
        
        .alert {
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        
        .login-footer {
            padding: 20px 30px;
            background-color: var(--beige);
            text-align: center;
            color: #666;
            font-size: 0.9rem;
        }
        
        .input-group-text {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            color: white;
            border: none;
            border-radius: 8px 0 0 8px;
        }
        
        .input-group .form-control {
            border-radius: 0 8px 8px 0;
        }

        .btn-home {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--mulled-wine);
            border: none;
            color: var(--white);
            padding: 12px 24px;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 30px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 100;
        }

        .btn-home:hover {
            background: var(--primary-maroon);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.3);
        }

        .back-link {
            color: var(--primary-maroon);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--dark-maroon);
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .btn-home {
                top: 10px;
                left: 10px;
                padding: 10px 18px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <!-- Back to Home Button - Fixed Position -->
    <a href="../catalog.php" class="btn-home">
        🏠 Browse Properties
    </a>
    <div class="login-container">
        <div class="login-card">
            <!-- Login Header -->
            <div class="login-header">
                <h1>🏢 RERS</h1>
                <p>Real Estate Receivable System</p>
            </div>
            
            <!-- Login Body -->
            <div class="login-body">
                <h4 class="text-center mb-4" style="color: var(--slate-gray); font-weight: 600;">
                    Welcome Back!
                </h4>
                
                <!-- System Messages -->
                <?php if (!empty($timeout_message)): ?>
                <div class="alert alert-warning" role="alert">
                    ⏰ <?php echo htmlspecialchars($timeout_message); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($security_message)): ?>
                <div class="alert alert-danger" role="alert">
                    🔒 <?php echo htmlspecialchars($security_message); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($logout_message)): ?>
                <div class="alert alert-success" role="alert">
                    ✅ <?php echo htmlspecialchars($logout_message); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($registered_message)): ?>
                <div class="alert alert-success" role="alert">
                    ✨ <?php echo htmlspecialchars($registered_message); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    ❌ <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" action="login.php" novalidate>
                    <!-- Username -->
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">👤</span>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="username" 
                                name="username" 
                                value="<?php echo htmlspecialchars($username); ?>"
                                placeholder="Enter your username"
                                required
                                autofocus>
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">🔒</span>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password" 
                                placeholder="Enter your password"
                                required>
                        </div>
                    </div>
                    
                    <!-- Remember Me -->
                    <div class="form-check remember-me">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            id="remember_me" 
                            name="remember_me"
                            <?php echo !empty($remembered_username) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="remember_me">
                            Remember my username
                        </label>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-login">
                        🚀 Login
                    </button>
                </form>
            </div>
            
            <!-- Login Footer -->
            <div class="login-footer">
                <p class="mb-2">
                    <a href="../catalog.php" class="back-link">🏠 Browse Property Catalog</a>
                </p>
                <p class="mb-2">
                    Don't have an account? 
                    <a href="register.php" style="color: var(--primary-maroon); font-weight: 600; text-decoration: none;">✨ Register here</a>
                </p>
                <p class="mb-0">
                    <strong>Default Credentials:</strong><br>
                    <small>Admin: admin / admin123 | Finance: finance / admin123</small>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS (Offline) -->
    <script src="../assets/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
