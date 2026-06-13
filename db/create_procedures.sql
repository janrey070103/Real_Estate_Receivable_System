USE real_estate_receivable_db;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_generate_payment_schedule$$
CREATE PROCEDURE sp_generate_payment_schedule (
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
END$$

DROP PROCEDURE IF EXISTS sp_update_overdue_schedules$$
CREATE PROCEDURE sp_update_overdue_schedules()
BEGIN
    UPDATE payment_schedules 
    SET status = 'overdue' 
    WHERE status = 'pending' 
    AND due_date < CURDATE();
    
    SELECT ROW_COUNT() AS updated_records;
END$$

DELIMITER ;
