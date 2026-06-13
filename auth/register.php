<?php
/**
 * Client Self-Registration Page
 * Real Estate Receivable System
 * 
 * Allows prospective clients to create their own account
 */

// Define constants
define('DB_INCLUDE', true);
define('APP_NAME', 'Real Estate Receivable System');

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/validation_helpers.php';

// If already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    if (is_client()) {
        header('Location: ../client/dashboard.php');
    } else {
        header('Location: ../dashboard.php');
    }
    exit();
}

// Initialize variables
$name = '';
$email = '';
$contact_no = '';
$address = '';
$username = '';
$property_interest = '';
$errors = [];
$success_message = '';

// Get property ID from URL (if coming from catalog)
$property_id = isset($_GET['property']) ? (int)$_GET['property'] : 0;
$property_name = '';

// Fetch property details if specified
if ($property_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT property_name FROM properties WHERE property_id = ? AND status = 'available'");
        $stmt->execute([$property_id]);
        $property = $stmt->fetch();
        if ($property) {
            $property_name = $property['property_name'];
        }
    } catch (PDOException $e) {
        error_log("Property fetch error: " . $e->getMessage());
    }
}

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token. Please try again.';
    } else {
        // Get and sanitize input
        $name = sanitize_input($_POST['name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $contact_no = sanitize_input($_POST['contact_no'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $property_interest = sanitize_input($_POST['property_interest'] ?? '');

        // Validation
        if (empty($name)) {
            $errors['name'] = 'Full name is required.';
        } elseif (strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        } elseif (strlen($name) > 100) {
            $errors['name'] = 'Name cannot exceed 100 characters.';
        }

        if (empty($email)) {
            $errors['email'] = 'Email address is required.';
        } elseif (!validate_email($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            // Check if email already exists in clients
            try {
                $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'This email is already registered. Please login or use a different email.';
                }
            } catch (PDOException $e) {
                error_log("Email check error: " . $e->getMessage());
            }
        }

        if (empty($contact_no)) {
            $errors['contact_no'] = 'Contact number is required.';
        } elseif (!validate_philippine_phone($contact_no, false)) {
            $errors['contact_no'] = 'Please enter a valid Philippine mobile number (e.g., 09171234567).';
        } else {
            $contact_no = normalize_phone($contact_no, 'compact');
        }

        if (empty($username)) {
            $errors['username'] = 'Username is required.';
        } elseif (strlen($username) < 4) {
            $errors['username'] = 'Username must be at least 4 characters.';
        } elseif (strlen($username) > 50) {
            $errors['username'] = 'Username cannot exceed 50 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors['username'] = 'Username can only contain letters, numbers, and underscores.';
        } else {
            // Check if username already exists
            try {
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $errors['username'] = 'This username is already taken. Please choose another.';
                }
            } catch (PDOException $e) {
                error_log("Username check error: " . $e->getMessage());
            }
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        if (empty($confirm_password)) {
            $errors['confirm_password'] = 'Please confirm your password.';
        } elseif ($password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        // If no errors, create account
        if (empty($errors)) {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // 1. Create client record with 'pending' status (requires admin approval)
                $stmt = $pdo->prepare("
                    INSERT INTO clients (name, email, contact_no, address, account_status) 
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $name,
                    $email,
                    $contact_no,
                    !empty($address) ? $address : null
                ]);
                $client_id = $pdo->lastInsertId();

                // 2. Create user account linked to client
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, role, client_id) 
                    VALUES (?, ?, 'client', ?)
                ");
                $stmt->execute([$username, $password_hash, $client_id]);
                $user_id = $pdo->lastInsertId();

                // 3. If they expressed interest in a property, create an inquiry record
                if (!empty($property_interest) && $property_id > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO inquiries (property_id, client_id, name, email, contact_no, message, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $property_id,
                        $client_id,
                        $name,
                        $email,
                        $contact_no,
                        "Registered with interest to avail: " . $property_interest
                    ]);
                }

                // 4. Log the action
                log_audit($pdo, 'CLIENT_SELF_REGISTER', "client_id:$client_id,user_id:$user_id", "Client self-registered: $name ($username)");

                // Commit transaction
                $pdo->commit();

                // Set success message and redirect to login
                set_flash_message('success', "Account created successfully! Your registration is pending admin approval. You'll be notified once approved.");
                header('Location: login.php?registered=1&username=' . urlencode($username));
                exit();

            } catch (PDOException $e) {
                // Rollback on error
                $pdo->rollBack();
                error_log("Registration error: " . $e->getMessage());
                $errors['general'] = 'Failed to create account. Please try again.';
            }
        }
    }
}

