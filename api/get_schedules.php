<?php
/**
 * API Endpoint: Get Payment Schedules
 */

header('Content-Type: application/json');

// Define constant for includes
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$property_id = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;

if ($property_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid property ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("\n        SELECT \n            ps.schedule_id,\n            ps.schedule_number,\n            ps.due_date,\n            ps.amount_due,\n            ps.status,\n            p.term_months AS term_months\n        FROM payment_schedules ps\n        INNER JOIN properties p ON p.property_id = ps.property_id\n        WHERE ps.property_id = ? AND ps.status != 'paid'\n        ORDER BY ps.schedule_number ASC, ps.due_date ASC\n    ");
    $stmt->execute([$property_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($schedules);
    
} catch (PDOException $e) {
    error_log("Get schedules API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
