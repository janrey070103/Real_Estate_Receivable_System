<?php
/**
 * API Endpoint: Get Property Information
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
    $stmt = $pdo->prepare("
        SELECT 
            p.property_id,
            p.property_name,
            p.total_price,
            COUNT(ps.schedule_id) as unpaid_count,
            COALESCE(SUM(ps.amount_due), 0) as total_due
        FROM properties p
        LEFT JOIN payment_schedules ps ON p.property_id = ps.property_id AND ps.status != 'paid'
        WHERE p.property_id = ?
        GROUP BY p.property_id
    ");
    $stmt->execute([$property_id]);
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$property) {
        http_response_code(404);
        echo json_encode(['error' => 'Property not found']);
        exit();
    }
    
    echo json_encode($property);
    
} catch (PDOException $e) {
    error_log("Get property info API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
