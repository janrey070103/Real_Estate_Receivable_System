<?php
/**
 * Add New Client Page
 * Real Estate Receivable System
 * 
 * Form to add a new client with validation
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/validation_helpers.php';

// Require login and check module access (finance or admin)
require_module_access('clients');

// Set page title
$page_title = 'Add New Client';

// Initialize variables
// Initialize variables with optional pre-fill from URL
$name = isset($_GET['name']) ? sanitize_input($_GET['name']) : '';
$email = isset($_GET['email']) ? sanitize_input($_GET['email']) : '';
$contact_no = isset($_GET['contact']) ? sanitize_input($_GET['contact']) : '';
$inquiry_id = isset($_GET['inquiry_id']) ? (int) $_GET['inquiry_id'] : 0;
$address = '';
$errors = [];

// Process form submission
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

        // Validation
        if (empty($name)) {
            $errors['name'] = 'Client name is required.';
        } elseif (strlen($name) < 2) {
            $errors['name'] = 'Client name must be at least 2 characters.';
        } elseif (strlen($name) > 100) {
            $errors['name'] = 'Client name cannot exceed 100 characters.';
        }

        if (!empty($email)) {
            // Enhanced email validation
            if (!validate_email($email)) {
                $errors['email'] = 'Please enter a valid email address.';
            } elseif (strlen($email) > 100) {
                $errors['email'] = 'Email cannot exceed 100 characters.';
            } else {
                // Check if email already exists
                try {
                    $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $errors['email'] = 'This email is already registered to another client.';
                    }
                } catch (PDOException $e) {
                    error_log("Email check error: " . $e->getMessage());
                }
            }
        }

        if (!empty($contact_no)) {
            // Validate Philippine phone number format
            if (!validate_philippine_phone($contact_no, false)) {
                $errors['contact_no'] = 'Please enter a valid Philippine mobile number (e.g., 09171234567 or +639171234567).';
            } else {
                // Normalize to compact format for storage
                $contact_no = normalize_phone($contact_no, 'compact');
            }
        }

        if (!empty($address) && strlen($address) > 500) {
            $errors['address'] = 'Address cannot exceed 500 characters.';
        }

        // If no errors, insert client
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                INSERT INTO clients (name, email, contact_no, address, account_status) 
                VALUES (?, ?, ?, ?, 'approved')
            ");

                $stmt->execute([
                    $name,
                    !empty($email) ? $email : null,
                    !empty($contact_no) ? $contact_no : null,
                    !empty($address) ? $address : null
                ]);

                $new_client_id = $pdo->lastInsertId();

                // Link back to Inquiry if this was a conversion
                $inquiry_id = isset($_POST['inquiry_id']) ? (int) $_POST['inquiry_id'] : 0;
                if ($inquiry_id > 0) {
                    try {
                        $link_stmt = $pdo->prepare("UPDATE inquiries SET client_id = ? WHERE inquiry_id = ? AND status = 'converted'");
                        $link_stmt->execute([$new_client_id, $inquiry_id]);
                        log_audit($pdo, 'LINK_INQUIRY_CLIENT', "inquiry_id:$inquiry_id,client_id:$new_client_id", "Linked inquiry #$inquiry_id to new client: $name");
                    } catch (PDOException $e) {
                        error_log("Inquiry link error: " . $e->getMessage());
                        // Non-fatal, continue
                    }
                }

                // Log the action to audit trail
                log_audit($pdo, 'ADD_CLIENT', 'client_id:' . $new_client_id, 'Added new client: ' . $name);

                set_flash_message('success', "Client '{$name}' added successfully!");
                header('Location: client_edit.php?id=' . $new_client_id);
                exit();

            } catch (PDOException $e) {
                error_log("Add client error: " . $e->getMessage());
                $errors['general'] = 'Failed to add client. Please try again.';
            }
        }
    }
}

// Include header
include '../templates/header.php';
?>

<!-- Include Navigation -->
<?php include '../templates/sidebar.php'; ?>

<!-- Main Content Wrapper -->
<div class="main-wrapper">
    <div class="main-content">
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="clients.php">Clients</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add New Client</li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2>
                            <span style="color: var(--primary-blue);">➕</span> Add New Client
                        </h2>
                        <p class="text-muted mb-0">Enter client information to create a new record</p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <a href="clients.php" class="btn btn-outline-secondary">
                            <span>◀</span> Back to Clients
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo htmlspecialchars($errors['general']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Add Client Form -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card">
                        <div class="card-header">
                            <span>📝</span> Client Information
                        </div>
                        <div class="card-body">
                            <form method="POST" action="client_add.php" novalidate>
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <!-- Inquiry Link -->
                                <?php if ($inquiry_id > 0): ?>
                                    <input type="hidden" name="inquiry_id" value="<?php echo $inquiry_id; ?>">
                                <?php endif; ?>

                                <!-- Name -->
                                <div class="mb-3">
                                    <label for="name" class="form-label">
                                        Client Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text"
                                        class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>"
                                        id="name" name="name" value="<?php echo htmlspecialchars($name); ?>"
                                        placeholder="Enter client's full name" required autofocus>
                                    <?php if (isset($errors['name'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['name']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <small class="form-text text-muted">Full name of the client (2-100
                                        characters)</small>
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text">📧</span>
                                        <input type="email"
                                            class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                            id="email" name="email" value="<?php echo htmlspecialchars($email); ?>"
                                            placeholder="client@example.com">
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['email']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="form-text text-muted">Optional - for sending invoices and
                                        notifications</small>
                                </div>

                                <!-- Contact Number -->
                                <div class="mb-3">
                                    <label for="contact_no" class="form-label">Contact Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">📞</span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['contact_no']) ? 'is-invalid' : ''; ?>"
                                            id="contact_no" name="contact_no"
                                            value="<?php echo htmlspecialchars($contact_no); ?>"
                                            placeholder="09171234567">
                                        <?php if (isset($errors['contact_no'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['contact_no']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="form-text text-muted">Optional - mobile or landline number</small>
                                </div>

                                <!-- Address -->
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea
                                        class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>"
                                        id="address" name="address" rows="3"
                                        placeholder="Enter complete address"><?php echo htmlspecialchars($address); ?></textarea>
                                    <?php if (isset($errors['address'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['address']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <small class="form-text text-muted">Optional - client's physical address (max 500
                                        characters)</small>
                                </div>

                                <hr class="my-4">

                                <!-- Form Actions -->
                                <div class="d-flex justify-content-between">
                                    <a href="clients.php" class="btn btn-outline-secondary">
                                        ✖ Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        ✓ Save Client
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Help Text -->
                    <div class="alert alert-info mt-3" role="alert">
                        <strong>ℹ️ Quick Tips:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Only the client name is required</li>
                            <li>Email must be unique if provided</li>
                            <li>You can add properties and documents after creating the client</li>
                            <li>All fields can be updated later</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php
    // Include footer
    include '../templates/footer.php';
    ?>