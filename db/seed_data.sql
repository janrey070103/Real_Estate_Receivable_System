-- =====================================================
-- SEED DATA FOR REAL ESTATE RECEIVABLE SYSTEM
-- Comprehensive test data with realistic scenarios
-- =====================================================

USE real_estate_receivable_db;

-- Clear existing data (except admin user)
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM notifications WHERE notif_id > 0;
DELETE FROM payments WHERE payment_id > 0;
DELETE FROM invoices WHERE invoice_id > 0;
DELETE FROM payment_schedules WHERE schedule_id > 0;
DELETE FROM documents WHERE doc_id > 0;
DELETE FROM properties WHERE property_id > 0;
DELETE FROM clients WHERE client_id > 0;
DELETE FROM users WHERE user_id > 1; -- Keep admin
SET FOREIGN_KEY_CHECKS = 1;

-- Reset auto increments
ALTER TABLE clients AUTO_INCREMENT = 1;
ALTER TABLE properties AUTO_INCREMENT = 1;
ALTER TABLE payment_schedules AUTO_INCREMENT = 1;
ALTER TABLE payments AUTO_INCREMENT = 1;
ALTER TABLE invoices AUTO_INCREMENT = 1;
ALTER TABLE notifications AUTO_INCREMENT = 1;
ALTER TABLE documents AUTO_INCREMENT = 1;

-- =====================================================
-- INSERT USERS (Only 1 finance user)
-- =====================================================
INSERT INTO users (username, password, role, created_at) VALUES
('finance_staff', '$2y$10$lIG.2306l9SXr7JOOmqDIuKmtPKDZrsuuzb6ZzEe4DZ7.dXPKhxf2', 'finance', '2025-01-01 08:00:00');
-- Default password for all: admin123

-- =====================================================
-- INSERT CLIENTS (Reduced to 3 for balance)
-- =====================================================
INSERT INTO clients (name, email, contact_no, address, created_at) VALUES
('Juan Dela Cruz', 'juan.delacruz@gmail.com', '09171234567', '123 Rizal St, Manila, Philippines', '2024-09-15 10:30:00'),
('Maria Santos', 'maria.santos@yahoo.com', '09281234567', '456 Bonifacio Ave, Quezon City, Philippines', '2024-10-20 14:20:00'),
('Robert Chen', 'robert.chen@outlook.com', '09391234567', '789 Makati Ave, Makati City, Philippines', '2024-11-10 09:15:00');

-- =====================================================
-- INSERT PROPERTIES (Reduced to 3 properties, 1 per client)
-- =====================================================
INSERT INTO properties (client_id, property_name, total_price, contract_date, term_months, created_at) VALUES
-- Client 1: Juan Dela Cruz - 1 property
(1, 'Sunrise Residences Unit 12A', 1200000.00, '2024-10-01', 24, '2024-09-15 10:45:00'),

-- Client 2: Maria Santos - 1 property
(2, 'Green Valley Townhouse Unit 8', 1800000.00, '2024-11-01', 18, '2024-10-20 15:00:00'),

-- Client 3: Robert Chen - 1 property
(3, 'Metro Plaza Office Space 301', 2400000.00, '2024-12-01', 12, '2024-11-10 10:00:00');

-- =====================================================
-- GENERATE PAYMENT SCHEDULES
-- =====================================================

-- Property 1: Sunrise Residences (24 months, started Oct 2024)
-- Monthly: 50,000.00
CALL sp_generate_payment_schedule(1, '2024-10-01', 24, 1200000.00);

-- Property 2: Green Valley Townhouse (18 months, started Nov 2024)
-- Monthly: 100,000.00
CALL sp_generate_payment_schedule(2, '2024-11-01', 18, 1800000.00);

-- Property 3: Metro Plaza Office (12 months, started Dec 2024)
-- Monthly: 200,000.00
CALL sp_generate_payment_schedule(3, '2024-12-01', 12, 2400000.00);

-- =====================================================
-- UPDATE OVERDUE SCHEDULES
-- =====================================================
CALL sp_update_overdue_schedules();

-- =====================================================
-- INSERT PAYMENTS (Realistic payment history)
-- =====================================================

