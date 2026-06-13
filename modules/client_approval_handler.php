<?php
/**
 * Client Approval Handler
 * Real Estate Receivable System
 * 
 * Processes approval/rejection actions for self-registered clients
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Require login and admin/finance access
require_module_access('clients');

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: client_approvals.php');
    exit();
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('error', 'Invalid security token. Please try again.');
    header('Location: client_approvals.php');
    exit();
}

// Get parameters
$action = $_POST['action'] ?? '';
$client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
$filter = $_POST['filter'] ?? 'pending';

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    set_flash_message('error', 'Invalid action specified.');
    header('Location: client_approvals.php?filter=' . urlencode($filter));
    exit();
}

// Validate client ID
if ($client_id <= 0) {
    set_flash_message('error', 'Invalid client ID.');
    header('Location: client_approvals.php?filter=' . urlencode($filter));
    exit();
}

try {
    // Get client details
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    if (!$client) {
        set_flash_message('error', 'Client not found.');
        header('Location: client_approvals.php?filter=' . urlencode($filter));
        exit();
    }

    // Get current user ID for audit trail
    $current_user_id = $_SESSION['user_id'];

    if ($action === 'approve') {
        // APPROVE CLIENT
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET account_status = 'approved',
                approved_by = ?,
                approved_at = NOW(),
                rejection_reason = NULL
            WHERE client_id = ?
        ");
        $stmt->execute([$current_user_id, $client_id]);

        // Log audit trail
        log_audit(
            $pdo, 
            'APPROVE_CLIENT', 
            "client_id:$client_id", 
            "Approved client registration: {$client['name']}"
        );

        set_flash_message('success', "✅ Client '{$client['name']}' has been approved successfully!");

    } elseif ($action === 'reject') {
        // REJECT CLIENT
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');
        
        if (empty($rejection_reason)) {
            set_flash_message('error', 'Rejection reason is required.');
            header('Location: client_approvals.php?filter=' . urlencode($filter));
            exit();
        }

        $stmt = $pdo->prepare("
            UPDATE clients 
            SET account_status = 'rejected',
                approved_by = ?,
                approved_at = NOW(),
                rejection_reason = ?
            WHERE client_id = ?
        ");
        $stmt->execute([$current_user_id, $rejection_reason, $client_id]);

        // Log audit trail
        log_audit(
            $pdo, 
            'REJECT_CLIENT', 
            "client_id:$client_id", 
            "Rejected client registration: {$client['name']}. Reason: " . substr($rejection_reason, 0, 100)
        );

        set_flash_message('success', "❌ Client '{$client['name']}' has been rejected.");
    }

} catch (PDOException $e) {
    error_log("Client approval handler error: " . $e->getMessage());
    set_flash_message('error', 'Database error occurred. Please try again.');
}

// Redirect back to approvals page
header('Location: client_approvals.php?filter=' . urlencode($filter));
exit();
?>
