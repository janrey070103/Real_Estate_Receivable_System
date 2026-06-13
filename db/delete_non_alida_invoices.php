<?php
/**
 * Delete Non-Alida Invoices Script
 * Real Estate Receivable System
 * 
 * This script deletes all invoices NOT related to "Alida" properties
 */

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

echo "==============================================\n";
echo "DELETE NON-ALIDA INVOICES SCRIPT\n";
echo "==============================================\n\n";

try {
    // Start transaction for safety
    $pdo->beginTransaction();
    
    // Step 1: Identify invoices to delete
    echo "Step 1: Identifying non-Alida invoices...\n";
    
    $stmt = $pdo->query("
        SELECT i.invoice_id, i.invoice_no, i.property_id as direct_prop_id, i.schedule_id, 
               i.total_amount, i.status, i.invoice_date,
               ps.property_id as schedule_prop_id,
               p1.property_name as direct_property,
               p2.property_name as schedule_property,
               COALESCE(p1.property_name, p2.property_name) as final_property
        FROM invoices i
        LEFT JOIN properties p1 ON i.property_id = p1.property_id
        LEFT JOIN payment_schedules ps ON i.schedule_id = ps.schedule_id
        LEFT JOIN properties p2 ON ps.property_id = p2.property_id
        ORDER BY i.invoice_id
    ");
    
    $all_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($all_invoices)) {
        echo "No invoices found in the system.\n";
        $pdo->rollBack();
        exit;
    }
    
    $invoices_to_delete = [];
    $invoices_to_keep = [];
    
    foreach ($all_invoices as $invoice) {
        $property_name = $invoice['final_property'];
        
        if ($property_name && strpos($property_name, 'Alida') !== false) {
            $invoices_to_keep[] = $invoice;
        } else {
            $invoices_to_delete[] = $invoice;
        }
    }
    
    if (empty($invoices_to_delete)) {
        echo "No non-Alida invoices found. Nothing to delete.\n";
        $pdo->rollBack();
        exit;
    }
    
    echo "Found " . count($invoices_to_delete) . " invoices to delete:\n\n";
    
    foreach ($invoices_to_delete as $inv) {
        echo "  - Invoice ID {$inv['invoice_id']}: {$inv['invoice_no']}\n";
        echo "    Property: " . ($inv['final_property'] ?? 'NULL/Orphaned') . "\n";
        echo "    Amount: ₱" . number_format($inv['total_amount'], 2) . " | Status: {$inv['status']}\n";
        echo "    Direct Property ID: " . ($inv['direct_prop_id'] ?? 'NULL') . " | Schedule ID: " . ($inv['schedule_id'] ?? 'NULL') . "\n\n";
    }
    
    echo "---\n";
    echo "Total to be deleted: " . count($invoices_to_delete) . " invoices\n";
    echo "Total to be kept: " . count($invoices_to_keep) . " Alida-related invoices\n";
    echo "---\n\n";
    
    // Step 2: Delete invoices
    echo "Step 2: Deleting non-Alida invoices...\n";
    
    $invoice_ids = array_column($invoices_to_delete, 'invoice_id');
    $placeholders = str_repeat('?,', count($invoice_ids) - 1) . '?';
    
    $delete_stmt = $pdo->prepare("DELETE FROM invoices WHERE invoice_id IN ($placeholders)");
    $delete_stmt->execute($invoice_ids);
    
    $deleted_count = $delete_stmt->rowCount();
    
    echo "✓ Deleted $deleted_count invoices successfully!\n\n";
    
    // Step 3: Verify deletion
    echo "Step 3: Verifying deletion...\n";
    $verify_stmt = $pdo->query("
        SELECT i.invoice_id, i.invoice_no, 
               ps.property_id as schedule_prop_id,
               p2.property_name as schedule_property
        FROM invoices i
        LEFT JOIN payment_schedules ps ON i.schedule_id = ps.schedule_id
        LEFT JOIN properties p2 ON ps.property_id = p2.property_id
        ORDER BY i.invoice_id
    ");
    
    $remaining_invoices = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nRemaining invoices (" . count($remaining_invoices) . "):\n";
    foreach ($remaining_invoices as $inv) {
        echo "  - Invoice ID {$inv['invoice_id']}: {$inv['invoice_no']} (Property: " . ($inv['schedule_property'] ?? 'N/A') . ")\n";
    }
    
    // Step 4: Log audit trail
    echo "\nStep 4: Logging audit trail...\n";
    foreach ($invoices_to_delete as $inv) {
        log_audit(
            $pdo, 
            'DELETE_INVOICE', 
            'invoice_id:' . $inv['invoice_id'], 
            'Bulk deletion: Removed non-Alida invoice: ' . $inv['invoice_no'] . 
            ' (Amount: ₱' . number_format($inv['total_amount'], 2) . ', Property: ' . ($inv['final_property'] ?? 'NULL') . ')'
        );
    }
    echo "✓ Audit trail logged\n\n";
    
    // Commit transaction
    $pdo->commit();
    
    echo "==============================================\n";
    echo "✓ DELETION COMPLETED SUCCESSFULLY!\n";
    echo "==============================================\n";
    echo "Deleted: $deleted_count invoices\n";
    echo "Remaining: " . count($remaining_invoices) . " Alida-related invoices\n";
    
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