-- Property 1: Sunrise Residences - PAID schedules 1-2, partial payment on 3
INSERT INTO payments (schedule_id, amount_paid, date_paid, receipt_no, created_at) VALUES
-- Schedule 1 (Due Nov 2024) - Fully paid
(1, 50000.00, '2024-11-05', 'REC-20241105-001', '2024-11-05 10:00:00'),
-- Schedule 2 (Due Dec 2024) - Fully paid
(2, 50000.00, '2024-12-03', 'REC-20241203-001', '2024-12-03 11:30:00'),
-- Schedule 3 (Due Jan 2025) - Partial payment
(3, 25000.00, '2024-12-20', 'REC-20241220-001', '2024-12-20 14:00:00');

-- Property 2: Green Valley Townhouse - PAID schedule 1
INSERT INTO payments (schedule_id, amount_paid, date_paid, receipt_no, created_at) VALUES
(25, 100000.00, '2024-11-15', 'REC-20241115-002', '2024-11-15 15:00:00');

-- Property 3: Metro Plaza Office - PAID schedule 1 (recent property)
INSERT INTO payments (schedule_id, amount_paid, date_paid, receipt_no, created_at) VALUES
(43, 200000.00, '2024-12-10', 'REC-20241210-003', '2024-12-10 09:30:00');

-- =====================================================
-- INSERT INVOICES (Mix of paid and unpaid)
-- =====================================================

-- Invoices for Property 1 (Sunrise Residences)
INSERT INTO invoices (invoice_no, schedule_id, invoice_date, due_date, total_amount, status, notes, created_at) VALUES
('INV-20241025-000001', 1, '2024-10-25', '2024-11-01', 50000.00, 'paid', 'First installment', '2024-10-25 10:00:00'),
('INV-20241125-000002', 2, '2024-11-25', '2024-12-01', 50000.00, 'paid', 'Second installment', '2024-11-25 10:00:00'),
('INV-20241215-000003', 3, '2024-12-15', '2025-01-01', 50000.00, 'unpaid', 'Third installment - Partial payment received', '2024-12-15 10:00:00');

-- Invoices for Property 2 (Green Valley)
INSERT INTO invoices (invoice_no, schedule_id, invoice_date, due_date, total_amount, status, notes, created_at) VALUES
('INV-20241015-000025', 25, '2024-10-15', '2024-11-01', 100000.00, 'paid', 'Payment 1 of 18', '2024-10-15 10:00:00'),
('INV-20241115-000026', 26, '2024-11-15', '2024-12-01', 100000.00, 'unpaid', 'Payment 2 of 18', '2024-11-15 10:00:00');

-- Invoices for Property 3 (Metro Plaza)
INSERT INTO invoices (invoice_no, schedule_id, invoice_date, due_date, total_amount, status, notes, created_at) VALUES
('INV-20241120-000043', 43, '2024-11-20', '2024-12-01', 200000.00, 'paid', 'Office space payment 1 of 12', '2024-11-20 10:00:00');

-- =====================================================
-- INSERT NOTIFICATIONS (Balanced notifications)
-- =====================================================

INSERT INTO notifications (client_id, message, date_created, type, status, sent_at) VALUES
-- Sent notifications
(1, 'Payment reminder: Schedule 3 for Sunrise Residences Unit 12A is due on 2025-01-01. Amount: ₱50,000.00', '2024-12-15 08:00:00', 'email', 'sent', '2024-12-15 08:05:00'),
(2, 'Payment reminder: Schedule 2 for Green Valley Townhouse Unit 8 is due on 2024-12-01. Amount: ₱100,000.00', '2024-11-15 08:00:00', 'sms', 'sent', '2024-11-15 08:02:00'),

-- Pending notifications
(3, 'Payment reminder: Schedule 2 for Metro Plaza Office Space 301 is due on 2025-01-01. Amount: ₱200,000.00', '2024-12-20 10:00:00', 'email', 'pending', NULL);

-- =====================================================
-- INSERT AUDIT LOGS (Sample activity history)
-- =====================================================
INSERT INTO audit_log (user_id, action, target, details, ip_address, timestamp) VALUES
-- Admin activities
(1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '127.0.0.1', '2024-11-01 08:00:00'),
(1, 'ADD_USER', 'user:finance_staff', 'Created new user with role: finance', '127.0.0.1', '2024-11-01 08:15:00'),
(1, 'LOGOUT', 'user:admin', 'User logged out', '127.0.0.1', '2024-11-01 17:00:00'),

