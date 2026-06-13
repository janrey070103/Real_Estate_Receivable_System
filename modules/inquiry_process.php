<?php
/**
 * Process Inquiry Page
 * Real Estate Receivable System
 * 
 * Detailed view to update status/notes for a specific inquiry
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

require_role(['admin', 'finance']);

$inquiry_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$inquiry_id) {
    header('Location: inquiries.php');
    exit;
}

// Fetch Inquiry Details with client information
$stmt = $pdo->prepare("
    SELECT i.*, p.property_name, p.total_price, p.status as property_status,
           c.name as client_name, c.account_status
    FROM inquiries i
    JOIN properties p ON i.property_id = p.property_id
    LEFT JOIN clients c ON i.client_id = c.client_id
    WHERE i.inquiry_id = ?
");
$stmt->execute([$inquiry_id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    set_flash_message('error', 'Inquiry not found.');
    header('Location: inquiries.php');
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $notes = trim($_POST['admin_notes']);

    // Update Inquiry
    $update_sql = "UPDATE inquiries SET status = ?, admin_notes = ? WHERE inquiry_id = ?";
    $stmt = $pdo->prepare($update_sql);

    if ($stmt->execute([$status, $notes, $inquiry_id])) {
        set_flash_message('success', 'Inquiry updated successfully.');

        // If "Contacted", check if client is approved before redirecting to sale
        if ($status === 'contacted' && !empty($inquiry['client_id'])) {
            if ($inquiry['account_status'] === 'approved') {
                // Client is approved - go directly to property sale
                set_flash_message('info', 'Redirecting to property sale workflow for this client...');
                header("Location: property_sell.php?property_id={$inquiry['property_id']}&client_id={$inquiry['client_id']}&inquiry_id={$inquiry_id}");
                exit;
            } elseif ($inquiry['account_status'] === 'pending') {
                // Client is pending - redirect to approval page first
                set_flash_message('warning', 'This client is pending approval. Please approve the client registration first before proceeding with the sale.');
                header("Location: client_approvals.php?filter=pending&highlight={$inquiry['client_id']}");
                exit;
            }
        }

        // If "Converted", redirect to Add Client page with pre-filled data
        if ($status === 'converted' && isset($_POST['redirect_add_client'])) {
            $query = http_build_query([
                'name' => $inquiry['name'],
                'email' => $inquiry['email'],
                'contact' => $inquiry['contact_no'],
                'inquiry_id' => $inquiry_id  // Link for backfill
            ]);
            header("Location: client_add.php?$query");
            exit;
        }
    } else {
        set_flash_message('error', 'Error updating inquiry.');
    }

    // Refresh page
    header("Location: inquiry_process.php?id=$inquiry_id");
    exit;
}

$page_title = 'Process Inquiry';
include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="inquiries.php" class="text-decoration-none text-muted mb-2 d-inline-block">← Back to
                        Inquiries</a>
                    <h2 style="color: var(--slate-gray); font-weight: 700;">Process Inquiry #
                        <?php echo $inquiry_id; ?>
                    </h2>
                </div>
            </div>

            <?php
            $flash = get_flash_message();
            if ($flash): ?>
                <div
                    class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Inquiry Details -->
                <div class="col-md-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 text-primary">Inquiry Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4 text-muted">Property:</div>
                                <div class="col-md-8 fw-bold">
                                    <?php echo htmlspecialchars($inquiry['property_name']); ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 text-muted">Inquirer Name:</div>
                                <div class="col-md-8 fw-bold fs-5">
                                    <?php echo htmlspecialchars($inquiry['name']); ?>
                                    <?php if (!empty($inquiry['client_id'])): ?>
                                        <span class="badge bg-success ms-2">✓ Registered Client #<?php echo $inquiry['client_id']; ?></span>
                                        <?php if ($inquiry['account_status'] === 'approved'): ?>
                                            <span class="badge bg-primary ms-1">✓ Approved</span>
                                        <?php elseif ($inquiry['account_status'] === 'pending'): ?>
                                            <span class="badge bg-warning ms-1">⏳ Pending Approval</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 text-muted">Contact Info:</div>
                                <div class="col-md-8">
                                    <div>📧 <a href="mailto:<?php echo htmlspecialchars($inquiry['email']); ?>">
                                            <?php echo htmlspecialchars($inquiry['email']); ?>
                                        </a></div>
                                    <div>📱
                                        <?php echo htmlspecialchars($inquiry['contact_no']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 text-muted">Message:</div>
                                <div class="col-md-8">
                                    <div class="p-3 bg-light rounded border">
                                        <?php echo nl2br(htmlspecialchars($inquiry['message'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 text-muted">Received:</div>
                                <div class="col-md-8">
                                    <?php echo date('F d, Y h:i A', strtotime($inquiry['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Panel -->
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 text-primary">Update Status</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Current Status</label>
                                    <select name="status" class="form-select" id="statusSelect">
                                        <option value="pending" <?php echo $inquiry['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="contacted" <?php echo $inquiry['status'] === 'contacted' ? 'selected' : ''; ?>>Contacted (Ready to Sell)</option>
                                        <option value="converted" <?php echo $inquiry['status'] === 'converted' ? 'selected' : ''; ?>>Converted (Sold)</option>
                                        <option value="closed" <?php echo $inquiry['status'] === 'closed' ? 'selected' : ''; ?>>Closed (Not Interested)</option>
                                    </select>
                                    <?php if (!empty($inquiry['client_id'])): ?>
                                        <?php if ($inquiry['account_status'] === 'approved'): ?>
                                            <div class="alert alert-success mt-2 mb-0" style="font-size: 0.85rem;">
                                                ✓ <strong>Client approved:</strong> Selecting "Contacted" will redirect to property sale workflow
                                            </div>
                                        <?php elseif ($inquiry['account_status'] === 'pending'): ?>
                                            <div class="alert alert-warning mt-2 mb-0" style="font-size: 0.85rem;">
                                                ⏳ <strong>Client pending approval:</strong> Selecting "Contacted" will redirect you to approve this client first
                                                <br><a href="client_approvals.php?filter=pending" target="_blank" class="btn btn-sm btn-warning mt-2">
                                                    Approve Client Now
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Admin Notes</label>
                                    <textarea name="admin_notes" class="form-control" rows="5"
                                        placeholder="Enter notes about calls, meetings, or outcomes..."><?php echo htmlspecialchars($inquiry['admin_notes'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3 form-check" id="convertCheckWrapper" style="display: none;">
                                    <input type="checkbox" class="form-check-input" name="redirect_add_client"
                                        id="redirectAddClient" checked>
                                    <label class="form-check-label" for="redirectAddClient">Redirect to "Add Client"
                                        page</label>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">Update Inquiry</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('statusSelect').addEventListener('change', function () {
            const wrapper = document.getElementById('convertCheckWrapper');
            if (this.value === 'converted') {
                wrapper.style.display = 'block';
            } else {
                wrapper.style.display = 'none';
                document.getElementById('redirectAddClient').checked = false;
            }
        });
    </script>

    <?php include '../templates/footer.php'; ?>