<?php
/**
 * Client Documents Page
 * Real Estate Receivable System
 * 
 * Upload and manage documents for a specific client
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Require user to be logged in
require_login();

// Get client ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid client ID.');
    header('Location: clients.php');
    exit();
}

$client_id = (int) $_GET['id'];

// Fetch client data
try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
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

// Handle document deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $doc_id = (int) $_GET['delete'];

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

            set_flash_message('success', 'Document deleted successfully!');
        } else {
            set_flash_message('error', 'Document not found.');
        }
    } catch (PDOException $e) {
        error_log("Delete document error: " . $e->getMessage());
        set_flash_message('error', 'Failed to delete document.');
    }

    header('Location: client_documents.php?id=' . $client_id);
    exit();
}

// Handle file upload
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $file = $_FILES['document'];

    // Validate file
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Check file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            $errors['file'] = 'File size cannot exceed 5MB.';
        }

        // Check file type
        $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_types)) {
            $errors['file'] = 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, JPEG, PNG.';
        }

        if (empty($errors)) {
            // Create upload directory if not exists
            $year = date('Y');
            $month = date('m');
            $upload_dir = "../uploads/documents/{$year}/{$month}/";

            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generate unique filename
            $unique_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $file_path = $upload_dir . $unique_name;
            $relative_path = "uploads/documents/{$year}/{$month}/" . $unique_name;

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO documents (client_id, file_name, file_path) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$client_id, $file['name'], $relative_path]);

                    set_flash_message('success', 'Document uploaded successfully!');
                    header('Location: client_documents.php?id=' . $client_id);
                    exit();

                } catch (PDOException $e) {
                    error_log("Upload document error: " . $e->getMessage());
                    unlink($file_path); // Delete file if database insert fails
                    $errors['file'] = 'Failed to save document information.';
                }
            } else {
                $errors['file'] = 'Failed to upload file.';
            }
        }
    } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors['file'] = 'File upload error occurred.';
    } else {
        $errors['file'] = 'Please select a file to upload.';
    }
}

// Fetch existing documents
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

$page_title = 'Client Documents - ' . $client['name'];

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
                    <li class="breadcrumb-item"><a
                            href="client_edit.php?id=<?php echo $client_id; ?>"><?php echo htmlspecialchars($client['name']); ?></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Documents</li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2>
                            <span style="color: var(--primary-blue);">📎</span> Documents
                        </h2>
                        <p class="text-muted mb-0">
                            Client: <strong><?php echo htmlspecialchars($client['name']); ?></strong>
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <a href="client_edit.php?id=<?php echo $client_id; ?>" class="btn btn-outline-secondary">
                            <span>◀</span> Back to Client
                        </a>
                    </div>
                </div>
            </div>

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
                <!-- Upload Form -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <span>📤</span> Upload Document
                        </div>
                        <div class="card-body">
                            <form method="POST" action="client_documents.php?id=<?php echo $client_id; ?>"
                                enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="document" class="form-label">Select File</label>
                                    <input type="file"
                                        class="form-control <?php echo isset($errors['file']) ? 'is-invalid' : ''; ?>"
                                        id="document" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                                    <?php if (isset($errors['file'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['file']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <small class="form-text text-muted">
                                        Max size: 5MB<br>
                                        Allowed: PDF, DOC, DOCX, JPG, PNG
                                    </small>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        ⬆️ Upload Document
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="alert alert-info mt-3" role="alert">
                        <strong>ℹ️ Document Tips:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Upload contracts, IDs, or supporting documents</li>
                            <li>Files are stored securely</li>
                            <li>You can delete documents anytime</li>
                        </ul>
                    </div>
                </div>

                <!-- Documents List -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><span>📁</span> Uploaded Documents</span>
                            <span class="badge bg-light text-dark"><?php echo count($documents); ?> Documents</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($documents) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>File Name</th>
                                                <th>Upload Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($documents as $doc): ?>
                                                <?php
                                                $file_ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                                $icon = '📄';
                                                if ($file_ext === 'pdf')
                                                    $icon = '📕';
                                                elseif (in_array($file_ext, ['jpg', 'jpeg', 'png']))
                                                    $icon = '🖼️';
                                                elseif (in_array($file_ext, ['doc', 'docx']))
                                                    $icon = '📘';
                                                ?>
                                                <tr>
                                                    <td>
                                                        <span style="font-size: 1.5rem;"><?php echo $icon; ?></span>
                                                        <strong><?php echo htmlspecialchars($doc['file_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo date('M d, Y g:i A', strtotime($doc['upload_date'])); ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>"
                                                                class="btn btn-outline-primary" download title="Download">
                                                                ⬇️
                                                            </a>
                                                            <a href="client_documents.php?id=<?php echo $client_id; ?>&delete=<?php echo $doc['doc_id']; ?>"
                                                                class="btn btn-outline-danger" title="Delete"
                                                                onclick="return confirm('Are you sure you want to delete this document?');">
                                                                🗑️
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">📁</div>
                                    <h5 class="text-muted">No Documents Uploaded</h5>
                                    <p class="text-muted mb-3">Upload your first document using the form on the left.</p>
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