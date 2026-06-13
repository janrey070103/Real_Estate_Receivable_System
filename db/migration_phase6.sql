-- =====================================================
-- Phase 6: Core Restructuring Migration
-- Real Estate Receivable System
-- =====================================================
-- This migration:
-- 1. Makes properties.client_id nullable (properties can exist without an owner)
-- 2. Adds status column to properties (available, reserved, sold)
-- 3. Adds client_id to users table (to link user accounts to client profiles)
-- 4. Adds 'client' role to users table
-- =====================================================

USE real_estate_receivable_db;

-- =====================================================
-- STEP 1: Modify properties table
-- =====================================================

-- Drop foreign key constraint first (required to modify column)
ALTER TABLE properties DROP FOREIGN KEY properties_ibfk_1;

-- Make client_id nullable
ALTER TABLE properties MODIFY client_id INT NULL;

-- Add status column with default 'available'
-- 'available' = Not yet sold, visible in catalog
-- 'reserved' = Client interested, holding
-- 'sold' = Assigned to a client
ALTER TABLE properties ADD COLUMN status ENUM('available', 'reserved', 'sold') NOT NULL DEFAULT 'available' AFTER term_months;

-- Re-add foreign key constraint allowing NULL
ALTER TABLE properties ADD CONSTRAINT properties_ibfk_1 
    FOREIGN KEY (client_id) REFERENCES clients(client_id) 
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Update existing properties: If they have a client, mark as 'sold'
UPDATE properties SET status = 'sold' WHERE client_id IS NOT NULL;

-- =====================================================
-- STEP 2: Modify users table for client accounts
-- =====================================================

-- Modify role enum to include 'client'
ALTER TABLE users MODIFY role ENUM('admin', 'finance', 'client') NOT NULL DEFAULT 'finance';

-- Add client_id column to link user accounts to client profiles
ALTER TABLE users ADD COLUMN client_id INT NULL AFTER role;

-- Add foreign key for client linkage
ALTER TABLE users ADD CONSTRAINT users_client_fk 
    FOREIGN KEY (client_id) REFERENCES clients(client_id) 
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Add index for faster client lookups
ALTER TABLE users ADD INDEX idx_client_id (client_id);

-- =====================================================
-- Verification query (optional, for testing)
-- =====================================================
SELECT 'Properties table check:' AS info;
DESCRIBE properties;

SELECT 'Users table check:' AS info;
DESCRIBE users;

-- Show migration complete
SELECT 'Phase 6 Migration Complete!' AS status;
