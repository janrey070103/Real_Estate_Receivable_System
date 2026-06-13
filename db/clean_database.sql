-- =====================================================
-- Clean Database Script
-- Real Estate Receivable System
-- =====================================================
-- This script removes all data except the admin account
-- Use this to reset the system to a clean state
-- =====================================================

USE real_estate_receivable_db;

-- =====================================================
-- DELETE ALL DATA (except admin user)
-- Order matters due to foreign key constraints
-- =====================================================

-- Delete in reverse dependency order (child to parent)

-- 1. Delete payments (references payment_schedules)
DELETE FROM payments;

-- 2. Delete invoices (references payment_schedules and properties)
DELETE FROM invoices;

-- 3. Delete payment schedules (references properties)
DELETE FROM payment_schedules;

-- 4. Delete documents (references clients)
DELETE FROM documents;

-- 5. Delete notifications (references clients)
DELETE FROM notifications;

-- 6. Delete properties (references clients)
DELETE FROM properties;

-- 7. Delete clients (parent table)
DELETE FROM clients;

-- 8. Delete all users except admin
DELETE FROM users WHERE username != 'admin';

-- 9. Delete audit logs except admin's logs (optional - keep for history)
-- Uncomment the line below if you want to delete audit logs for deleted users
-- DELETE FROM audit_log WHERE user_id NOT IN (SELECT user_id FROM users WHERE username = 'admin');

-- =====================================================
-- VERIFY CLEAN STATE
-- =====================================================

SELECT 'Database cleaned successfully!' as Status;

SELECT 
    'users' as Table_Name,
    COUNT(*) as Record_Count,
    'Admin account preserved' as Note
FROM users
UNION ALL
SELECT 'clients', COUNT(*), 'All deleted' FROM clients
UNION ALL
SELECT 'properties', COUNT(*), 'All deleted' FROM properties
UNION ALL
SELECT 'payment_schedules', COUNT(*), 'All deleted' FROM payment_schedules
UNION ALL
SELECT 'payments', COUNT(*), 'All deleted' FROM payments
UNION ALL
SELECT 'invoices', COUNT(*), 'All deleted' FROM invoices
UNION ALL
SELECT 'documents', COUNT(*), 'All deleted' FROM documents
UNION ALL
SELECT 'notifications', COUNT(*), 'All deleted' FROM notifications;

-- =====================================================
-- ADMIN ACCOUNT INFO
-- =====================================================

SELECT 
    user_id,
    username,
    role,
    created_at
FROM users
WHERE username = 'admin';

-- =====================================================
-- NOTES
-- =====================================================
-- Admin account credentials remain:
-- Username: admin
-- Password: admin123
-- Role: admin
-- =====================================================
