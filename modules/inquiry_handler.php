<?php
/**
 * Inquiry Submission Handler
 * Real Estate Receivable System
 * 
 * Handles AJAX submissions of property inquiries from catalog.php
 */

header('Content-Type: application/json');

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'success' => false,
    'message' => 'Invalid request'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF Verification could be added here

        // Get and sanitize inputs
        $property_id = filter_input(INPUT_POST, 'property_id', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // Validation
        $errors = [];
        if (!$property_id)
            $errors[] = "Invalid property selected.";
        if (empty($name))
            $errors[] = "Name is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = "Valid email is required.";
        if (empty($contact))
            $errors[] = "Contact number is required.";
        if (empty($message))
            $errors[] = "Message is required.";

        if (!empty($errors)) {
            $response['message'] = implode(" ", $errors);
            echo json_encode($response);
            exit;
        }

        // Get Client ID if logged in
        $client_id = null;
        if (isset($_SESSION['client_id'])) {
            $client_id = $_SESSION['client_id'];
        } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'client' && isset($_SESSION['user_id'])) {
            // Try to find client_id from user_id if not directly in session
            // This is a fallback, ideally client_id is in session
            $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $client_id = $stmt->fetchColumn() ?: null;
        }

        // Insert Inquiry
        $sql = "INSERT INTO inquiries (property_id, client_id, name, email, contact_no, message, status, created_at) 
                VALUES (:property_id, :client_id, :name, :email, :contact, :message, 'pending', NOW())";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':property_id' => $property_id,
            ':client_id' => $client_id,
            ':name' => $name,
            ':email' => $email,
            ':contact' => $contact,
            ':message' => $message
        ]);

        if ($result) {
            $response['success'] = true;
            $response['message'] = "Thank you for your inquiry! Our team will contact you shortly.";

            // Log for admin notification (optional)
            // log_audit($pdo, 'INQUIRY', "Property ID: $property_id", "New inquiry from $name");
        } else {
            $response['message'] = "Database error: Could not save inquiry.";
        }

    } catch (Exception $e) {
        $response['message'] = "Server error: " . $e->getMessage();
        error_log("Inquiry Error: " . $e->getMessage());
    }
}

echo json_encode($response);
exit;
?>