-- Finance staff activities
(2, 'LOGIN', 'user_id:2', 'Successful login for user: finance_staff', '127.0.0.1', '2024-11-05 09:00:00'),
(2, 'ADD_CLIENT', 'client_id:1', 'Added new client: Juan Dela Cruz', '127.0.0.1', '2024-11-05 10:30:00'),
(2, 'ADD_PROPERTY', 'property_id:1', 'Added new property: Sunrise Residences Unit 12A for client_id: 1', '127.0.0.1', '2024-11-05 10:45:00'),
(2, 'GENERATE_SCHEDULES', 'property_id:1', 'Generated 24 payment schedules for property: Sunrise Residences Unit 12A', '127.0.0.1', '2024-11-05 11:00:00'),
(2, 'RECORD_PAYMENT', 'payment_id:1', 'Recorded payment of ₱50,000.00 for schedule #1', '127.0.0.1', '2024-11-05 14:00:00'),
(2, 'ADD_CLIENT', 'client_id:2', 'Added new client: Maria Santos', '127.0.0.1', '2024-11-10 14:20:00'),
(2, 'ADD_PROPERTY', 'property_id:2', 'Added new property: Green Valley Townhouse Unit 8 for client_id: 2', '127.0.0.1', '2024-11-10 15:00:00'),
(2, 'GENERATE_SCHEDULES', 'property_id:2', 'Generated 18 payment schedules for property: Green Valley Townhouse Unit 8', '127.0.0.1', '2024-11-10 15:15:00'),
(2, 'CREATE_INVOICE', 'invoice_id:4', 'Created invoice: INV-20241015-000025', '127.0.0.1', '2024-11-15 10:00:00'),
(2, 'ADD_CLIENT', 'client_id:3', 'Added new client: Robert Chen', '127.0.0.1', '2024-11-20 10:15:00'),
(2, 'ADD_PROPERTY', 'property_id:3', 'Added new property: Metro Plaza Office Space 301 for client_id: 3', '127.0.0.1', '2024-11-20 10:30:00'),
(2, 'GENERATE_SCHEDULES', 'property_id:3', 'Generated 12 payment schedules for property: Metro Plaza Office Space 301', '127.0.0.1', '2024-11-20 10:45:00'),
(2, 'RECORD_PAYMENT', 'payment_id:5', 'Recorded payment of ₱200,000.00 for schedule #43', '127.0.0.1', '2024-12-10 09:30:00'),
(2, 'GENERATE_NOTIFICATIONS', 'count:3', 'Generated 2 SMS and 1 email notifications', '127.0.0.1', '2024-12-20 10:00:00'),
(2, 'LOGOUT', 'user:finance_staff', 'User logged out', '127.0.0.1', '2024-12-20 17:00:00'),

-- System activities
(0, 'SYSTEM', 'audit_log table', 'Audit logging system initialized with seed data', '127.0.0.1', '2024-11-01 08:00:00');

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

SELECT '=== SEEDING COMPLETE ===' AS status;

SELECT 'CLIENTS' AS table_name, COUNT(*) AS count FROM clients
UNION ALL
SELECT 'PROPERTIES', COUNT(*) FROM properties
UNION ALL
SELECT 'PAYMENT_SCHEDULES', COUNT(*) FROM payment_schedules
UNION ALL
SELECT 'PAYMENTS', COUNT(*) FROM payments
UNION ALL
SELECT 'INVOICES', COUNT(*) FROM invoices
UNION ALL
SELECT 'NOTIFICATIONS', COUNT(*) FROM notifications
UNION ALL
SELECT 'USERS', COUNT(*) FROM users;

-- Show payment summary by property
SELECT 
    p.property_name,
    c.name AS client_name,
    p.term_months,
    COUNT(ps.schedule_id) AS total_schedules,
    SUM(CASE WHEN ps.status = 'paid' THEN 1 ELSE 0 END) AS paid_schedules,
    SUM(CASE WHEN ps.status = 'pending' THEN 1 ELSE 0 END) AS pending_schedules,
    SUM(CASE WHEN ps.status = 'overdue' THEN 1 ELSE 0 END) AS overdue_schedules,
    CONCAT('₱', FORMAT(p.total_price, 2)) AS total_price,
    CONCAT('₱', FORMAT(COALESCE(SUM(pay.amount_paid), 0), 2)) AS total_paid
FROM properties p
INNER JOIN clients c ON p.client_id = c.client_id
LEFT JOIN payment_schedules ps ON p.property_id = ps.property_id
LEFT JOIN payments pay ON ps.schedule_id = pay.schedule_id
GROUP BY p.property_id
ORDER BY p.property_id;

SELECT '=== DATA READY FOR TESTING ===' AS message;
