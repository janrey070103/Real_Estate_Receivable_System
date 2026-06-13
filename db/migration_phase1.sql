-- =====================================================
-- Phase 1 Migration: Interest, Down Payment, Penalties & Images
-- Real Estate Receivable System
-- 
-- This migration adds support for:
-- 1. Interest rate and down payment on properties
-- 2. Penalty tracking on payment schedules
-- 3. Property images (up to 4)
-- =====================================================

-- Run this script against your existing database:
-- mysql -u root -p real_estate_receivable_db < migration_phase1.sql

USE real_estate_receivable_db;

-- =====================================================
-- STEP 1: Add columns to `properties` table
-- =====================================================

-- Check and add interest_rate column
SET @col_exist = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = 'real_estate_receivable_db' 
                  AND TABLE_NAME = 'properties' 
                  AND COLUMN_NAME = 'interest_rate');
SET @sql = IF(@col_exist = 0, 
    'ALTER TABLE properties ADD COLUMN interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT ''Annual interest rate percentage (e.g., 12.00 for 12%)'' AFTER term_months',
    'SELECT ''Column interest_rate already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add down_payment column
SET @col_exist = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = 'real_estate_receivable_db' 
                  AND TABLE_NAME = 'properties' 
                  AND COLUMN_NAME = 'down_payment');
SET @sql = IF(@col_exist = 0, 
    'ALTER TABLE properties ADD COLUMN down_payment DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Down payment amount'' AFTER interest_rate',
    'SELECT ''Column down_payment already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add image columns (simple approach: 4 VARCHAR columns)
SET @col_exist = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = 'real_estate_receivable_db' 
                  AND TABLE_NAME = 'properties' 
                  AND COLUMN_NAME = 'image_1');
SET @sql = IF(@col_exist = 0, 
    'ALTER TABLE properties ADD COLUMN image_1 VARCHAR(255) DEFAULT NULL COMMENT ''Property image 1 path'' AFTER down_payment',
    'SELECT ''Column image_1 already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exist = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = 'real_estate_receivable_db' 
                  AND TABLE_NAME = 'properties' 
                  AND COLUMN_NAME = 'image_2');
SET @sql = IF(@col_exist = 0, 
    'ALTER TABLE properties ADD COLUMN image_2 VARCHAR(255) DEFAULT NULL COMMENT ''Property image 2 path'' AFTER image_1',
    'SELECT ''Column image_2 already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exist = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = 'real_estate_receivable_db' 
                  AND TABLE_NAME = 'properties' 
                  AND COLUMN_NAME = 'image_3');
SET @sql = IF(@col_exist = 0, 
    'ALTER TABLE properties ADD COLUMN image_3 VARCHAR(255) DEFAULT NULL COMMENT ''Property image 3 path'' AFTER image_2',
    'SELECT ''Column image_3 already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exist = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = 'real_estate_receivable_db' 
                  AND TABLE_NAME = 'properties' 
                  AND COLUMN_NAME = 'image_4');
SET @sql = IF(@col_exist = 0, 
    'ALTER TABLE properties ADD COLUMN image_4 VARCHAR(255) DEFAULT NULL COMMENT ''Property image 4 path'' AFTER image_3',
    'SELECT ''Column image_4 already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- STEP 2: Add columns to `payment_schedules` table
-- =====================================================

-- Check and add principal_amount column
SET @col_exist = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = 'real_estate_receivable_db' 
                  AND TABLE_NAME = 'payment_schedules' 
                  AND COLUMN_NAME = 'principal_amount');
SET @sql = IF(@col_exist = 0, 
    'ALTER TABLE payment_schedules ADD COLUMN principal_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Principal portion of payment'' AFTER amount_due',
    'SELECT ''Column principal_amount already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add interest_amount column
SET @col_exist = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = 'real_estate_receivable_db' 
                  AND TABLE_NAME = 'payment_schedules' 
                  AND COLUMN_NAME = 'interest_amount');
SET @sql = IF(@col_exist = 0, 
    'ALTER TABLE payment_schedules ADD COLUMN interest_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Interest portion of payment'' AFTER principal_amount',
    'SELECT ''Column interest_amount already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add penalty_amount column
SET @col_exist = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = 'real_estate_receivable_db' 
                  AND TABLE_NAME = 'payment_schedules' 
                  AND COLUMN_NAME = 'penalty_amount');
SET @sql = IF(@col_exist = 0, 
    'ALTER TABLE payment_schedules ADD COLUMN penalty_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Late payment penalty'' AFTER interest_amount',
    'SELECT ''Column penalty_amount already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- STEP 3: Create properties upload directory
-- (This is informational - create manually or via PHP)
-- =====================================================
-- Directory: uploads/properties/
-- Ensure this directory exists and is writable by the web server

-- =====================================================
-- VERIFICATION: Show updated table structures
-- =====================================================
DESCRIBE properties;
DESCRIBE payment_schedules;

-- =====================================================
-- END OF MIGRATION
-- =====================================================
