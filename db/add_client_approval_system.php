<?php
/**
 * Database Migration: Add Client Approval System
 * Adds account_status column to clients table for approval workflow
 */

require_once '../includes/db_connect.php';

try {
    echo "Starting Client Approval System Migration...\n\n";

    // Add account_status column to clients table
    $sql = "ALTER TABLE clients 
            ADD COLUMN account_status ENUM('pending', 'approved', 'rejected') 
            NOT NULL DEFAULT 'approved' 
            AFTER address";
    
    $pdo->exec($sql);
    echo "✓ Added 'account_status' column to clients table\n";

    // Add index for faster filtering
    $sql = "ALTER TABLE clients ADD INDEX idx_account_status (account_status)";
    $pdo->exec($sql);
    echo "✓ Added index on account_status column\n";

    // Add approved_by and approved_at columns for audit trail
    $sql = "ALTER TABLE clients 
            ADD COLUMN approved_by INT NULL AFTER account_status,
            ADD COLUMN approved_at TIMESTAMP NULL AFTER approved_by";
    
    $pdo->exec($sql);
    echo "✓ Added approval audit columns (approved_by, approved_at)\n";

    // Add rejection_reason column
    $sql = "ALTER TABLE clients 
            ADD COLUMN rejection_reason TEXT NULL AFTER approved_at";
    
    $pdo->exec($sql);
    echo "✓ Added rejection_reason column\n";

    // Add foreign key for approved_by
    $sql = "ALTER TABLE clients 
            ADD CONSTRAINT fk_clients_approved_by 
            FOREIGN KEY (approved_by) REFERENCES users(user_id) 
            ON DELETE SET NULL";
    
    $pdo->exec($sql);
    echo "✓ Added foreign key for approved_by column\n";

    echo "\n=== Migration Complete! ===\n";
    echo "All existing clients have been set to 'approved' status by default.\n";
    echo "New self-registered clients will be created with 'pending' status.\n";

} catch (PDOException $e) {
    echo "\n✗ Migration Error: " . $e->getMessage() . "\n";
    
    // Check if column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "\nNote: The account_status column may already exist.\n";
        echo "Run this migration on a fresh database or manually verify the schema.\n";
    }
}
?>
