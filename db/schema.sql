-- =====================================================
-- Real Estate Receivable System - Database Schema
-- =====================================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS real_estate_receivable_db;
USE real_estate_receivable_db;

-- =====================================================
-- TABLE: users
-- Description: System users (admin and finance roles)
-- =====================================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'finance') NOT NULL DEFAULT 'finance',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: audit_log
-- Description: System audit trail for all user actions
-- =====================================================
DROP TABLE IF EXISTS audit_log;
CREATE TABLE audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL DEFAULT 0 COMMENT '0 = System',
    action VARCHAR(100) NOT NULL COMMENT 'Action type (LOGIN, LOGOUT, ADD_CLIENT, etc.)',
    target VARCHAR(200) DEFAULT NULL COMMENT 'Target of action (e.g., client_id:5)',
    details TEXT DEFAULT NULL COMMENT 'Additional details about the action',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the user',
    user_agent VARCHAR(255) DEFAULT NULL COMMENT 'Browser/device information',
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: clients
-- Description: Client/customer information
-- =====================================================
DROP TABLE IF EXISTS clients;
CREATE TABLE clients (
    client_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    contact_no VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: properties
-- Description: Property/real estate information
-- =====================================================
DROP TABLE IF EXISTS properties;
CREATE TABLE properties (
    property_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    property_name VARCHAR(150) NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    contract_date DATE NOT NULL,
    term_months INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_contract_date (contract_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: payment_schedules
-- Description: Payment schedule for each property
-- =====================================================
DROP TABLE IF EXISTS payment_schedules;
CREATE TABLE payment_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    schedule_number INT NOT NULL COMMENT 'Installment number (1, 2, 3... up to term_months)',
    due_date DATE NOT NULL,
    amount_due DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'paid', 'overdue') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_property_schedule (property_id, schedule_number),
    INDEX idx_property_id (property_id),
    INDEX idx_due_date (due_date),
    INDEX idx_status (status),
    INDEX idx_schedule_number (schedule_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: payments
-- Description: Actual payments made by clients
-- =====================================================
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL,
    date_paid DATE NOT NULL,
    receipt_no VARCHAR(50) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES payment_schedules(schedule_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_schedule_id (schedule_id),
    INDEX idx_date_paid (date_paid),
    INDEX idx_receipt_no (receipt_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: invoices
-- Description: Invoice records for clients
-- =====================================================
DROP TABLE IF EXISTS invoices;
CREATE TABLE invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50) NOT NULL UNIQUE,
    schedule_id INT NULL,
    property_id INT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    status ENUM('unpaid', 'paid', 'overdue') NOT NULL DEFAULT 'unpaid',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES payment_schedules(schedule_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_invoice_no (invoice_no),
    INDEX idx_schedule_id (schedule_id),
    INDEX idx_property_id (property_id),
    INDEX idx_invoice_date (invoice_date),
    INDEX idx_status (status),
    CONSTRAINT chk_invoice_reference CHECK (schedule_id IS NOT NULL OR property_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: documents
-- Description: Document storage for client files
-- =====================================================
DROP TABLE IF EXISTS documents;
CREATE TABLE documents (
    doc_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_upload_date (upload_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: notifications
-- Description: Notification system (SMS/Email)
-- =====================================================
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
    notif_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    message TEXT NOT NULL,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    type ENUM('sms', 'email') NOT NULL,
    status ENUM('pending', 'sent') NOT NULL DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_date_created (date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INITIAL DATA
-- =====================================================

-- Insert default admin account
-- Password: admin123 (hashed using password_hash())
-- Generated hash verified to work with password_verify('admin123', $hash)
INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$lIG.2306l9SXr7JOOmqDIuKmtPKDZrsuuzb6ZzEe4DZ7.dXPKhxf2', 'admin');
-- Default password: admin123 (Please change this after first login)

-- =====================================================
-- SAMPLE DATA (Optional - for testing)
-- =====================================================

-- Sample client
INSERT INTO clients (name, email, contact_no, address) VALUES
('Juan Dela Cruz', 'juan.delacruz@email.com', '09171234567', '123 Main St, Manila, Philippines');

-- Sample property
INSERT INTO properties (client_id, property_name, total_price, contract_date, term_months) VALUES
(1, 'Sunrise Residences Unit 101', 2500000.00, '2025-01-15', 60);

-- Sample payment schedule (5 years, monthly)
INSERT INTO payment_schedules (property_id, schedule_number, due_date, amount_due, status) VALUES
(1, 1, '2025-02-15', 41666.67, 'pending'),
(1, 2, '2025-03-15', 41666.67, 'pending'),
(1, 3, '2025-04-15', 41666.67, 'pending');

-- Sample invoice
INSERT INTO invoices (invoice_no, schedule_id, invoice_date, due_date, total_amount, status, notes) VALUES
('INV-20250115-000001', 1, '2025-01-15', '2025-02-15', 41666.67, 'unpaid', 'First installment payment');

-- =====================================================
-- VIEWS (Optional - for reporting)
-- =====================================================

-- View: Client Payment Summary
CREATE OR REPLACE VIEW vw_client_payment_summary AS
SELECT 
    c.client_id,
    c.name AS client_name,
    c.email,
    c.contact_no,
    COUNT(DISTINCT p.property_id) AS total_properties,
    SUM(p.total_price) AS total_contract_value,
    COUNT(ps.schedule_id) AS total_schedules,
    SUM(CASE WHEN ps.status = 'paid' THEN ps.amount_due ELSE 0 END) AS total_paid,
    SUM(CASE WHEN ps.status = 'pending' THEN ps.amount_due ELSE 0 END) AS total_pending,
    SUM(CASE WHEN ps.status = 'overdue' THEN ps.amount_due ELSE 0 END) AS total_overdue
FROM clients c
LEFT JOIN properties p ON c.client_id = p.client_id
LEFT JOIN payment_schedules ps ON p.property_id = ps.property_id
GROUP BY c.client_id, c.name, c.email, c.contact_no;

-- View: Overdue Payments
CREATE OR REPLACE VIEW vw_overdue_payments AS
SELECT 
    ps.schedule_id,
    c.client_id,
    c.name AS client_name,
    c.email,
    c.contact_no,
    p.property_name,
    ps.due_date,
    ps.amount_due,
    DATEDIFF(CURDATE(), ps.due_date) AS days_overdue
FROM payment_schedules ps
JOIN properties p ON ps.property_id = p.property_id
JOIN clients c ON p.client_id = c.client_id
WHERE ps.status = 'overdue' OR (ps.status = 'pending' AND ps.due_date < CURDATE())
ORDER BY ps.due_date ASC;

-- View: Payment History
CREATE OR REPLACE VIEW vw_payment_history AS
SELECT 
    pay.payment_id,
    pay.receipt_no,
    pay.date_paid,
    pay.amount_paid,
    c.client_id,
    c.name AS client_name,
    p.property_name,
    ps.due_date,
    ps.amount_due
FROM payments pay
JOIN payment_schedules ps ON pay.schedule_id = ps.schedule_id
JOIN properties p ON ps.property_id = p.property_id
JOIN clients c ON p.client_id = c.client_id
ORDER BY pay.date_paid DESC;

-- =====================================================
-- STORED PROCEDURES (Optional)
-- =====================================================

DELIMITER //

-- Procedure: Update payment schedule status to overdue
CREATE PROCEDURE sp_update_overdue_schedules()
BEGIN
    UPDATE payment_schedules 
    SET status = 'overdue' 
    WHERE status = 'pending' 
    AND due_date < CURDATE();
    
    SELECT ROW_COUNT() AS updated_records;
END //

-- Procedure: Generate payment schedule for a property
CREATE PROCEDURE sp_generate_payment_schedule(
    IN p_property_id INT,
    IN p_start_date DATE,
    IN p_term_months INT,
    IN p_total_amount DECIMAL(12,2)
)
BEGIN
    DECLARE v_counter INT DEFAULT 0;
    DECLARE v_monthly_amount DECIMAL(12,2);
    DECLARE v_due_date DATE;
    
    SET v_monthly_amount = p_total_amount / p_term_months;
    
    WHILE v_counter < p_term_months DO
        SET v_counter = v_counter + 1;
        SET v_due_date = DATE_ADD(p_start_date, INTERVAL v_counter MONTH);
        
        INSERT INTO payment_schedules (property_id, schedule_number, due_date, amount_due, status)
        VALUES (p_property_id, v_counter, v_due_date, v_monthly_amount, 'pending');
    END WHILE;
    
    SELECT CONCAT('Generated ', p_term_months, ' payment schedules') AS result;
END //

DELIMITER ;

-- =====================================================
-- TRIGGERS
-- =====================================================

DELIMITER //

-- Trigger: Auto-update payment schedule AND invoice status when payment is made
CREATE TRIGGER trg_after_payment_insert
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    DECLARE v_total_paid DECIMAL(12,2);
    DECLARE v_amount_due DECIMAL(12,2);
    DECLARE v_property_id INT;
    
    -- Get schedule details
    SELECT 
        ps.amount_due,
        ps.property_id
    INTO 
        v_amount_due,
        v_property_id
    FROM payment_schedules ps
    WHERE ps.schedule_id = NEW.schedule_id;
    
    -- Calculate total paid for this schedule
    SELECT COALESCE(SUM(amount_paid), 0)
    INTO v_total_paid
    FROM payments
    WHERE schedule_id = NEW.schedule_id;
    
    -- If schedule is now fully paid, update everything
    IF v_total_paid >= v_amount_due THEN
        
        -- 1. Update payment_schedules status
        UPDATE payment_schedules
        SET status = 'paid'
        WHERE schedule_id = NEW.schedule_id;
        
        -- 2. Update schedule-based invoices
        UPDATE invoices 
        SET status = 'paid',
            updated_at = CURRENT_TIMESTAMP
        WHERE schedule_id = NEW.schedule_id
        AND status = 'unpaid';
        
        -- 3. Update property-based invoices (if ALL schedules paid)
        UPDATE invoices i
        SET status = 'paid',
            updated_at = CURRENT_TIMESTAMP
        WHERE i.property_id = v_property_id
        AND i.status = 'unpaid'
        AND NOT EXISTS (
            SELECT 1 
            FROM payment_schedules ps
            WHERE ps.property_id = v_property_id
            AND ps.status != 'paid'
        );
        
    END IF;
END //

DELIMITER ;

-- =====================================================
-- TRIGGER 2: Sync invoice when schedule status changes
-- Backup mechanism for manual status updates
-- =====================================================

DELIMITER //

CREATE TRIGGER sync_invoice_on_schedule_update
AFTER UPDATE ON payment_schedules
FOR EACH ROW
BEGIN
    -- Only proceed if status changed to 'paid'
    IF NEW.status = 'paid' AND OLD.status != 'paid' THEN
        
        -- Update schedule-based invoices
        UPDATE invoices 
        SET status = 'paid',
            updated_at = CURRENT_TIMESTAMP
        WHERE schedule_id = NEW.schedule_id
        AND status = 'unpaid';
        
        -- Update property-based invoices (check if ALL schedules are paid)
        UPDATE invoices i
        SET status = 'paid',
            updated_at = CURRENT_TIMESTAMP
        WHERE i.property_id = NEW.property_id
        AND i.status = 'unpaid'
        AND NOT EXISTS (
            SELECT 1 
            FROM payment_schedules ps
            WHERE ps.property_id = NEW.property_id
            AND ps.status != 'paid'
        );
        
    END IF;
END//

DELIMITER ;

-- =====================================================
-- GRANTS (Optional - for security)
-- =====================================================
-- GRANT SELECT, INSERT, UPDATE, DELETE ON real_estate_receivable_db.* TO 'rers_user'@'localhost';
-- FLUSH PRIVILEGES;

-- =====================================================
-- END OF SCHEMA
-- =====================================================
