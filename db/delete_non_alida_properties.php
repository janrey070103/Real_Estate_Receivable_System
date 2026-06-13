<?php
/**
 * Delete Non-Alida Properties Script
 * Real Estate Receivable System
 * 
 * This script deletes all properties NOT related to "Alida"
 * Along with their linked payment schedules, payments, and invoices (CASCADE)
 */

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

echo "==============================================\n";
echo "DELETE NON-ALIDA PROPERTIES SCRIPT\n";
echo "==============================================\n\n";

try {
    // Start transaction for safety
    $pdo->beginTransaction();
    
    // Step 1: Identify properties to delete (NOT containing "Alida")
    echo "Step 1: Identifying non-Alida properties...\n";
    $stmt = $pdo->query("
        SELECT p.property_id, p.property_name, c.name as client_name, c.client_id,
               (SELECT COUNT(*) FROM payment_schedules WHERE property_id = p.property_id) as schedule_count,
               (SELECT COUNT(*) FROM payments pay 
                JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id 
                WHERE ps.property_id = p.property_id) as payment_count,
               (SELECT COUNT(*) FROM invoices WHERE property_id = p.property_id) as invoice_count
        FROM properties p
        LEFT JOIN clients c ON p.client_id = c.client_id
        WHERE p.property_name NOT LIKE '%Alida%'
        ORDER BY p.property_id
    ");
    
    $properties_to_delete = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($properties_to_delete)) {
        echo "No non-Alida properties found. Nothing to delete.\n";
        $pdo->rollBack();
        exit;
    }
    
    echo "Found " . count($properties_to_delete) . " properties to delete:\n\n";
    
    $total_schedules = 0;
    $total_payments = 0;
    $total_invoices = 0;
    
    foreach ($properties_to_delete as $prop) {
        echo "  - Property ID {$prop['property_id']}: {$prop['property_name']}\n";
        echo "    Client: " . ($prop['client_name'] ?? 'None') . "\n";
        echo "    Schedules: {$prop['schedule_count']} | Payments: {$prop['payment_count']} | Invoices: {$prop['invoice_count']}\n\n";
        
        $total_schedules += $prop['schedule_count'];
        $total_payments += $prop['payment_count'];
        $total_invoices += $prop['invoice_count'];
    }
    
    echo "---\n";
    echo "Total to be deleted:\n";
    echo "  Properties: " . count($properties_to_delete) . "\n";
    echo "  Payment Schedules: $total_schedules\n";
    echo "  Payments: $total_payments\n";
    echo "  Invoices: $total_invoices\n";
    echo "---\n\n";
    
    // Step 2: Delete properties (CASCADE will handle related records)
    echo "Step 2: Deleting properties and cascading related records...\n";
    
    $property_ids = array_column($properties_to_delete, 'property_id');
    $placeholders = str_repeat('?,', count($property_ids) - 1) . '?';
    
    $delete_stmt = $pdo->prepare("DELETE FROM properties WHERE property_id IN ($placeholders)");
    $delete_stmt->execute($property_ids);
    
    $deleted_count = $delete_stmt->rowCount();
    
    echo "✓ Deleted $deleted_count properties successfully!\n";
    echo "✓ Related payment_schedules, payments, and invoices automatically deleted (CASCADE)\n\n";
    
    // Step 3: Verify deletion
    echo "Step 3: Verifying deletion...\n";
    $verify_stmt = $pdo->query("
        SELECT p.property_id, p.property_name, c.name as client_name
        FROM properties p
        LEFT JOIN clients c ON p.client_id = c.client_id
        ORDER BY p.property_id
    ");
    
    $remaining_properties = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nRemaining properties (" . count($remaining_properties) . "):\n";
    foreach ($remaining_properties as $prop) {
        echo "  - Property ID {$prop['property_id']}: {$prop['property_name']} (Client: " . ($prop['client_name'] ?? 'None') . ")\n";
    }
    
    // Step 4: Log audit trail
    echo "\nStep 4: Logging audit trail...\n";
    foreach ($properties_to_delete as $prop) {
        log_audit(
            $pdo, 
            'DELETE_PROPERTY', 
            'property_id:' . $prop['property_id'], 
            'Bulk deletion: Removed non-Alida property: ' . $prop['property_name'] . 
            ' (Schedules: ' . $prop['schedule_count'] . ', Payments: ' . $prop['payment_count'] . ')'
        );
    }
    echo "✓ Audit trail logged\n\n";
    
    // Commit transaction
    $pdo->commit();
    
    echo "==============================================\n";
    echo "✓ DELETION COMPLETED SUCCESSFULLY!\n";
    echo "==============================================\n";
    echo "Deleted: $deleted_count properties and all related records\n";
    echo "Remaining: " . count($remaining_properties) . " Alida-related properties\n";
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Transaction rolled back. No changes made.\n";
    exit(1);
}
?>

