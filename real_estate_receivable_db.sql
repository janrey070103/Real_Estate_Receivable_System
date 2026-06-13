-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jan 14, 2026 at 03:58 PM
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

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = System',
  `action` varchar(100) NOT NULL COMMENT 'Action type (LOGIN, LOGOUT, ADD_CLIENT, etc.)',
  `target` varchar(200) DEFAULT NULL COMMENT 'Target of action (e.g., client_id:5)',
  `details` text DEFAULT NULL COMMENT 'Additional details about the action',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of the user',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'Browser/device information',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`log_id`, `user_id`, `action`, `target`, `details`, `ip_address`, `user_agent`, `timestamp`) VALUES
(1, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '127.0.0.1', NULL, '2024-11-01 00:00:00'),
(2, 1, 'ADD_USER', 'user:finance_staff', 'Created new user with role: finance', '127.0.0.1', NULL, '2024-11-01 00:15:00'),
(3, 1, 'ADD_USER', 'user:accounting_staff', 'Created new user with role: finance', '127.0.0.1', NULL, '2024-11-01 00:20:00'),
(4, 1, 'ADD_USER', 'user:auditor_staff', 'Created new user with role: finance', '127.0.0.1', NULL, '2024-11-01 00:25:00'),
(5, 1, 'LOGOUT', 'user:admin', 'User logged out', '127.0.0.1', NULL, '2024-11-01 09:00:00'),
(6, 7, 'LOGIN', 'user_id:2', 'Successful login for user: finance_staff', '127.0.0.1', NULL, '2024-11-05 01:00:00'),
(7, 7, 'ADD_CLIENT', 'client_id:1', 'Added new client: Juan Dela Cruz', '127.0.0.1', NULL, '2024-11-05 02:30:00'),
(8, 7, 'ADD_PROPERTY', 'property_id:1', 'Added new property: Sunrise Residences Unit 12A for client_id: 1', '127.0.0.1', NULL, '2024-11-05 02:45:00'),
(9, 7, 'GENERATE_SCHEDULES', 'property_id:1', 'Generated 24 payment schedules for property: Sunrise Residences Unit 12A', '127.0.0.1', NULL, '2024-11-05 03:00:00'),
(10, 7, 'RECORD_PAYMENT', 'payment_id:1', 'Recorded payment of ???50,000.00 for schedule #1', '127.0.0.1', NULL, '2024-11-05 06:00:00'),
(11, 7, 'LOGOUT', 'user:finance_staff', 'User logged out', '127.0.0.1', NULL, '2024-11-05 10:00:00'),
(12, 7, 'LOGIN', 'user_id:3', 'Successful login for user: accounting_staff', '127.0.0.1', NULL, '2024-11-10 01:00:00'),
(13, 7, 'ADD_CLIENT', 'client_id:2', 'Added new client: Maria Santos', '127.0.0.1', NULL, '2024-11-10 06:20:00'),
(14, 7, 'ADD_PROPERTY', 'property_id:2', 'Added new property: Green Valley Townhouse Unit 8 for client_id: 2', '127.0.0.1', NULL, '2024-11-10 07:00:00'),
(15, 7, 'GENERATE_SCHEDULES', 'property_id:2', 'Generated 18 payment schedules for property: Green Valley Townhouse Unit 8', '127.0.0.1', NULL, '2024-11-10 07:15:00'),
(16, 7, 'CREATE_INVOICE', 'invoice_id:4', 'Created invoice: INV-20241015-000025', '127.0.0.1', NULL, '2024-11-15 02:00:00'),
(17, 7, 'LOGOUT', 'user:accounting_staff', 'User logged out', '127.0.0.1', NULL, '2024-11-10 09:30:00'),
(18, 7, 'LOGIN', 'user_id:4', 'Successful login for user: auditor_staff', '127.0.0.1', NULL, '2024-11-20 02:00:00'),
(19, 7, 'ADD_CLIENT', 'client_id:3', 'Added new client: Robert Chen', '127.0.0.1', NULL, '2024-11-20 02:15:00'),
(20, 7, 'ADD_PROPERTY', 'property_id:3', 'Added new property: Metro Plaza Office Space 301 for client_id: 3', '127.0.0.1', NULL, '2024-11-20 02:30:00'),
(21, 7, 'GENERATE_SCHEDULES', 'property_id:3', 'Generated 12 payment schedules for property: Metro Plaza Office Space 301', '127.0.0.1', NULL, '2024-11-20 02:45:00'),
(22, 7, 'RECORD_PAYMENT', 'payment_id:5', 'Recorded payment of ???200,000.00 for schedule #43', '127.0.0.1', NULL, '2024-12-10 01:30:00'),
(23, 7, 'GENERATE_NOTIFICATIONS', 'count:3', 'Generated 2 SMS and 1 email notifications', '127.0.0.1', NULL, '2024-12-20 02:00:00'),
(24, 7, 'LOGOUT', 'user:auditor_staff', 'User logged out', '127.0.0.1', NULL, '2024-12-20 09:00:00'),
(25, 0, 'SYSTEM', 'audit_log table', 'Audit logging system initialized with seed data', '127.0.0.1', NULL, '2024-11-01 00:00:00'),
(26, 0, 'LOGIN_FAILED', 'username:admin', 'Invalid credentials attempted (Attempt 1)', '::1', NULL, '2025-12-28 05:19:19'),
(27, 0, 'LOGIN_FAILED', 'username:admin', 'Invalid credentials attempted (Attempt 2)', '::1', NULL, '2025-12-28 05:19:25'),
(28, 0, 'LOGIN_FAILED', 'username:admin', 'Invalid credentials attempted (Attempt 3)', '::1', NULL, '2025-12-28 05:19:34'),
(29, 0, 'LOGIN_FAILED', 'username:admin', 'Invalid credentials attempted (Attempt 4)', '::1', NULL, '2025-12-28 05:19:41'),
(30, 0, 'LOGIN_FAILED', 'username:admin', 'Invalid credentials attempted (Attempt 5)', '::1', NULL, '2025-12-28 05:19:48'),
(31, 0, 'LOGIN_FAILED', 'username:admin', 'Invalid credentials attempted (Attempt 1)', '::1', NULL, '2025-12-28 05:21:03'),
(32, 0, 'LOGIN_FAILED', 'username:admin', 'Invalid credentials attempted (Attempt 2)', '::1', NULL, '2025-12-28 05:21:45'),
(33, 0, 'LOGIN_FAILED', 'username:finance', 'Invalid credentials attempted (Attempt 3)', '::1', NULL, '2025-12-28 05:21:58'),
(34, 0, 'LOGIN_FAILED', 'username:finance', 'Invalid credentials attempted (Attempt 4)', '::1', NULL, '2025-12-28 05:23:08'),
(35, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 05:23:13'),
(36, 1, 'DELETE_USER', 'user_id:7', 'Deleted user: finance_staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 05:23:39'),
(37, 1, 'ADD_USER', 'user:finance', 'Created new user with role: finance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 05:23:54'),
(38, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 05:26:28'),
(39, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-28 05:31:39'),
(40, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-28 05:41:59'),
(41, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-28 05:43:08'),
(42, 1, 'ADD_PROPERTY', 'property_id:4', 'Added new property: Ayala Unit 15 for client_id: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-28 05:56:04'),
(43, 1, 'GENERATE_SCHEDULES', 'property_id:4', 'Generated 60 payment schedules for property: Ayala Unit 15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-28 05:57:31'),
(44, 1, 'RECORD_PAYMENT', 'payment_id:6', 'Recorded payment of ₱25,000.00 for schedule #55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-28 06:02:52'),
(45, 1, 'GENERATE_NOTIFICATIONS', 'count:74', 'Generated 37 SMS and 37 email notifications', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-28 06:05:09'),
(46, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 02:20:03'),
(47, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-10 06:03:26'),
(48, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-11 12:30:24'),
(49, 1, 'ADD_CLIENT', 'client_id:4', 'Added new client: DA Sionomio', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-11 12:34:51'),
(50, 1, 'UPLOAD_DOCUMENT', 'client_id:4', 'Uploaded document: ethics.pdf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-11 12:35:09'),
(51, 1, 'ADD_PROPERTY', 'property_id:5', 'Added new property: San Lorenzo Carlen Property Block 13 Lot 43 for client_id: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-11 12:36:42'),
(52, 1, 'GENERATE_SCHEDULES', 'property_id:5', 'Generated 40 payment schedules for property: San Lorenzo Carlen Property Block 13 Lot 43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-11 12:38:07'),
(53, 1, 'CREATE_INVOICE', 'invoice_id:7', 'Created invoice: INV-20260111-000115', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-11 12:40:23'),
(54, 1, 'RECORD_PAYMENT', 'payment_id:7', 'Recorded payment of ₱99,000.00 for schedule #115', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-11 12:41:27'),
(55, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 09:57:38'),
(56, 1, 'ADD_CLIENT', 'client_id:5', 'Added new client: Carres Dizon', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:00:10'),
(57, 1, 'UPLOAD_DOCUMENT', 'client_id:5', 'Uploaded document: CONSUMER-INSIGHTSBASED-RENTAL-MANAGEMENT-SYSTEM.-CHAP1-3.pdf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:00:28'),
(58, 1, 'ADD_PROPERTY', 'property_id:6', 'Added new property: Bata Block 12 Lot 2 for client_id: 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:01:35'),
(59, 1, 'GENERATE_SCHEDULES', 'property_id:6', 'Generated 40 payment schedules for property: Bata Block 12 Lot 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:02:05'),
(60, 1, 'CREATE_INVOICE', 'invoice_id:8', 'Created invoice: INV-20260112-000155', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:02:50'),
(61, 1, 'RECORD_PAYMENT', 'payment_id:8', 'Recorded payment of ₱34,000.00 for schedule #155', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:03:36'),
(62, 1, 'ADD_USER', 'user:carresfinance', 'Created new user with role: finance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:05:01'),
(63, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:05:32'),
(64, 12, 'LOGIN', 'user_id:12', 'Successful login for user: carresfinance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:05:45'),
(65, 12, 'LOGOUT', 'user:carresfinance', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:40:50'),
(66, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:40:57'),
(67, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:43:38'),
(68, 12, 'LOGIN', 'user_id:12', 'Successful login for user: carresfinance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:43:57'),
(69, 12, 'LOGOUT', 'user:carresfinance', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:43:59'),
(70, 0, 'LOGIN_FAILED', 'username:admin', 'Invalid credentials attempted (Attempt 1)', '::1', NULL, '2026-01-12 10:44:12'),
(71, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:44:19'),
(72, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:45:02'),
(73, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:46:47'),
(74, 1, 'ADD_CLIENT', 'client_id:6', 'Added new client: juan dela tore', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:48:09'),
(75, 1, 'UPDATE_CLIENT', 'client_id:6', 'Updated client: juan dela tore', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:48:43'),
(76, 1, 'UPDATE_CLIENT', 'client_id:6', 'Updated client: Julius dela tore', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:49:06'),
(77, 1, 'ADD_PROPERTY', 'property_id:7', 'Added new property: breadbox bata lot 1 for client_id: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:50:19'),
(78, 1, 'GENERATE_SCHEDULES', 'property_id:7', 'Generated 50 payment schedules for property: breadbox bata lot 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:50:59'),
(79, 1, 'CREATE_INVOICE', 'invoice_id:9', 'Created invoice: INV-20260112-000195', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:52:22'),
(80, 1, 'RECORD_PAYMENT', 'payment_id:9', 'Recorded payment of ₱150,000.00 for schedule #195', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 10:53:12'),
(81, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-12 12:37:39'),
(82, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-12 12:40:08'),
(83, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 23:46:05'),
(84, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-12 23:47:15'),
(85, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-12 23:51:36'),
(86, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-12 23:51:43'),
(87, 12, 'LOGIN', 'user_id:12', 'Successful login for user: carresfinance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 00:15:37'),
(88, 12, 'LOGOUT', 'user:carresfinance', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 00:15:44'),
(89, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 00:19:36'),
(90, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 01:01:43'),
(91, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 01:02:03'),
(92, 1, 'CREATE_INVOICE', 'invoice_id:10', 'Created invoice: INV-20260113-000155', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 01:05:16'),
(93, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 01:08:03'),
(94, 10, 'LOGIN', 'user_id:10', 'Successful login for user: finance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 01:08:12'),
(95, 10, 'LOGOUT', 'user:finance', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 01:08:28'),
(96, 0, 'LOGIN_FAILED', 'username:carresfinance', 'Invalid credentials attempted (Attempt 1)', '::1', NULL, '2026-01-13 01:08:47'),
(97, 12, 'LOGIN', 'user_id:12', 'Successful login for user: carresfinance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 01:08:56'),
(98, 12, 'LOGOUT', 'user:carresfinance', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 01:09:05'),
(99, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 01:09:12'),
(100, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 01:12:47'),
(101, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 03:31:45'),
(102, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 03:56:12'),
(103, 0, 'LOGIN_FAILED', 'username:finance', 'Invalid credentials attempted (Attempt 1)', '::1', NULL, '2026-01-13 03:56:31'),
(104, 10, 'LOGIN', 'user_id:10', 'Successful login for user: finance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 03:56:46'),
(105, 10, 'LOGOUT', 'user:finance', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 03:56:50'),
(106, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 04:01:08'),
(107, 1, 'GENERATE_NOTIFICATIONS', 'count:74', 'Generated 37 SMS and 37 email notifications', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 04:02:56'),
(108, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 04:09:52'),
(109, 10, 'LOGIN', 'user_id:10', 'Successful login for user: finance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 04:10:04'),
(110, 10, 'LOGOUT', 'user:finance', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 04:10:43'),
(111, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 04:13:15'),
(112, 1, 'LOGOUT', 'user:admin', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 04:13:32'),
(113, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 04:52:22'),
(114, 1, 'ADD_CLIENT', 'client_id:7', 'Added new client: john jats', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 04:53:57'),
(115, 1, 'UPLOAD_DOCUMENT', 'client_id:7', 'Uploaded document: Untitled Diagram.drawio.png', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 04:54:44'),
(116, 1, 'ADD_PROPERTY', 'property_id:8', 'Added new property: Ayala Unit 15 for client_id: 7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 05:06:29'),
(117, 1, 'GENERATE_SCHEDULES', 'property_id:8', 'Generated 60 payment schedules for property: Ayala Unit 15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 05:09:19'),
(118, 1, 'CREATE_INVOICE', 'invoice_id:11', 'Created invoice: INV-20260113-000245', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 05:13:18'),
(119, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 08:18:44'),
(120, 1, 'LOGIN', 'user_id:1', 'Successful login for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-14 14:52:34');

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
(1, 'Juan Dela Cruz', 'juan.delacruz@gmail.com', '09171234567', '123 Rizal St, Manila, Philippines', '2024-09-15 02:30:00', '2025-12-26 13:27:54'),
(2, 'Maria Santos', 'maria.santos@yahoo.com', '09281234567', '456 Bonifacio Ave, Quezon City, Philippines', '2024-10-20 06:20:00', '2025-12-26 13:27:54'),
(3, 'Robert Chen', 'robert.chen@outlook.com', '09391234567', '789 Makati Ave, Makati City, Philippines', '2024-11-10 01:15:00', '2025-12-26 13:27:54'),
(4, 'DA Sionomio', 'da@gmail.com', '09827365211', 'Bata Bacolod City', '2026-01-11 12:34:51', '2026-01-11 12:34:51'),
(5, 'Carres Dizon', 'carres@gmail.com', '09827362514', 'Talisay', '2026-01-12 10:00:10', '2026-01-12 10:00:10'),
(6, 'Julius dela tore', 'juan@gmail.com', '09123564665', 'bacolod city', '2026-01-12 10:48:09', '2026-01-12 10:49:05'),
(7, 'john jats', 'john@gmail.com', '09785643678', 'bacolod city', '2026-01-13 04:53:57', '2026-01-13 04:53:57');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `doc_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`doc_id`, `client_id`, `file_name`, `file_path`, `upload_date`) VALUES
(1, 4, 'ethics.pdf', 'uploads/clients/4/1768134909_696398fd0217b_ethics.pdf', '2026-01-11 12:35:09'),
(2, 5, 'CONSUMER-INSIGHTSBASED-RENTAL-MANAGEMENT-SYSTEM.-CHAP1-3.pdf', 'uploads/clients/5/1768212028_6964c63ccf717_CONSUMER-INSIGHTSBASED-RENTAL-MANAGEMENT-SYSTEM.-CHAP1-3.pdf', '2026-01-12 10:00:28'),
(3, 7, 'Untitled Diagram.drawio.png', 'uploads/clients/7/1768280084_6965d0146fe64_UntitledDiagram.drawio.png', '2026-01-13 04:54:44');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL,
  `invoice_no` varchar(50) DEFAULT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `property_id` int(11) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `status` enum('unpaid','paid','overdue') NOT NULL DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`invoice_id`, `invoice_no`, `schedule_id`, `property_id`, `invoice_date`, `due_date`, `total_amount`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'INV-20241025-000001', 1, NULL, '2024-10-25', '2024-11-01', 50000.00, 'paid', 'First installment', '2024-10-25 02:00:00', '2025-12-26 13:27:54'),
(2, 'INV-20241125-000002', 2, NULL, '2024-11-25', '2024-12-01', 50000.00, 'paid', 'Second installment', '2024-11-25 02:00:00', '2025-12-26 13:27:54'),
(3, 'INV-20241215-000003', 3, NULL, '2024-12-15', '2025-01-01', 50000.00, 'unpaid', 'Third installment - Partial payment received', '2024-12-15 02:00:00', '2025-12-26 13:27:54'),
(4, 'INV-20241015-000025', 25, NULL, '2024-10-15', '2024-11-01', 100000.00, 'paid', 'Payment 1 of 18', '2024-10-15 02:00:00', '2025-12-26 13:27:54'),
(5, 'INV-20241115-000026', 26, NULL, '2024-11-15', '2024-12-01', 100000.00, 'unpaid', 'Payment 2 of 18', '2024-11-15 02:00:00', '2025-12-26 13:27:54'),
(6, 'INV-20241120-000043', 43, NULL, '2024-11-20', '2024-12-01', 200000.00, 'paid', 'Office space payment 1 of 12', '2024-11-20 02:00:00', '2025-12-26 13:27:54'),
(7, 'INV-20260111-000115', 115, NULL, '2026-01-11', '2026-02-10', 150000.00, 'unpaid', '', '2026-01-11 12:40:23', '2026-01-11 12:40:23'),
(8, 'INV-20260112-000155', 155, NULL, '2026-01-12', '2026-02-11', 50000.00, 'unpaid', '', '2026-01-12 10:02:50', '2026-01-12 10:02:50'),
(9, 'INV-20260112-000195', 195, NULL, '2026-01-12', '2026-02-11', 200000.00, 'unpaid', 'baayad na', '2026-01-12 10:52:22', '2026-01-12 10:52:22'),
(10, 'INV-20260113-000155', 155, NULL, '2026-01-13', '2026-02-12', 50000.00, 'unpaid', 'bayad na', '2026-01-13 01:05:16', '2026-01-13 01:05:16'),
(11, 'INV-20260113-000245', 245, NULL, '2026-01-13', '2026-02-12', 25000.00, 'unpaid', '', '2026-01-13 05:13:18', '2026-01-13 05:13:18');

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

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notif_id`, `client_id`, `message`, `date_created`, `type`, `status`, `sent_at`) VALUES
(1, 1, 'Payment reminder: Schedule 3 for Sunrise Residences Unit 12A is due on 2025-01-01. Amount: ???50,000.00', '2024-12-15 00:00:00', 'email', 'sent', '2024-12-15 00:05:00'),
(2, 2, 'Payment reminder: Schedule 2 for Green Valley Townhouse Unit 8 is due on 2024-12-01. Amount: ???100,000.00', '2024-11-15 00:00:00', 'sms', 'sent', '2024-11-15 00:02:00'),
(3, 3, 'Payment reminder: Schedule 2 for Metro Plaza Office Space 301 is due on 2025-01-01. Amount: ???200,000.00', '2024-12-20 02:00:00', 'email', 'sent', '2025-12-26 13:31:26'),
(4, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 361 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'sent', '2026-01-11 12:42:15'),
(5, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 361 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'sent', '2026-01-11 12:42:17'),
(6, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 330 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'sent', '2026-01-12 10:04:36'),
(7, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 330 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(8, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 302 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(9, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 302 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(10, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 271 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(11, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 271 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(12, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 241 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(13, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 241 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(14, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 210 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(15, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 210 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(16, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 180 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(17, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 180 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(18, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 149 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(19, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 149 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(20, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 118 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(21, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 118 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(22, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 88 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(23, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 88 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(24, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 57 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(25, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 57 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(26, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 27 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(27, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 27 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(28, 1, 'Reminder: Your payment for Sunrise Residences Unit 12A is due on January 01, 2026. Amount Due: ₱50,000.00. Thank you for your prompt payment.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(29, 1, 'Reminder: Your payment for Sunrise Residences Unit 12A is due on January 01, 2026. Amount Due: ₱50,000.00. Thank you for your prompt payment.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(30, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 361 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(31, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 361 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(32, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 330 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(33, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 330 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(34, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 302 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(35, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 302 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(36, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 271 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(37, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 271 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(38, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 241 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(39, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 241 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(40, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 210 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(41, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 210 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(42, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 180 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(43, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 180 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(44, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 149 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(45, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 149 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(46, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 118 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(47, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 118 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(48, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 88 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(49, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 88 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(50, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 57 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(51, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 57 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(52, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 27 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(53, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 27 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(54, 2, 'Reminder: Your payment for Green Valley Townhouse Unit 8 is due on January 01, 2026. Amount Due: ₱100,000.00. Thank you for your prompt payment.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(55, 2, 'Reminder: Your payment for Green Valley Townhouse Unit 8 is due on January 01, 2026. Amount Due: ₱100,000.00. Thank you for your prompt payment.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(56, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 330 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(57, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 330 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(58, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 302 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(59, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 302 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(60, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 271 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(61, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 271 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(62, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 241 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(63, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 241 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(64, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 210 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(65, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 210 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(66, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 180 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(67, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 180 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(68, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 149 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(69, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 149 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(70, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 118 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(71, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 118 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(72, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 88 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(73, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 88 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(74, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 57 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(75, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 57 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(76, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 27 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'sms', 'pending', NULL),
(77, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 27 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2025-12-28 06:05:09', 'email', 'pending', NULL),
(78, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 377 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(79, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 377 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(80, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 346 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(81, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 346 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(82, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 318 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(83, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 318 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(84, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 287 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(85, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 287 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(86, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 257 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(87, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 257 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(88, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 226 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(89, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 226 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(90, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 196 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(91, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 196 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(92, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 165 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(93, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 165 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(94, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 134 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(95, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 134 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(96, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 104 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(97, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 104 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(98, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 73 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(99, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 73 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(100, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 43 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(101, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 43 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(102, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 12 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(103, 1, 'URGENT: Your payment for Sunrise Residences Unit 12A is OVERDUE by 12 day(s). Amount Due: ₱50,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(104, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 377 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(105, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 377 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(106, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 346 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(107, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 346 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(108, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 318 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(109, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 318 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(110, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 287 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(111, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 287 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(112, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 257 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(113, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 257 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(114, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 226 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(115, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 226 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(116, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 196 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(117, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 196 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(118, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 165 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(119, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 165 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(120, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 134 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(121, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 134 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(122, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 104 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(123, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 104 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(124, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 73 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(125, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 73 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(126, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 43 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(127, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 43 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(128, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 12 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(129, 2, 'URGENT: Your payment for Green Valley Townhouse Unit 8 is OVERDUE by 12 day(s). Amount Due: ₱100,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(130, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 346 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(131, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 346 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(132, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 318 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(133, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 318 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(134, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 287 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(135, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 287 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(136, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 257 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(137, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 257 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(138, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 226 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(139, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 226 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(140, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 196 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(141, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 196 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(142, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 165 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(143, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 165 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(144, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 134 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(145, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 134 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(146, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 104 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(147, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 104 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(148, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 73 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(149, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 73 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL),
(150, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 43 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'sms', 'pending', NULL),
(151, 3, 'URGENT: Your payment for Metro Plaza Office Space 301 is OVERDUE by 43 day(s). Amount Due: ₱200,000.00. Please settle your payment immediately.', '2026-01-13 04:02:56', 'email', 'pending', NULL);

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
(1, 1, 50000.00, '2025-08-05', 'REC-20241105-001', '2025-08-05 02:00:00', '2025-12-28 05:34:44'),
(2, 2, 50000.00, '2025-09-03', 'REC-20241203-001', '2025-09-03 03:30:00', '2025-12-28 05:34:44'),
(3, 3, 25000.00, '2025-10-15', 'REC-20241220-001', '2025-10-15 06:00:00', '2025-12-28 05:34:44'),
(4, 25, 100000.00, '2025-11-10', 'REC-20241115-002', '2025-11-10 07:00:00', '2025-12-28 05:34:45'),
(5, 43, 200000.00, '2025-12-05', 'REC-20241210-003', '2025-12-05 01:30:00', '2025-12-28 05:34:45'),
(6, 55, 25000.00, '2025-12-28', '324789437983', '2025-12-28 06:02:52', '2025-12-28 06:02:52'),
(7, 115, 99000.00, '2026-01-11', NULL, '2026-01-11 12:41:27', '2026-01-11 12:41:27'),
(8, 155, 34000.00, '2026-01-12', '8479437', '2026-01-12 10:03:36', '2026-01-12 10:03:36'),
(9, 195, 150000.00, '2026-01-12', '2', '2026-01-12 10:53:12', '2026-01-12 10:53:12');

--
-- Triggers `payments`
--
DELIMITER $$
CREATE TRIGGER `trg_after_payment_insert` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
    DECLARE v_total_paid DECIMAL(12,2);
    DECLARE v_amount_due DECIMAL(12,2);
    DECLARE v_property_id INT;
    
    
    SELECT 
        ps.amount_due,
        ps.property_id
    INTO 
        v_amount_due,
        v_property_id
    FROM payment_schedules ps
    WHERE ps.schedule_id = NEW.schedule_id;
    
    
    SELECT COALESCE(SUM(amount_paid), 0)
    INTO v_total_paid
    FROM payments
    WHERE schedule_id = NEW.schedule_id;
    
    
    IF v_total_paid >= v_amount_due THEN
        
        
        UPDATE payment_schedules
        SET status = 'paid'
        WHERE schedule_id = NEW.schedule_id;
        
        
        UPDATE invoices 
        SET status = 'paid',
            updated_at = CURRENT_TIMESTAMP
        WHERE schedule_id = NEW.schedule_id
        AND status = 'unpaid';
        
        
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

-- --------------------------------------------------------

--
-- Table structure for table `payment_schedules`
--

CREATE TABLE `payment_schedules` (
  `schedule_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `schedule_number` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount_due` decimal(12,2) NOT NULL,
  `status` enum('pending','paid','overdue') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_schedules`
--

INSERT INTO `payment_schedules` (`schedule_id`, `property_id`, `schedule_number`, `due_date`, `amount_due`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2024-11-01', 50000.00, 'paid', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(2, 1, 2, '2024-12-01', 50000.00, 'paid', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(3, 1, 3, '2025-01-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(4, 1, 4, '2025-02-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(5, 1, 5, '2025-03-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(6, 1, 6, '2025-04-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(7, 1, 7, '2025-05-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(8, 1, 8, '2025-06-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(9, 1, 9, '2025-07-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(10, 1, 10, '2025-08-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(11, 1, 11, '2025-09-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(12, 1, 12, '2025-10-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(13, 1, 13, '2025-11-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(14, 1, 14, '2025-12-01', 50000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(15, 1, 15, '2026-01-01', 50000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(16, 1, 16, '2026-02-01', 50000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(17, 1, 17, '2026-03-01', 50000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(18, 1, 18, '2026-04-01', 50000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(19, 1, 19, '2026-05-01', 50000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(20, 1, 20, '2026-06-01', 50000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(21, 1, 21, '2026-07-01', 50000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(22, 1, 22, '2026-08-01', 50000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(23, 1, 23, '2026-09-01', 50000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(24, 1, 24, '2026-10-01', 50000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(25, 2, 1, '2024-12-01', 100000.00, 'paid', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(26, 2, 2, '2025-01-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(27, 2, 3, '2025-02-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(28, 2, 4, '2025-03-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(29, 2, 5, '2025-04-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(30, 2, 6, '2025-05-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(31, 2, 7, '2025-06-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(32, 2, 8, '2025-07-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(33, 2, 9, '2025-08-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(34, 2, 10, '2025-09-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(35, 2, 11, '2025-10-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(36, 2, 12, '2025-11-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(37, 2, 13, '2025-12-01', 100000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(38, 2, 14, '2026-01-01', 100000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(39, 2, 15, '2026-02-01', 100000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(40, 2, 16, '2026-03-01', 100000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(41, 2, 17, '2026-04-01', 100000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(42, 2, 18, '2026-05-01', 100000.00, 'pending', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(43, 3, 1, '2025-01-01', 200000.00, 'paid', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(44, 3, 2, '2025-02-01', 200000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(45, 3, 3, '2025-03-01', 200000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(46, 3, 4, '2025-04-01', 200000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(47, 3, 5, '2025-05-01', 200000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(48, 3, 6, '2025-06-01', 200000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(49, 3, 7, '2025-07-01', 200000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(50, 3, 8, '2025-08-01', 200000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(51, 3, 9, '2025-09-01', 200000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(52, 3, 10, '2025-10-01', 200000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(53, 3, 11, '2025-11-01', 200000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(54, 3, 12, '2025-12-01', 200000.00, 'overdue', '2025-12-26 13:27:54', '2025-12-26 13:27:54'),
(55, 4, 1, '2026-01-28', 25000.00, 'paid', '2025-12-28 05:57:31', '2025-12-28 06:02:52'),
(56, 4, 2, '2026-02-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(57, 4, 3, '2026-03-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(58, 4, 4, '2026-04-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(59, 4, 5, '2026-05-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(60, 4, 6, '2026-06-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(61, 4, 7, '2026-07-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(62, 4, 8, '2026-08-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(63, 4, 9, '2026-09-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(64, 4, 10, '2026-10-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(65, 4, 11, '2026-11-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(66, 4, 12, '2026-12-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(67, 4, 13, '2027-01-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(68, 4, 14, '2027-02-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(69, 4, 15, '2027-03-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(70, 4, 16, '2027-04-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(71, 4, 17, '2027-05-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(72, 4, 18, '2027-06-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(73, 4, 19, '2027-07-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(74, 4, 20, '2027-08-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(75, 4, 21, '2027-09-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(76, 4, 22, '2027-10-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(77, 4, 23, '2027-11-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(78, 4, 24, '2027-12-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(79, 4, 25, '2028-01-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(80, 4, 26, '2028-02-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(81, 4, 27, '2028-03-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(82, 4, 28, '2028-04-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(83, 4, 29, '2028-05-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(84, 4, 30, '2028-06-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(85, 4, 31, '2028-07-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(86, 4, 32, '2028-08-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(87, 4, 33, '2028-09-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(88, 4, 34, '2028-10-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(89, 4, 35, '2028-11-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(90, 4, 36, '2028-12-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(91, 4, 37, '2029-01-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(92, 4, 38, '2029-02-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(93, 4, 39, '2029-03-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(94, 4, 40, '2029-04-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(95, 4, 41, '2029-05-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(96, 4, 42, '2029-06-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(97, 4, 43, '2029-07-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(98, 4, 44, '2029-08-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(99, 4, 45, '2029-09-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(100, 4, 46, '2029-10-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(101, 4, 47, '2029-11-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(102, 4, 48, '2029-12-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(103, 4, 49, '2030-01-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(104, 4, 50, '2030-02-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(105, 4, 51, '2030-03-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(106, 4, 52, '2030-04-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(107, 4, 53, '2030-05-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(108, 4, 54, '2030-06-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(109, 4, 55, '2030-07-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(110, 4, 56, '2030-08-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(111, 4, 57, '2030-09-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(112, 4, 58, '2030-10-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(113, 4, 59, '2030-11-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(114, 4, 60, '2030-12-28', 25000.00, 'pending', '2025-12-28 05:57:31', '2025-12-28 05:57:31'),
(115, 5, 1, '2026-02-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(116, 5, 2, '2026-03-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(117, 5, 3, '2026-04-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(118, 5, 4, '2026-05-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(119, 5, 5, '2026-06-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(120, 5, 6, '2026-07-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(121, 5, 7, '2026-08-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(122, 5, 8, '2026-09-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(123, 5, 9, '2026-10-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(124, 5, 10, '2026-11-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(125, 5, 11, '2026-12-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(126, 5, 12, '2027-01-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(127, 5, 13, '2027-02-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(128, 5, 14, '2027-03-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(129, 5, 15, '2027-04-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(130, 5, 16, '2027-05-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(131, 5, 17, '2027-06-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(132, 5, 18, '2027-07-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(133, 5, 19, '2027-08-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(134, 5, 20, '2027-09-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(135, 5, 21, '2027-10-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(136, 5, 22, '2027-11-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(137, 5, 23, '2027-12-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(138, 5, 24, '2028-01-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(139, 5, 25, '2028-02-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(140, 5, 26, '2028-03-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(141, 5, 27, '2028-04-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(142, 5, 28, '2028-05-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(143, 5, 29, '2028-06-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(144, 5, 30, '2028-07-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(145, 5, 31, '2028-08-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(146, 5, 32, '2028-09-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(147, 5, 33, '2028-10-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(148, 5, 34, '2028-11-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(149, 5, 35, '2028-12-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(150, 5, 36, '2029-01-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(151, 5, 37, '2029-02-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(152, 5, 38, '2029-03-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(153, 5, 39, '2029-04-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(154, 5, 40, '2029-05-11', 150000.00, 'pending', '2026-01-11 12:38:07', '2026-01-11 12:38:07'),
(155, 6, 1, '2026-02-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(156, 6, 2, '2026-03-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(157, 6, 3, '2026-04-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(158, 6, 4, '2026-05-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(159, 6, 5, '2026-06-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(160, 6, 6, '2026-07-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(161, 6, 7, '2026-08-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(162, 6, 8, '2026-09-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(163, 6, 9, '2026-10-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(164, 6, 10, '2026-11-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(165, 6, 11, '2026-12-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(166, 6, 12, '2027-01-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(167, 6, 13, '2027-02-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(168, 6, 14, '2027-03-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(169, 6, 15, '2027-04-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(170, 6, 16, '2027-05-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(171, 6, 17, '2027-06-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(172, 6, 18, '2027-07-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(173, 6, 19, '2027-08-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(174, 6, 20, '2027-09-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(175, 6, 21, '2027-10-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(176, 6, 22, '2027-11-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(177, 6, 23, '2027-12-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(178, 6, 24, '2028-01-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(179, 6, 25, '2028-02-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(180, 6, 26, '2028-03-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(181, 6, 27, '2028-04-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(182, 6, 28, '2028-05-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(183, 6, 29, '2028-06-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(184, 6, 30, '2028-07-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(185, 6, 31, '2028-08-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(186, 6, 32, '2028-09-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(187, 6, 33, '2028-10-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(188, 6, 34, '2028-11-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(189, 6, 35, '2028-12-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(190, 6, 36, '2029-01-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(191, 6, 37, '2029-02-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(192, 6, 38, '2029-03-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(193, 6, 39, '2029-04-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(194, 6, 40, '2029-05-12', 50000.00, 'pending', '2026-01-12 10:02:05', '2026-01-12 10:02:05'),
(195, 7, 1, '2026-02-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(196, 7, 2, '2026-03-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(197, 7, 3, '2026-04-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(198, 7, 4, '2026-05-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(199, 7, 5, '2026-06-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(200, 7, 6, '2026-07-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(201, 7, 7, '2026-08-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(202, 7, 8, '2026-09-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(203, 7, 9, '2026-10-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(204, 7, 10, '2026-11-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(205, 7, 11, '2026-12-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(206, 7, 12, '2027-01-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(207, 7, 13, '2027-02-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(208, 7, 14, '2027-03-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(209, 7, 15, '2027-04-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(210, 7, 16, '2027-05-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(211, 7, 17, '2027-06-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(212, 7, 18, '2027-07-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(213, 7, 19, '2027-08-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(214, 7, 20, '2027-09-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(215, 7, 21, '2027-10-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(216, 7, 22, '2027-11-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(217, 7, 23, '2027-12-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(218, 7, 24, '2028-01-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(219, 7, 25, '2028-02-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(220, 7, 26, '2028-03-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(221, 7, 27, '2028-04-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(222, 7, 28, '2028-05-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(223, 7, 29, '2028-06-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(224, 7, 30, '2028-07-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(225, 7, 31, '2028-08-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(226, 7, 32, '2028-09-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(227, 7, 33, '2028-10-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(228, 7, 34, '2028-11-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(229, 7, 35, '2028-12-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(230, 7, 36, '2029-01-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(231, 7, 37, '2029-02-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(232, 7, 38, '2029-03-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(233, 7, 39, '2029-04-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(234, 7, 40, '2029-05-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(235, 7, 41, '2029-06-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(236, 7, 42, '2029-07-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(237, 7, 43, '2029-08-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(238, 7, 44, '2029-09-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(239, 7, 45, '2029-10-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(240, 7, 46, '2029-11-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(241, 7, 47, '2029-12-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(242, 7, 48, '2030-01-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(243, 7, 49, '2030-02-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(244, 7, 50, '2030-03-12', 200000.00, 'pending', '2026-01-12 10:50:59', '2026-01-12 10:50:59'),
(245, 8, 1, '2026-01-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(246, 8, 2, '2026-02-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(247, 8, 3, '2026-03-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(248, 8, 4, '2026-04-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(249, 8, 5, '2026-05-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(250, 8, 6, '2026-06-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(251, 8, 7, '2026-07-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(252, 8, 8, '2026-08-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(253, 8, 9, '2026-09-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(254, 8, 10, '2026-10-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(255, 8, 11, '2026-11-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(256, 8, 12, '2026-12-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(257, 8, 13, '2027-01-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(258, 8, 14, '2027-02-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(259, 8, 15, '2027-03-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(260, 8, 16, '2027-04-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(261, 8, 17, '2027-05-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(262, 8, 18, '2027-06-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(263, 8, 19, '2027-07-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(264, 8, 20, '2027-08-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(265, 8, 21, '2027-09-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(266, 8, 22, '2027-10-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(267, 8, 23, '2027-11-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(268, 8, 24, '2027-12-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(269, 8, 25, '2028-01-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(270, 8, 26, '2028-02-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(271, 8, 27, '2028-03-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(272, 8, 28, '2028-04-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(273, 8, 29, '2028-05-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(274, 8, 30, '2028-06-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(275, 8, 31, '2028-07-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(276, 8, 32, '2028-08-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(277, 8, 33, '2028-09-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(278, 8, 34, '2028-10-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(279, 8, 35, '2028-11-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(280, 8, 36, '2028-12-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(281, 8, 37, '2029-01-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(282, 8, 38, '2029-02-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(283, 8, 39, '2029-03-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(284, 8, 40, '2029-04-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(285, 8, 41, '2029-05-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(286, 8, 42, '2029-06-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(287, 8, 43, '2029-07-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(288, 8, 44, '2029-08-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(289, 8, 45, '2029-09-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(290, 8, 46, '2029-10-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(291, 8, 47, '2029-11-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(292, 8, 48, '2029-12-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(293, 8, 49, '2030-01-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(294, 8, 50, '2030-02-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(295, 8, 51, '2030-03-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(296, 8, 52, '2030-04-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(297, 8, 53, '2030-05-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(298, 8, 54, '2030-06-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(299, 8, 55, '2030-07-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(300, 8, 56, '2030-08-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(301, 8, 57, '2030-09-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(302, 8, 58, '2030-10-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(303, 8, 59, '2030-11-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19'),
(304, 8, 60, '2030-12-28', 25000.00, 'pending', '2026-01-13 05:09:19', '2026-01-13 05:09:19');

--
-- Triggers `payment_schedules`
--
DELIMITER $$
CREATE TRIGGER `sync_invoice_on_schedule_update` AFTER UPDATE ON `payment_schedules` FOR EACH ROW BEGIN
    
    IF NEW.status = 'paid' AND OLD.status != 'paid' THEN
        
        
        UPDATE invoices 
        SET status = 'paid',
            updated_at = CURRENT_TIMESTAMP
        WHERE schedule_id = NEW.schedule_id
        AND status = 'unpaid';
        
        
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
(1, 1, 'Sunrise Residences Unit 12A', 1200000.00, '2024-10-01', 24, '2024-09-15 02:45:00', '2025-12-26 13:27:54'),
(2, 2, 'Green Valley Townhouse Unit 8', 1800000.00, '2024-11-01', 18, '2024-10-20 07:00:00', '2025-12-26 13:27:54'),
(3, 3, 'Metro Plaza Office Space 301', 2400000.00, '2024-12-01', 12, '2024-11-10 02:00:00', '2025-12-26 13:27:54'),
(4, 3, 'Ayala Unit 15', 1500000.00, '2025-12-28', 60, '2025-12-28 05:56:04', '2025-12-28 05:56:04'),
(5, 4, 'San Lorenzo Carlen Property Block 13 Lot 43', 6000000.00, '2026-01-11', 40, '2026-01-11 12:36:42', '2026-01-11 12:36:42'),
(6, 5, 'Bata Block 12 Lot 2', 2000000.00, '2026-01-12', 40, '2026-01-12 10:01:35', '2026-01-12 10:01:35'),
(7, 6, 'breadbox bata lot 1', 10000000.00, '2026-01-12', 50, '2026-01-12 10:50:19', '2026-01-12 10:50:19'),
(8, 7, 'Ayala Unit 15', 1500000.00, '2025-12-28', 60, '2026-01-13 05:06:29', '2026-01-13 05:06:29');

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
(1, 'admin', '$2y$10$lIG.2306l9SXr7JOOmqDIuKmtPKDZrsuuzb6ZzEe4DZ7.dXPKhxf2', 'admin', '2023-12-31 16:00:00', '2025-12-28 05:22:31'),
(10, 'finance', '$2y$10$o4c/IxBsn5hwHnDuGpMMbe3AXgc1ZeNJ8kftnXxvHz7SDnBNdomPy', 'finance', '2025-12-28 05:23:54', '2025-12-28 05:23:54'),
(12, 'carresfinance', '$2y$10$mOfZXGOkjodj7ABeGWSE5uNcIm7eK2B4i45K34YZ2xBKK2Bh/DDWW', 'finance', '2026-01-12 10:05:01', '2026-01-12 10:05:01');

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
  ADD UNIQUE KEY `unique_property_schedule` (`property_id`,`schedule_number`),
  ADD KEY `idx_property_id` (`property_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_schedule_number` (`schedule_number`);

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=152;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=305;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `property_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `payment_schedules` (`schedule_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `payment_schedules` (`schedule_id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  ADD CONSTRAINT `payment_schedules_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
