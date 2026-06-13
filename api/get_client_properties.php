<?php
/**
 * API Endpoint: Get Client Properties
 * Returns all properties for a given client (for client-first payment flow)
 */

header('Content-Type: application/json');

define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;

if ($client_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid client ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            p.property_id,
            p.property_name,
            p.total_price,
            p.security_deposit,
            p.interest_rate,
            COUNT(ps.schedule_id) as total_schedules,
            SUM(CASE WHEN ps.status != 'paid' THEN 1 ELSE 0 END) as unpaid_count,
            COALESCE(SUM(CASE WHEN ps.status = 'overdue' THEN ps.amount_due ELSE 0 END), 0) as overdue_amount,
            COALESCE(SUM(CASE WHEN ps.status = 'pending' THEN ps.amount_due ELSE 0 END), 0) as pending_amount
        FROM properties p
        LEFT JOIN payment_schedules ps ON p.property_id = ps.property_id
        WHERE p.client_id = ?
        GROUP BY p.property_id
        HAVING total_schedules > 0
        ORDER BY unpaid_count DESC, p.property_name ASC
    ");
    $stmt->execute([$client_id]);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($properties);

} catch (PDOException $e) {
    error_log("Get client properties API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>