$page_title = 'Create Account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../assets/bootstrap/bootstrap.min.css">
    
    <style>
        :root {
            --primary-maroon: #800000;
            --dark-maroon: #5c0000;
            --mulled-wine: #4B4359;
            --beige: #F5F5DD;
        }
        
        body {
            background: linear-gradient(135deg, #F5F5DD 0%, #e8e8cc 100%);
            min-height: 100vh;
            padding: 40px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .register-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, var(--mulled-wine) 0%, var(--dark-maroon) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .register-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            color: var(--beige);
        }
        
        .register-body {
            padding: 30px;
        }
        
        .form-label {
            color: var(--mulled-wine);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
        }
        
        .btn-register {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            border: none;
            color: white;
            padding: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, var(--dark-maroon) 0%, var(--primary-maroon) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.4);
        }
        
        .alert {
            border-radius: 8px;
            padding: 12px 15px;
        }
        
        .register-footer {
            padding: 20px 30px;
            background-color: var(--beige);
            text-align: center;
        }

        .btn-back {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--mulled-wine);
            border: none;
            color: white;
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

        .btn-back:hover {
            background: var(--primary-maroon);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.3);
        }

        .property-interest-box {
            background: #f8f9fa;
            border: 2px solid var(--primary-maroon);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="../catalog.php" class="btn-back">
        ← Back to Catalog
    </a>

    <div class="register-container">
        <div class="register-card">
            <!-- Header -->
            <div class="register-header">
                <h1>🏢 Register to Purchase Property</h1>
                <p class="mb-0">Create your account to start the acquisition process</p>
            </div>
            
            <!-- Body -->
            <div class="register-body">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger">
                        ❌ <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($property_name)): ?>
                    <div class="property-interest-box">
                        <h6 class="mb-1" style="color: var(--primary-maroon);">🏠 Property You Want to Avail</h6>
                        <p class="mb-0"><strong><?php echo htmlspecialchars($property_name); ?></strong></p>
                        <small class="text-muted">Complete registration to start the purchase process</small>
                    </div>
                <?php endif; ?>
                
                <!-- Registration Form -->
                <form method="POST" action="register.php<?php echo $property_id > 0 ? '?property=' . $property_id : ''; ?>" novalidate>
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <?php if ($property_id > 0 && !empty($property_name)): ?>
                        <input type="hidden" name="property_interest" value="<?php echo htmlspecialchars($property_name); ?>">
                    <?php endif; ?>

                    <h5 class="mb-3">Personal Information</h5>

                    <!-- Full Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                            class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                            id="name" name="name" 
                            value="<?php echo htmlspecialchars($name); ?>" 
                            placeholder="Juan Dela Cruz" required autofocus>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            Email Address <span class="text-danger">*</span>
                        </label>
                        <input type="email" 
                            class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                            id="email" name="email" 
                            value="<?php echo htmlspecialchars($email); ?>" 
                            placeholder="juan@example.com" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Contact Number -->
                    <div class="mb-3">
                        <label for="contact_no" class="form-label">
                            Contact Number <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                            class="form-control <?php echo isset($errors['contact_no']) ? 'is-invalid' : ''; ?>" 
                            id="contact_no" name="contact_no" 
                            value="<?php echo htmlspecialchars($contact_no); ?>" 
                            placeholder="09171234567" required>
                        <?php if (isset($errors['contact_no'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['contact_no']); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Address -->
                    <div class="mb-3">
                        <label for="address" class="form-label">Address (Optional)</label>
                        <textarea class="form-control" id="address" name="address" rows="2" 
                            placeholder="Complete address"><?php echo htmlspecialchars($address); ?></textarea>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Account Credentials</h5>

                    <!-- Username -->
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            Username <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                            class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                            id="username" name="username" 
                            value="<?php echo htmlspecialchars($username); ?>" 
                            placeholder="Choose a username" required>
                        <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['username']); ?></div>
                        <?php endif; ?>
                        <small class="text-muted">Letters, numbers, and underscores only (4-50 characters)</small>
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            Password <span class="text-danger">*</span>
                        </label>
                        <input type="password" 
                            class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                            id="password" name="password" 
                            placeholder="Create a secure password" required>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
                        <?php endif; ?>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">
                            Confirm Password <span class="text-danger">*</span>
                        </label>
                        <input type="password" 
                            class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                            id="confirm_password" name="confirm_password" 
                            placeholder="Re-enter your password" required>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-register mt-3">
                        ✨ Create Account
                    </button>
                </form>
            </div>
            
            <!-- Footer -->
            <div class="register-footer">
                <p class="mb-0">
                    Already have an account? 
                    <a href="login.php" style="color: var(--primary-maroon); font-weight: 600;">Login here</a>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="../assets/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
