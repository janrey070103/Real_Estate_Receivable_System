<?php
/**
 * Database Migration: Add Property Detail Fields
 * Adds square_meters, description, and location to properties table
 */

require_once '../includes/db_connect.php';

try {
    echo "Starting Property Details Migration...\n\n";

    // Check if columns already exist
    $check_sql = "SHOW COLUMNS FROM properties LIKE 'square_meters'";
    $result = $pdo->query($check_sql);
    
    if ($result->rowCount() > 0) {
        echo "✓ Property detail columns already exist\n";
        exit;
    }

    // Add square_meters column
    $sql = "ALTER TABLE properties 
            ADD COLUMN square_meters DECIMAL(10,2) NULL 
            COMMENT 'Property area in square meters' 
            AFTER property_name";
    $pdo->exec($sql);
    echo "✓ Added 'square_meters' column\n";

    // Add location column
    $sql = "ALTER TABLE properties 
            ADD COLUMN location VARCHAR(255) NULL 
            COMMENT 'Property location/address' 
            AFTER square_meters";
    $pdo->exec($sql);
    echo "✓ Added 'location' column\n";

    // Add description column
    $sql = "ALTER TABLE properties 
            ADD COLUMN description TEXT NULL 
            COMMENT 'Property description and features' 
            AFTER location";
    $pdo->exec($sql);
    echo "✓ Added 'description' column\n";

    // Add index on location for faster searches
    $sql = "ALTER TABLE properties ADD INDEX idx_location (location)";
    $pdo->exec($sql);
    echo "✓ Added index on location column\n";

    echo "\n=== Migration Complete! ===\n";
    echo "Property details fields have been added.\n";
    echo "You can now add square meters, location, and description to properties.\n\n";

} catch (PDOException $e) {
    echo "\n✗ Migration Error: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "\nNote: Columns may already exist.\n";
    }
}
?>
