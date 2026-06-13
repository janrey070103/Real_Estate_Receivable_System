<?php
/**
 * Database Migration: Remove Password Column from Clients Table
 * Consolidates authentication to users table only
 * 
 * REASON: There were two password columns (users.password and clients.password)
 * causing authentication conflicts. Login checks users.password but admin change
 * password was updating clients.password, making logins fail.
 */

require_once '../includes/db_connect.php';

try {
    echo "Starting Password Consolidation Migration...\n\n";

    // Check if password column exists in clients table
    $check_sql = "SHOW COLUMNS FROM clients LIKE 'password'";
    $result = $pdo->query($check_sql);
    
    if ($result->rowCount() == 0) {
        echo "✓ Password column does not exist in clients table (already removed)\n";
        echo "=== Migration Already Complete! ===\n";
        exit;
    }

    echo "⚠️  Found password column in clients table\n";
    echo "📋 This column is redundant - authentication uses users.password\n\n";

    // Drop the index first (if exists)
    try {
        $pdo->exec("ALTER TABLE clients DROP INDEX idx_email_password");
        echo "✓ Dropped index idx_email_password\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "check that column/key exists") === false) {
            echo "ℹ️  Index idx_email_password doesn't exist (OK)\n";
        }
    }

    // Remove password column from clients table
    $sql = "ALTER TABLE clients DROP COLUMN password";
    $pdo->exec($sql);
    echo "✓ Removed 'password' column from clients table\n\n";

    echo "=== Migration Complete! ===\n";
    echo "Authentication is now consolidated to users table only.\n";
    echo "Admin 'Change Password' feature will now work correctly.\n\n";

    echo "📝 Summary:\n";
    echo "   - Login: Uses users.password ✓\n";
    echo "   - Change Password: Updates users.password ✓\n";
    echo "   - Single source of truth for authentication ✓\n\n";

} catch (PDOException $e) {
    echo "\n✗ Migration Error: " . $e->getMessage() . "\n";
}
?>
