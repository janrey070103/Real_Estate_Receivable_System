<?php
/**
 * Client My Documents Page
 * Real Estate Receivable System - Phase 14
 * 
 * Client-facing page to view and download their documents
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Require client role
require_client();

$client_id = get_client_id();

try {
    // Fetch client data
    $client_stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $client_stmt->execute([$client_id]);
    $client = $client_stmt->fetch();

    if (!$client) {
        set_flash_message('error', 'Client profile not found.');
        header('Location: dashboard.php');
        exit();
    }

    // Fetch documents for this client
    $docs_stmt = $pdo->prepare("
        SELECT * FROM documents 
        WHERE client_id = ? 
        ORDER BY upload_date DESC
    ");
    $docs_stmt->execute([$client_id]);
    $documents = $docs_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("My documents error: " . $e->getMessage());
    set_flash_message('error', 'Failed to load documents.');
    $documents = [];
}

$page_title = 'My Documents';

include '../templates/client_header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">My Dashboard</a></li>
        <li class="breadcrumb-item active">My Documents</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h2>
                <span style="color: var(--primary-maroon);">📁</span>
                My Documents
            </h2>
            <p class="text-muted mb-0">Your contracts and uploaded files</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <span class="badge bg-light text-dark fs-6">
                <?php echo count($documents); ?> Documents
            </span>
        </div>
    </div>
</div>

<?php
$flash = get_flash_message();
if ($flash):
    $alert_class = $flash['type'] === 'success' ? 'alert-success' :
        ($flash['type'] === 'error' ? 'alert-danger' : 'alert-info');
    ?>
    <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Documents List -->
<div class="card">
    <div class="card-header">
        <span>📎</span> Uploaded Documents
    </div>
    <div class="card-body p-0">
        <?php if (count($documents) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Document</th>
                            <th>Upload Date</th>
                            <th style="width: 100px;">Action</th>
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
                                    <span style="font-size: 1.5rem;">
                                        <?php echo $icon; ?>
                                    </span>
                                    <strong>
                                        <?php echo htmlspecialchars($doc['file_name']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y g:i A', strtotime($doc['upload_date'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>"
                                        class="btn btn-sm btn-primary" download title="Download">
                                        ⬇️ Download
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div style="font-size: 4rem;">📁</div>
                <h5 class="text-muted">No Documents Yet</h5>
                <p class="text-muted">Your contracts and important documents will appear here once uploaded by an
                    administrator.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Info Box -->
<div class="alert alert-info mt-4" role="alert">
    <strong>ℹ️ Note:</strong> Documents are uploaded by the system administrator.
    If you need to submit or request documents, please contact your account manager.
</div>

</div>

<?php include '../templates/client_footer.php'; ?>