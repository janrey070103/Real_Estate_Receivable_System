-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 30, 2025 at 03:03 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `real_estate_receivable_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_payment_schedule` (IN `p_property_id` INT, IN `p_start_date` DATE, IN `p_term_months` INT, IN `p_total_amount` DECIMAL(12,2))   BEGIN
    DECLARE v_counter INT DEFAULT 0;
    DECLARE v_monthly_amount DECIMAL(12,2);
    DECLARE v_due_date DATE;
    
    SET v_monthly_amount = p_total_amount / p_term_months;
    
    WHILE v_counter < p_term_months DO
        SET v_counter = v_counter + 1;
        SET v_due_date = DATE_ADD(p_start_date, INTERVAL v_counter MONTH);
        
        INSERT INTO payment_schedules (property_id, due_date, amount_due, status)
        VALUES (p_property_id, v_due_date, v_monthly_amount, 'pending');
    END WHILE;
    
    SELECT CONCAT('Generated ', p_term_months, ' payment schedules') AS result;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_overdue_schedules` ()   BEGIN
    UPDATE payment_schedules 
    SET status = 'overdue' 
    WHERE status = 'pending' 
    AND due_date < CURDATE();
    
    SELECT ROW_COUNT() AS updated_records;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target` varchar(200) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`log_id`, `user_id`, `action`, `target`, `details`, `ip_address`, `user_agent`, `timestamp`) VALUES
