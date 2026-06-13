<?php
/**
 * API Endpoint: Search Payments & Schedules
 * Advanced search for payment records AND payment schedules by client, property, date range, receipt
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

// Get search parameters
$client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
$property_id = isset($_GET['property_id']) ? (int) $_GET['property_id'] : 0;
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$receipt_no = isset($_GET['receipt_no']) ? trim($_GET['receipt_no']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? min(100, max(10, (int) $_GET['limit'])) : 50;

try {
    // Build dynamic query for BOTH payments and schedules
    $where = [];
    $params = [];

    if ($client_id > 0) {
        $where[] = "p.client_id = ?";
        $params[] = $client_id;
    }

    if ($property_id > 0) {
        $where[] = "p.property_id = ?";
        $params[] = $property_id;
    }

    if (!empty($search)) {
        $where[] = "(c.name LIKE ? OR p.property_name LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Query for completed payments
    $payments_sql = "
        SELECT 
            pay.payment_id,
            pay.amount_paid,
            pay.date_paid,
            pay.receipt_no,
            pay.created_at,
            ps.schedule_id,
            ps.schedule_number,
            ps.amount_due,
            ps.due_date,
            ps.status,
            p.property_id,
            p.property_name,
            c.client_id,
            c.name as client_name,
            'completed' as record_type
        FROM payments pay
        INNER JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        {$where_clause}
    ";

    // Query for pending schedules (unpaid)
    $schedules_sql = "
        SELECT 
            NULL as payment_id,
            NULL as amount_paid,
            NULL as date_paid,
            NULL as receipt_no,
            ps.created_at,
            ps.schedule_id,
            ps.schedule_number,
            ps.amount_due,
            ps.due_date,
            ps.status,
            p.property_id,
            p.property_name,
            c.client_id,
            c.name as client_name,
            'schedule' as record_type
        FROM payment_schedules ps
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        {$where_clause}
        AND ps.status IN ('pending', 'overdue')
    ";

    // Combine both queries
    $sql = "(
        {$payments_sql}
    )
    UNION ALL
    (
        {$schedules_sql}
    )
    ORDER BY 
        CASE 
            WHEN status = 'overdue' THEN 1
            WHEN status = 'pending' THEN 2
            WHEN status = 'paid' THEN 3
        END,
        client_name ASC,
        property_name ASC
    LIMIT ?
    ";
    
    // Double the params for UNION query
    $all_params = array_merge($params, $params);
    $all_params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($all_params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary stats
    $total_amount = 0;
    $paid_count = 0;
    $pending_count = 0;
    
    foreach ($results as $result) {
        if ($result['record_type'] === 'completed') {
            $total_amount += $result['amount_paid'];
            $paid_count++;
        } else {
            $pending_count++;
        }
    }

    echo json_encode([
        'count' => count($results),
        'total_amount' => $total_amount,
        'paid_count' => $paid_count,
        'pending_count' => $pending_count,
        'payments' => $results
    ]);

} catch (PDOException $e) {
    error_log("Search payments API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>