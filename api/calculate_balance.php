<?php
/**
 * API Endpoint: Calculate Real-time Balance
 * Returns schedule balance with real-time calculation for AJAX updates
 */

header('Content-Type: application/json');

define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$schedule_id = isset($_GET['schedule_id']) ? (int) $_GET['schedule_id'] : 0;
$pending_payment = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

if ($schedule_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid schedule ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            ps.schedule_id,
            ps.amount_due,
            ps.principal_amount,
            ps.interest_amount,
            ps.penalty_amount,
            ps.status,
            ps.due_date,
            p.property_name,
            p.property_id,
            c.name as client_name,
            COALESCE(SUM(pay.amount_paid), 0) as total_paid
        FROM payment_schedules ps
        INNER JOIN properties p ON ps.property_id = p.property_id
        LEFT JOIN clients c ON p.client_id = c.client_id
        LEFT JOIN payments pay ON ps.schedule_id = pay.schedule_id
        WHERE ps.schedule_id = ?
        GROUP BY ps.schedule_id
    ");
    $stmt->execute([$schedule_id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        http_response_code(404);
        echo json_encode(['error' => 'Schedule not found']);
        exit();
    }

    // Calculate days overdue
    $due_date = new DateTime($schedule['due_date']);
    $today = new DateTime();
    $days_diff = $today->diff($due_date)->days;
    $is_overdue = ($due_date < $today);

    // Calculate penalty if overdue
    $penalty = 0;
    if ($is_overdue && $schedule['penalty_amount'] <= 0) {
        $penalty = calculate_penalty($schedule['amount_due'], $days_diff);
    } else {
        $penalty = floatval($schedule['penalty_amount']);
    }

    // Calculate current and projected balance (including penalty)
    $current_balance = ($schedule['amount_due'] + $penalty) - $schedule['total_paid'];
    $projected_balance = max(0, $current_balance - $pending_payment);
    $will_complete = ($projected_balance <= 0);

    echo json_encode([
        'schedule_id' => $schedule['schedule_id'],
        'property_name' => $schedule['property_name'],
        'client_name' => $schedule['client_name'],
        'amount_due' => floatval($schedule['amount_due']),
        'principal_amount' => floatval($schedule['principal_amount']),
        'interest_amount' => floatval($schedule['interest_amount']),
        'penalty_amount' => $penalty,
        'total_paid' => floatval($schedule['total_paid']),
        'current_balance' => round($current_balance, 2),
        'pending_payment' => $pending_payment,
        'projected_balance' => round($projected_balance, 2),
        'will_complete' => $will_complete,
        'status' => $schedule['status'],
        'is_overdue' => $is_overdue,
        'days_overdue' => $is_overdue ? $days_diff : 0,
        'formatted' => [
            'amount_due' => format_peso($schedule['amount_due']),
            'total_paid' => format_peso($schedule['total_paid']),
            'current_balance' => format_peso($current_balance),
            'projected_balance' => format_peso($projected_balance),
            'penalty' => format_peso($penalty)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Calculate balance API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>