(1, 1, 'SYSTEM', 'audit_log table', 'Audit logging system initialized', '127.0.0.1', NULL, '2025-10-25 16:44:49'),
(3, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 16:47:50'),
(5, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 16:48:55'),
(6, 2, 'LOGIN', 'user_id:2', 'Successful login for user: finance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 16:49:29'),
(7, 2, 'LOGOUT', 'user:finance', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 17:38:09'),
(8, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 17:38:14'),
(9, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 04:03:15'),
(10, 1, 'ADD_CLIENT', 'client_id:3', 'Added new client: Aldrin Delicano', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 04:05:11'),
(11, 1, 'ADD_USER', 'user:financeda', 'Created new user with role: finance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 04:23:35'),
(12, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 04:23:38'),
(13, 3, 'LOGIN', 'user_id:3', 'Successful login for user: financeda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 04:23:45'),
(14, 3, 'LOGOUT', 'user:financeda', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 04:24:57'),
(15, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 04:25:01'),
(16, 1, 'RECORD_PAYMENT', 'payment_id:3', 'Recorded payment of ₱70,000.00 for schedule #184', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 04:26:25'),
(17, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 05:48:41'),
(18, 3, 'LOGIN', 'user_id:3', 'Successful login for user: financeda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 05:48:49'),
(20, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 13:19:24'),
(21, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-27 04:20:28'),
(22, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-27 04:26:07'),
(23, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-27 04:27:24'),
(24, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-27 05:21:59'),
(25, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-27 05:27:53'),
(26, 3, 'LOGIN', 'user_id:3', 'Successful login for user: financeda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-27 05:28:03'),
(27, 3, 'LOGOUT', 'user:financeda', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-27 05:28:15'),
(28, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-27 09:39:39'),
(29, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-27 10:05:14'),
(30, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-27 10:05:24'),
(31, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-28 04:13:17'),
(32, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-28 15:21:39'),
(33, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-28 15:24:08');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`client_id`, `name`, `email`, `contact_no`, `address`, `created_at`, `updated_at`) VALUES
(2, 'James Franco', 'james@gmail.com', '09471873933', 'Bacolod City', '2025-10-25 06:21:49', '2025-10-25 06:21:49'),
(3, 'Aldrin Delicano', 'aldrin@gmail.com', '09471873933', 'Victorias', '2025-10-26 04:05:11', '2025-10-26 04:05:11');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `doc_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`doc_id`, `client_id`, `file_name`, `file_path`, `description`, `upload_date`) VALUES
(1, 2, 'Test_Client_Document.pdf', 'uploads/documents/2025/10/68fc77f00afd9_Test_Client_Document.pdf', NULL, '2025-10-25 07:10:40'),
(2, 3, 'Contract_Unit12_SMDC_AldrinDelicano.pdf', 'uploads/clients/3/1761452222_68fda0bedfcf3_Contract_Unit12_SMDC_AldrinDelicano.pdf', NULL, '2025-10-26 04:17:02');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `property_id` int(11) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`invoice_id`, `invoice_no`, `schedule_id`, `property_id`, `invoice_date`, `due_date`, `total_amount`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(4, 'INV-20251026-000184', 184, NULL, '2025-10-26', '2025-11-30', 70000.00, 'paid', '', '2025-10-26 04:21:51', '2025-10-26 04:26:25');

-- --------------------------------------------------------

--
-- Table structure for table `invoices_backup_before_sync`
--

CREATE TABLE `invoices_backup_before_sync` (
  `invoice_id` int(11) NOT NULL DEFAULT 0,
  `invoice_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('unpaid','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices_backup_before_sync`
--

INSERT INTO `invoices_backup_before_sync` (`invoice_id`, `invoice_no`, `status`, `updated_at`) VALUES
(1, 'INV-20250115-000001', 'unpaid', '2025-10-25 14:51:23'),
(2, 'INV-20250115-P000001', 'unpaid', '2025-10-25 14:51:23');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notif_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` enum('sms','email') NOT NULL,
  `status` enum('pending','sent') NOT NULL DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `date_paid` date NOT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `schedule_id`, `amount_paid`, `date_paid`, `receipt_no`, `created_at`, `updated_at`) VALUES
(2, 84, 50000.00, '2025-10-25', NULL, '2025-10-25 14:29:52', '2025-10-25 14:29:52'),
(3, 184, 70000.00, '2025-10-26', NULL, '2025-10-26 04:26:25', '2025-10-26 04:26:25');

--
-- Triggers `payments`
--
DELIMITER $$
CREATE TRIGGER `sync_invoice_on_payment_insert` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
    DECLARE v_schedule_status VARCHAR(20);
    DECLARE v_property_id INT;
    DECLARE v_total_paid DECIMAL(12,2);
    DECLARE v_amount_due DECIMAL(12,2);
    
    -- Get schedule details
    SELECT ps.status, ps.property_id, ps.amount_due
    INTO v_schedule_status, v_property_id, v_amount_due
    FROM payment_schedules ps
    WHERE ps.schedule_id = NEW.schedule_id;
    
    -- Calculate total paid for this schedule
    SELECT COALESCE(SUM(amount_paid), 0)
    INTO v_total_paid
    FROM payments
    WHERE schedule_id = NEW.schedule_id;
    
    -- If schedule is now fully paid, update related invoices
    IF v_total_paid >= v_amount_due THEN
        
        -- Update schedule-based invoices
        UPDATE invoices 
        SET status = 'paid',
            updated_at = CURRENT_TIMESTAMP
        WHERE schedule_id = NEW.schedule_id
        AND status = 'unpaid';
        
        -- Update property-based invoices if all schedules paid
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
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_payment_insert` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
    DECLARE v_total_paid DECIMAL(12,2);
    DECLARE v_amount_due DECIMAL(12,2);
    
    
    SELECT COALESCE(SUM(amount_paid), 0) INTO v_total_paid
    FROM payments
    WHERE schedule_id = NEW.schedule_id;
    
    
    SELECT amount_due INTO v_amount_due
    FROM payment_schedules
    WHERE schedule_id = NEW.schedule_id;
    
    
    IF v_total_paid >= v_amount_due THEN
        UPDATE payment_schedules
        SET status = 'paid'
        WHERE schedule_id = NEW.schedule_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_schedules`
--

CREATE TABLE `payment_schedules` (
  `schedule_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount_due` decimal(12,2) NOT NULL,
  `status` enum('pending','paid','overdue') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_schedules`
--

INSERT INTO `payment_schedules` (`schedule_id`, `property_id`, `due_date`, `amount_due`, `status`, `created_at`, `updated_at`) VALUES
(84, 3, '2025-12-03', 50000.00, 'paid', '2025-10-25 07:43:43', '2025-10-25 14:29:52'),
(85, 3, '2026-01-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(86, 3, '2026-02-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(87, 3, '2026-03-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(88, 3, '2026-04-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(89, 3, '2026-05-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(90, 3, '2026-06-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(91, 3, '2026-07-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(92, 3, '2026-08-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(93, 3, '2026-09-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(94, 3, '2026-10-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(95, 3, '2026-11-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(96, 3, '2026-12-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(97, 3, '2027-01-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(98, 3, '2027-02-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(99, 3, '2027-03-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(100, 3, '2027-04-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(101, 3, '2027-05-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(102, 3, '2027-06-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(103, 3, '2027-07-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(104, 3, '2027-08-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(105, 3, '2027-09-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(106, 3, '2027-10-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(107, 3, '2027-11-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(108, 3, '2027-12-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(109, 3, '2028-01-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(110, 3, '2028-02-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(111, 3, '2028-03-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(112, 3, '2028-04-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(113, 3, '2028-05-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(114, 3, '2028-06-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(115, 3, '2028-07-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(116, 3, '2028-08-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(117, 3, '2028-09-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(118, 3, '2028-10-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(119, 3, '2028-11-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(120, 3, '2028-12-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(121, 3, '2029-01-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(122, 3, '2029-02-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(123, 3, '2029-03-03', 50000.00, 'pending', '2025-10-25 07:43:43', '2025-10-25 07:43:43'),
(184, 5, '2025-11-26', 70000.00, 'paid', '2025-10-26 04:20:14', '2025-10-26 04:26:25'),
(185, 5, '2025-12-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(186, 5, '2026-01-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(187, 5, '2026-02-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(188, 5, '2026-03-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(189, 5, '2026-04-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(190, 5, '2026-05-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(191, 5, '2026-06-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(192, 5, '2026-07-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(193, 5, '2026-08-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(194, 5, '2026-09-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(195, 5, '2026-10-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(196, 5, '2026-11-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(197, 5, '2026-12-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(198, 5, '2027-01-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(199, 5, '2027-02-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(200, 5, '2027-03-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(201, 5, '2027-04-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(202, 5, '2027-05-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(203, 5, '2027-06-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(204, 5, '2027-07-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(205, 5, '2027-08-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(206, 5, '2027-09-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(207, 5, '2027-10-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(208, 5, '2027-11-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(209, 5, '2027-12-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(210, 5, '2028-01-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(211, 5, '2028-02-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(212, 5, '2028-03-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(213, 5, '2028-04-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(214, 5, '2028-05-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(215, 5, '2028-06-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(216, 5, '2028-07-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(217, 5, '2028-08-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(218, 5, '2028-09-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(219, 5, '2028-10-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(220, 5, '2028-11-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(221, 5, '2028-12-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(222, 5, '2029-01-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(223, 5, '2029-02-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(224, 5, '2029-03-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(225, 5, '2029-04-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(226, 5, '2029-05-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(227, 5, '2029-06-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(228, 5, '2029-07-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(229, 5, '2029-08-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(230, 5, '2029-09-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(231, 5, '2029-10-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(232, 5, '2029-11-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(233, 5, '2029-12-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(234, 5, '2030-01-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(235, 5, '2030-02-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(236, 5, '2030-03-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(237, 5, '2030-04-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(238, 5, '2030-05-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(239, 5, '2030-06-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(240, 5, '2030-07-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(241, 5, '2030-08-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(242, 5, '2030-09-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14'),
(243, 5, '2030-10-26', 70000.00, 'pending', '2025-10-26 04:20:14', '2025-10-26 04:20:14');

--
-- Triggers `payment_schedules`
--
DELIMITER $$
CREATE TRIGGER `sync_invoice_on_schedule_update` AFTER UPDATE ON `payment_schedules` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `property_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `property_name` varchar(150) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `contract_date` date NOT NULL,
  `term_months` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`property_id`, `client_id`, `property_name`, `total_price`, `contract_date`, `term_months`, `created_at`, `updated_at`) VALUES
(3, 2, 'San Lorenzo Homes Block 12 Lot 13', 2000000.00, '2025-11-03', 40, '2025-10-25 07:43:18', '2025-10-25 07:43:18'),
(5, 3, 'SMDC Unit 12 Residence', 4200000.00, '2025-10-26', 60, '2025-10-26 04:19:47', '2025-10-26 04:19:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','finance') NOT NULL DEFAULT 'finance',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$lIG.2306l9SXr7JOOmqDIuKmtPKDZrsuuzb6ZzEe4DZ7.dXPKhxf2', 'admin', '2025-10-24 16:03:19', '2025-10-24 16:33:20'),
(2, 'finance', '$2y$10$lIG.2306l9SXr7JOOmqDIuKmtPKDZrsuuzb6ZzEe4DZ7.dXPKhxf2', 'finance', '2025-10-25 16:44:49', '2025-10-25 16:44:49'),
(3, 'financeda', '$2y$10$8yK9HbMmeS6VnzDyZEGKEeetTstPd3iAD8z5iXpZ0e0ywqDPhYwD.', 'finance', '2025-10-26 04:23:35', '2025-10-26 04:23:35');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_client_payment_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_client_payment_summary` (
`client_id` int(11)
,`client_name` varchar(100)
,`email` varchar(100)
,`contact_no` varchar(20)
,`total_properties` bigint(21)
,`total_contract_value` decimal(34,2)
,`total_schedules` bigint(21)
,`total_paid` decimal(34,2)
,`total_pending` decimal(34,2)
,`total_overdue` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_overdue_payments`
-- (See below for the actual view)
--
CREATE TABLE `vw_overdue_payments` (
`schedule_id` int(11)
,`client_id` int(11)
,`client_name` varchar(100)
,`email` varchar(100)
,`contact_no` varchar(20)
,`property_name` varchar(150)
,`due_date` date
,`amount_due` decimal(12,2)
,`days_overdue` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_payment_history`
-- (See below for the actual view)
--
CREATE TABLE `vw_payment_history` (
`payment_id` int(11)
,`receipt_no` varchar(50)
,`date_paid` date
,`amount_paid` decimal(12,2)
,`client_id` int(11)
,`client_name` varchar(100)
,`property_name` varchar(150)
,`due_date` date
,`amount_due` decimal(12,2)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_client_payment_summary`
--
DROP TABLE IF EXISTS `vw_client_payment_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_client_payment_summary`  AS SELECT `c`.`client_id` AS `client_id`, `c`.`name` AS `client_name`, `c`.`email` AS `email`, `c`.`contact_no` AS `contact_no`, count(distinct `p`.`property_id`) AS `total_properties`, sum(`p`.`total_price`) AS `total_contract_value`, count(`ps`.`schedule_id`) AS `total_schedules`, sum(case when `ps`.`status` = 'paid' then `ps`.`amount_due` else 0 end) AS `total_paid`, sum(case when `ps`.`status` = 'pending' then `ps`.`amount_due` else 0 end) AS `total_pending`, sum(case when `ps`.`status` = 'overdue' then `ps`.`amount_due` else 0 end) AS `total_overdue` FROM ((`clients` `c` left join `properties` `p` on(`c`.`client_id` = `p`.`client_id`)) left join `payment_schedules` `ps` on(`p`.`property_id` = `ps`.`property_id`)) GROUP BY `c`.`client_id`, `c`.`name`, `c`.`email`, `c`.`contact_no` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_overdue_payments`
--
DROP TABLE IF EXISTS `vw_overdue_payments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_overdue_payments`  AS SELECT `ps`.`schedule_id` AS `schedule_id`, `c`.`client_id` AS `client_id`, `c`.`name` AS `client_name`, `c`.`email` AS `email`, `c`.`contact_no` AS `contact_no`, `p`.`property_name` AS `property_name`, `ps`.`due_date` AS `due_date`, `ps`.`amount_due` AS `amount_due`, to_days(curdate()) - to_days(`ps`.`due_date`) AS `days_overdue` FROM ((`payment_schedules` `ps` join `properties` `p` on(`ps`.`property_id` = `p`.`property_id`)) join `clients` `c` on(`p`.`client_id` = `c`.`client_id`)) WHERE `ps`.`status` = 'overdue' OR `ps`.`status` = 'pending' AND `ps`.`due_date` < curdate() ORDER BY `ps`.`due_date` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_payment_history`
--
DROP TABLE IF EXISTS `vw_payment_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_payment_history`  AS SELECT `pay`.`payment_id` AS `payment_id`, `pay`.`receipt_no` AS `receipt_no`, `pay`.`date_paid` AS `date_paid`, `pay`.`amount_paid` AS `amount_paid`, `c`.`client_id` AS `client_id`, `c`.`name` AS `client_name`, `p`.`property_name` AS `property_name`, `ps`.`due_date` AS `due_date`, `ps`.`amount_due` AS `amount_due` FROM (((`payments` `pay` join `payment_schedules` `ps` on(`pay`.`schedule_id` = `ps`.`schedule_id`)) join `properties` `p` on(`ps`.`property_id` = `p`.`property_id`)) join `clients` `c` on(`p`.`client_id` = `c`.`client_id`)) ORDER BY `pay`.`date_paid` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`client_id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`doc_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_upload_date` (`upload_date`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `idx_invoice_no` (`invoice_no`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_property_id` (`property_id`),
  ADD KEY `idx_invoice_date` (`invoice_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_date_created` (`date_created`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_date_paid` (`date_paid`),
  ADD KEY `idx_receipt_no` (`receipt_no`);

--
-- Indexes for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `idx_property_id` (`property_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`property_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_contract_date` (`contract_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=244;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `property_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `payment_schedules` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `payment_schedules` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  ADD CONSTRAINT `payment_schedules_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
