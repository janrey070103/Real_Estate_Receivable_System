<?php
/**
 * Edit Client Page
 * Real Estate Receivable System
 * 
 * Form to edit existing client with validation
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/validation_helpers.php';

// Require user to be logged in
require_login();

// Set page title
$page_title = 'Edit Client';

// Get client ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid client ID.');
    header('Location: clients.php');
    exit();
}

$client_id = (int) $_GET['id'];
$errors = [];
$upload_errors = [];

// Handle document deletion
if (isset($_GET['delete_doc']) && is_numeric($_GET['delete_doc'])) {
    $doc_id = (int) $_GET['delete_doc'];

    try {
        // Get document info
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE doc_id = ? AND client_id = ?");
        $stmt->execute([$doc_id, $client_id]);
        $doc = $stmt->fetch();

        if ($doc) {
            // Delete file from filesystem
            $file_path = '../' . $doc['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM documents WHERE doc_id = ?");
            $stmt->execute([$doc_id]);

            // Log document deletion
            log_audit($pdo, 'DELETE_DOCUMENT', 'doc_id:' . $doc_id, 'Deleted document: ' . $doc['file_name'] . ' for client_id: ' . $client_id);

            set_flash_message('success', 'Document deleted successfully!');
        }
    } catch (PDOException $e) {
        error_log("Delete document error: " . $e->getMessage());
        set_flash_message('error', 'Failed to delete document.');
    }

    header('Location: client_edit.php?id=' . $client_id);
    exit();
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['contract_file'])) {
    $file = $_FILES['contract_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        // Validate file size (10MB max)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            $upload_errors['file'] = 'File size cannot exceed 10MB.';
        }

        // Validate file type
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_types)) {
            $upload_errors['file'] = 'Invalid file type. Only PDF, JPG, and PNG are allowed.';
        }

        // Validate actual MIME type for security
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($mime_type, $allowed_mimes)) {
            $upload_errors['file'] = 'Invalid file content. Only PDF and image files are allowed.';
        }

        if (empty($upload_errors)) {
            // Create upload directory for this client
            $upload_dir = "../uploads/clients/{$client_id}/";

            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generate unique filename
            $unique_name = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $file_path = $upload_dir . $unique_name;
            $relative_path = "uploads/clients/{$client_id}/" . $unique_name;

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO documents (client_id, file_name, file_path) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$client_id, $file['name'], $relative_path]);

                    // Log document upload
                    log_audit($pdo, 'UPLOAD_DOCUMENT', 'client_id:' . $client_id, 'Uploaded document: ' . $file['name']);

                    set_flash_message('success', 'Contract file uploaded successfully!');
                    header('Location: client_edit.php?id=' . $client_id);
                    exit();

                } catch (PDOException $e) {
                    error_log("Upload document error: " . $e->getMessage());
                    unlink($file_path); // Delete file if database insert fails
                    $upload_errors['file'] = 'Failed to save document information.';
                }
            } else {
                $upload_errors['file'] = 'Failed to upload file.';
            }
        }
    } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_errors['file'] = 'File upload error occurred.';
    }
}

// Fetch client data
try {
    $stmt = $pdo->prepare("
        SELECT c.*,
               COUNT(DISTINCT p.property_id) as property_count,
               COALESCE(SUM(p.total_price), 0) as total_value,
               COUNT(DISTINCT i.invoice_id) as invoice_count,
               COALESCE(SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END), 0) as paid_invoices,
               COALESCE(SUM(CASE WHEN i.status = 'unpaid' THEN 1 ELSE 0 END), 0) as unpaid_invoices
        FROM clients c
        LEFT JOIN properties p ON c.client_id = p.client_id
        LEFT JOIN invoices i ON p.property_id = i.property_id
        WHERE c.client_id = ?
        GROUP BY c.client_id
    ");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    if (!$client) {
        set_flash_message('error', 'Client not found.');
        header('Location: clients.php');
        exit();
    }

} catch (PDOException $e) {
    error_log("Fetch client error: " . $e->getMessage());
    set_flash_message('error', 'Failed to load client data.');
    header('Location: clients.php');
    exit();
}

// Process form submission (skip if uploading file)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['contract_file'])) {
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
                // Check if email already exists for other clients
                try {
                    $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE email = ? AND client_id != ?");
                    $stmt->execute([$email, $client_id]);
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

        // If no errors, update client
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                UPDATE clients 
                SET name = ?, email = ?, contact_no = ?, address = ?
                WHERE client_id = ?
            ");

                $stmt->execute([
                    $name,
                    !empty($email) ? $email : null,
                    !empty($contact_no) ? $contact_no : null,
                    !empty($address) ? $address : null,
                    $client_id
                ]);

                // Log client update
                log_audit($pdo, 'UPDATE_CLIENT', 'client_id:' . $client_id, 'Updated client: ' . $name);

                set_flash_message('success', "Client '{$name}' updated successfully!");
                header('Location: client_edit.php?id=' . $client_id);
                exit();

            } catch (PDOException $e) {
                error_log("Update client error: " . $e->getMessage());
                $errors['general'] = 'Failed to update client. Please try again.';
            }
        } else {
            // Update client array with submitted values for form display
            $client['name'] = $name;
            $client['email'] = $email;
            $client['contact_no'] = $contact_no;
            $client['address'] = $address;
        }
    }
}

// Fetch uploaded documents
try {
    $stmt = $pdo->prepare("
        SELECT * FROM documents 
        WHERE client_id = ? 
        ORDER BY upload_date DESC
    ");
    $stmt->execute([$client_id]);
    $documents = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Fetch documents error: " . $e->getMessage());
    $documents = [];
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
                    <li class="breadcrumb-item active" aria-current="page">
                        <?php echo htmlspecialchars($client['name']); ?></li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2>
                            <span style="color: var(--primary-blue);">✏️</span> Edit Client
                        </h2>
                        <p class="text-muted mb-0">Update client information and view statistics</p>
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

            <?php
            $flash = get_flash_message();
            if ($flash):
                $alert_class = 'alert-info';
                if ($flash['type'] === 'success')
                    $alert_class = 'alert-success';
                if ($flash['type'] === 'error')
                    $alert_class = 'alert-danger';
                if ($flash['type'] === 'warning')
                    $alert_class = 'alert-warning';
                ?>
                <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
                    <strong><?php echo ucfirst($flash['type']); ?>!</strong>
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Client Statistics -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <span>📊</span> Client Statistics
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-12">
                                    <div class="p-3 bg-light rounded">
                                        <h6 class="text-muted mb-1">Client ID</h6>
                                        <h3 style="color: var(--primary-blue); margin: 0;">
                                            #<?php echo $client['client_id']; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="text-muted mb-0">Total Properties</h6>
                                    <h4 class="mb-0" style="color: var(--primary-blue);">
                                        <?php echo number_format($client['property_count']); ?></h4>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="text-muted mb-0">Total Contract Value</h6>
                                    <h5 class="mb-0" style="color: var(--primary-blue);">
                                        ₱<?php echo number_format($client['total_value'], 2); ?></h5>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">Invoices</h6>
                                <div class="d-flex justify-content-between">
                                    <span>🟢 Paid: <strong
                                            class="text-success"><?php echo $client['paid_invoices']; ?></strong></span>
                                    <span>🟡 Unpaid: <strong
                                            class="text-warning"><?php echo $client['unpaid_invoices']; ?></strong></span>
                                </div>
                            </div>
                            <hr>
                            <div>
                                <h6 class="text-muted mb-1">Member Since</h6>
                                <p class="mb-0">
                                    <strong><?php echo date('F d, Y', strtotime($client['created_at'])); ?></strong></p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <span>⚡</span> Quick Actions
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="client_properties.php?id=<?php echo $client_id; ?>"
                                    class="btn btn-outline-primary">
                                    <span>🏘️</span> View Properties
                                </a>
                                <a href="client_documents.php?id=<?php echo $client_id; ?>"
                                    class="btn btn-outline-secondary">
                                    <span>📎</span> Upload Document
                                </a>
                                <hr class="my-2">
                                <a href="clients.php?delete=<?php echo $client_id; ?>" class="btn btn-outline-danger"
                                    onclick="return confirm('Are you sure you want to delete this client? This action cannot be undone.');">
                                    <span>🗑️</span> Delete Client
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Form -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <span>📝</span> Client Information
                        </div>
                        <div class="card-body">
                            <form method="POST" action="client_edit.php?id=<?php echo $client_id; ?>" novalidate>
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                                <!-- Name -->
                                <div class="mb-3">
                                    <label for="name" class="form-label">
                                        Client Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text"
                                        class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>"
                                        id="name" name="name" value="<?php echo htmlspecialchars($client['name']); ?>"
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
                                    <input type="email"
                                        class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                        id="email" name="email"
                                        value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>"
                                        placeholder="client@example.com">
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['email']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <small class="form-text text-muted">Optional - for sending invoices and
                                        notifications</small>
                                </div>

                                <!-- Contact Number -->
                                <div class="mb-3">
                                    <label for="contact_no" class="form-label">Contact Number</label>
                                    <input type="text"
                                        class="form-control <?php echo isset($errors['contact_no']) ? 'is-invalid' : ''; ?>"
                                        id="contact_no" name="contact_no"
                                        value="<?php echo htmlspecialchars($client['contact_no'] ?? ''); ?>"
                                        placeholder="09171234567">
                                    <?php if (isset($errors['contact_no'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['contact_no']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <small class="form-text text-muted">Optional - mobile or landline number</small>
                                </div>

                                <!-- Address -->
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea
                                        class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>"
                                        id="address" name="address" rows="3"
                                        placeholder="Enter complete address"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
                                    <?php if (isset($errors['address'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['address']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <small class="form-text text-muted">Optional - client's physical address (max 500
                                        characters)</small>
                                </div>

                                <hr class="my-4">

                                <!-- Last Updated Info -->
                                <div class="alert alert-light mb-3">
                                    <small class="text-muted">
                                        <strong>Last Updated:</strong>
                                        <?php echo date('F d, Y \a\t g:i A', strtotime($client['updated_at'])); ?>
                                    </small>
                                </div>

                                <!-- Form Actions -->
                                <div class="d-flex justify-content-between">
                                    <a href="clients.php" class="btn btn-outline-secondary">
                                        ✖ Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        ✓ Update Client
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Contract Documents Section -->
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><span>📄</span> Contract Documents</span>
                            <span class="badge bg-light text-dark"><?php echo count($documents); ?> Files</span>
                        </div>
                        <div class="card-body">
                            <!-- Upload Form -->
                            <div class="mb-4">
                                <h6 class="mb-3">📄 Upload Contract File</h6>
                                <?php if (!empty($upload_errors)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <strong>Upload Error!</strong>
                                        <?php echo htmlspecialchars($upload_errors['file'] ?? 'Unknown error'); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="client_edit.php?id=<?php echo $client_id; ?>"
                                    enctype="multipart/form-data">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <input type="file" class="form-control" id="contract_file"
                                                name="contract_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                            <small class="form-text text-muted">
                                                Allowed: PDF, JPG, PNG | Max size: 10MB
                                            </small>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <span>⬆️</span> Upload File
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <hr>

                            <!-- Uploaded Documents List -->
                            <h6 class="mb-3">📁 Uploaded Files</h6>
                            <?php if (count($documents) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($documents as $doc): ?>
                                        <?php
                                        $file_ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                        $icon = '📄';
                                        $badge_color = 'secondary';
                                        if ($file_ext === 'pdf') {
                                            $icon = '📝';
                                            $badge_color = 'danger';
                                        } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                                            $icon = '🖼️';
                                            $badge_color = 'info';
                                        }
                                        ?>
                                        <div class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between align-items-center">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <span style="font-size: 1.2rem;"><?php echo $icon; ?></span>
                                                        <?php echo htmlspecialchars($doc['file_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        📅 Uploaded:
                                                        <?php echo date('M d, Y g:i A', strtotime($doc['upload_date'])); ?>
                                                    </small>
                                                </div>
                                                <div class="ms-3">
                                                    <span
                                                        class="badge bg-<?php echo $badge_color; ?> me-2"><?php echo strtoupper($file_ext); ?></span>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>"
                                                            class="btn btn-outline-primary" download title="Download"
                                                            data-bs-toggle="tooltip">
                                                            ⬇️
                                                        </a>
                                                        <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>"
                                                            class="btn btn-outline-info" target="_blank" title="View"
                                                            data-bs-toggle="tooltip">
                                                            👁️
                                                        </a>
                                                        <a href="client_edit.php?id=<?php echo $client_id; ?>&delete_doc=<?php echo $doc['doc_id']; ?>"
                                                            class="btn btn-outline-danger" title="Delete"
                                                            data-bs-toggle="tooltip"
                                                            onclick="return confirm('Are you sure you want to delete this document?');">
                                                            🗑️
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state" style="padding: 2rem 1rem;">
                                    <div class="empty-icon" style="font-size: 3rem;">📁</div>
                                    <p class="text-muted mb-0">No contract files uploaded yet. Upload a contract document
                                        above.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php
    // Include footer
    include '../templates/footer.php';
    ?>