-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Aug 14, 2025 at 09:27 PM
-- Server version: 10.11.10-MariaDB-log
-- PHP Version: 8.3.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `china_ababel`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`china_ababel`@`localhost` PROCEDURE `clean_old_sync_logs` ()   BEGIN
    -- Delete sync logs older than 90 days
    DELETE FROM loading_sync_log 
    WHERE synced_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Delete API logs older than 30 days (Port Sudan)
    DELETE FROM api_sync_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END$$

CREATE DEFINER=`china_ababel`@`localhost` PROCEDURE `process_partial_payment` (IN `p_transaction_id` INT, IN `p_payment_currency` VARCHAR(3), IN `p_payment_amount` DECIMAL(15,2), IN `p_bank_name` VARCHAR(100), IN `p_user_id` INT)   BEGIN
    DECLARE v_client_id INT;
    DECLARE v_loading_id INT;
    DECLARE v_transaction_no VARCHAR(50);
    
    -- Get transaction details
    SELECT client_id, loading_id, transaction_no 
    INTO v_client_id, v_loading_id, v_transaction_no
    FROM transactions 
    WHERE id = p_transaction_id;
    
    -- Insert payment transaction
    INSERT INTO transactions (
        transaction_no,
        client_id,
        transaction_type_id,
        transaction_date,
        description,
        bank_name,
        loading_id,
        status,
        created_by,
        approved_by,
        approved_at
    ) VALUES (
        CONCAT('PAY-', DATE_FORMAT(NOW(), '%Y%m%d-%H%i%s')),
        v_client_id,
        2, -- Assuming 2 is payment type
        CURDATE(),
        CONCAT('Partial payment for ', v_transaction_no),
        p_bank_name,
        v_loading_id,
        'approved',
        p_user_id,
        p_user_id,
        NOW()
    );
    
    -- Update payment amounts based on currency
    SET @payment_id = LAST_INSERT_ID();
    
    CASE p_payment_currency
        WHEN 'RMB' THEN
            UPDATE transactions SET payment_rmb = p_payment_amount WHERE id = @payment_id;
        WHEN 'USD' THEN
            UPDATE transactions SET payment_usd = p_payment_amount WHERE id = @payment_id;
        WHEN 'SDG' THEN
            UPDATE transactions SET payment_sdg = p_payment_amount WHERE id = @payment_id;
        WHEN 'AED' THEN
            UPDATE transactions SET payment_aed = p_payment_amount WHERE id = @payment_id;
    END CASE;
    
    SELECT @payment_id AS payment_id;
END$$

CREATE DEFINER=`china_ababel`@`localhost` PROCEDURE `retry_failed_syncs` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_loading_id INT;
    DECLARE cur CURSOR FOR 
        SELECT id FROM loadings 
        WHERE sync_status = 'failed' 
        AND sync_attempts < 3 
        AND office = 'port_sudan';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_loading_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Update sync_attempts
        UPDATE loadings 
        SET sync_attempts = sync_attempts + 1 
        WHERE id = v_loading_id;
        
        -- Log retry attempt
        INSERT INTO loading_sync_log (loading_id, action, status, error_message)
        VALUES (v_loading_id, 'retry', 'pending', 'Retry attempt scheduled');
    END LOOP;
    
    CLOSE cur;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `api_sync_log`
--

CREATE TABLE `api_sync_log` (
  `id` int(11) NOT NULL,
  `endpoint` varchar(100) NOT NULL,
  `method` varchar(10) NOT NULL,
  `china_loading_id` int(11) DEFAULT NULL,
  `container_id` int(11) DEFAULT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `response_code` int(11) DEFAULT NULL,
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `api_sync_log`
--

INSERT INTO `api_sync_log` (`id`, `endpoint`, `method`, `china_loading_id`, `container_id`, `request_data`, `response_code`, `response_data`, `ip_address`, `created_at`) VALUES
(1, 'https://ababel.net/app/api/china_sync.php', 'POST', 11, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":11,\"entry_date\":\"2025-07-19\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"80\",\"carton_count\":12,\"container_number\":\"CMAU7702683\",\"bill_number\":\"CLM-20250719-6442\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-18\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":242,\\\"message\\\":\\\"Container already exists\\\",\\\"existing\\\":true}\",\"data\":{\"success\":true,\"container_id\":242,\"message\":\"Container already exists\",\"existing\":true},\"container_id\":242,\"message\":\"Container already exists\",\"existing\":true}', '172.71.103.169', '2025-07-19 10:55:30'),
(2, 'https://ababel.net/app/api/china_sync.php', 'POST', 10, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":10,\"entry_date\":\"2025-07-19\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"7\",\"carton_count\":400,\"container_number\":\"CMAU7702683\",\"bill_number\":\"CLM-20250719-5499\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-18\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":243,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":{\"success\":true,\"container_id\":243,\"message\":\"Container created successfully\"},\"container_id\":243,\"message\":\"Container created successfully\"}', '172.71.103.169', '2025-07-19 10:57:06'),
(3, 'https://ababel.net/app/api/china_sync.php', 'POST', 13, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":13,\"entry_date\":\"2025-07-21\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"7\",\"carton_count\":1,\"container_number\":\"CMAU7702683\",\"bill_number\":\"CLM-20250721-6088\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-20\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":248,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":{\"success\":true,\"container_id\":248,\"message\":\"Container created successfully\"},\"container_id\":248,\"message\":\"Container created successfully\"}', '104.23.168.113', '2025-07-21 07:02:55'),
(4, 'https://ababel.net/app/api/china_sync.php', 'POST', 15, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":15,\"entry_date\":\"2025-07-24\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"64\",\"carton_count\":12,\"container_number\":\"CMAU7702685\",\"bill_number\":\"CLM-20250724-4036\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-23\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":250,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":{\"success\":true,\"container_id\":250,\"message\":\"Container created successfully\"},\"container_id\":250,\"message\":\"Container created successfully\"}', '172.71.103.173', '2025-07-28 08:48:35'),
(5, 'https://ababel.net/app/api/china_sync.php', 'POST', 16, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":16,\"entry_date\":\"2025-07-30\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"76\",\"carton_count\":12,\"container_number\":\"CMAU7702685\",\"bill_number\":\"CLM-20250730-3450\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-29\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":252,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":{\"success\":true,\"container_id\":252,\"message\":\"Container created successfully\"},\"container_id\":252,\"message\":\"Container created successfully\"}', '172.71.150.126', '2025-07-30 13:16:58'),
(6, 'https://ababel.net/app/api/china_sync.php', 'POST', 19, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":19,\"entry_date\":\"2025-08-11\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"24356\",\"carton_count\":121,\"container_number\":\"CMAU7702360\",\"bill_number\":\"CLM-20250811-1532\",\"category\":\"General Cargo\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-09-10\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n{\\\"success\\\":true,\\\"container_id\\\":254,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":null}', '172.68.234.21', '2025-08-10 17:06:19'),
(7, 'https://ababel.net/app/api/china_sync.php', 'POST', 18, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":18,\"entry_date\":\"2025-08-07\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"7642\",\"carton_count\":12,\"container_number\":\"CMAU7702340\",\"bill_number\":\"CLM-20250807-8360\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-09-06\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n{\\\"success\\\":true,\\\"container_id\\\":255,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":null}', '172.68.234.21', '2025-08-10 17:06:26'),
(8, 'https://ababel.net/app/api/china_sync.php', 'POST', 17, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":17,\"entry_date\":\"2025-08-07\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"786\",\"carton_count\":12,\"container_number\":\"CMAU7702685\",\"bill_number\":\"CLM-20250807-6673\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-09-06\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n{\\\"success\\\":true,\\\"container_id\\\":253,\\\"message\\\":\\\"Container already exists\\\",\\\"existing\\\":true}\",\"data\":null}', '172.68.234.21', '2025-08-10 17:06:33');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cashbox_movements`
--

CREATE TABLE `cashbox_movements` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `movement_date` date NOT NULL,
  `movement_type` enum('in','out','transfer') NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `amount_rmb` decimal(15,2) DEFAULT 0.00,
  `amount_usd` decimal(15,2) DEFAULT 0.00,
  `amount_sdg` decimal(15,2) DEFAULT 0.00,
  `amount_aed` decimal(15,2) DEFAULT 0.00,
  `bank_name` varchar(100) DEFAULT NULL,
  `tt_number` varchar(50) DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `balance_after_rmb` decimal(15,2) DEFAULT NULL,
  `balance_after_usd` decimal(15,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `cashbox_movements`
--

INSERT INTO `cashbox_movements` (`id`, `transaction_id`, `movement_date`, `movement_type`, `category`, `amount_rmb`, `amount_usd`, `amount_sdg`, `amount_aed`, `bank_name`, `tt_number`, `receipt_no`, `description`, `balance_after_rmb`, `balance_after_usd`, `created_by`, `created_at`) VALUES
(6, 13, '2025-07-24', 'in', 'payment_received', 2424.00, 0.00, 0.00, 0.00, '', NULL, NULL, 'Payment from Mohamed Abdulla Ali Farh - TRX-2025-000002', NULL, NULL, 1, '2025-07-24 15:13:26');

-- --------------------------------------------------------

--
-- Stand-in structure for view `cashbox_summary`
-- (See below for the actual view)
--
CREATE TABLE `cashbox_summary` (
`balance_rmb` decimal(37,2)
,`balance_usd` decimal(37,2)
,`balance_sdg` decimal(37,2)
,`balance_aed` decimal(37,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `client_code` varchar(50) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL COMMENT 'Name in English',
  `name_ar` varchar(255) DEFAULT NULL COMMENT 'Name in Arabic',
  `phone` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `balance_rmb` decimal(15,2) DEFAULT 0.00,
  `balance_usd` decimal(15,2) DEFAULT 0.00,
  `balance_sdg` decimal(15,2) DEFAULT 0.00,
  `credit_limit` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `client_code`, `name`, `name_ar`, `phone`, `email`, `address`, `balance_rmb`, `balance_usd`, `balance_sdg`, `credit_limit`, `status`, `created_at`, `updated_at`) VALUES
(1, '1', 'Mohamed Abdulla Ali Farh', 'محمد عبدالله علي', '0910564187', 'hmadakhan686@gmail.com', 'Portsudan', 10103.00, 62.00, 0.00, 0.00, 'active', '2025-07-16 13:58:13', '2025-08-12 13:30:48');

-- --------------------------------------------------------

--
-- Stand-in structure for view `client_balances`
-- (See below for the actual view)
--
CREATE TABLE `client_balances` (
`id` int(11)
,`name` varchar(255)
,`client_code` varchar(50)
,`total_balance_rmb` decimal(37,2)
,`total_balance_usd` decimal(37,2)
,`transaction_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `loadings`
--

CREATE TABLE `loadings` (
  `id` int(11) NOT NULL,
  `loading_no` varchar(50) NOT NULL,
  `shipping_date` date NOT NULL,
  `actual_shipping_date` date DEFAULT NULL,
  `arrival_date` date DEFAULT NULL,
  `claim_number` varchar(50) DEFAULT NULL,
  `bol_number` varchar(50) DEFAULT NULL,
  `bol_issued_date` date DEFAULT NULL,
  `bol_issued_by` int(11) DEFAULT NULL,
  `container_no` varchar(50) NOT NULL,
  `bl_number` varchar(50) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_code` varchar(20) DEFAULT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `item_description` text DEFAULT NULL,
  `cartons_count` int(11) DEFAULT 0,
  `purchase_amount` decimal(15,2) DEFAULT 0.00,
  `commission_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_usd` decimal(15,2) DEFAULT 0.00,
  `total_with_shipping` decimal(15,2) DEFAULT 0.00,
  `office` enum('port_sudan','uae','tanzania','egypt') DEFAULT NULL,
  `status` enum('pending','shipped','arrived','cleared','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sync_status` enum('pending','synced','failed') DEFAULT 'pending',
  `sync_attempts` int(11) DEFAULT 0,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `port_sudan_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loadings`
--

INSERT INTO `loadings` (`id`, `loading_no`, `shipping_date`, `actual_shipping_date`, `arrival_date`, `claim_number`, `bol_number`, `bol_issued_date`, `bol_issued_by`, `container_no`, `bl_number`, `client_id`, `client_code`, `client_name`, `item_description`, `cartons_count`, `purchase_amount`, `commission_amount`, `total_amount`, `shipping_usd`, `total_with_shipping`, `office`, `status`, `notes`, `created_by`, `created_at`, `updated_by`, `updated_at`, `sync_status`, `sync_attempts`, `last_sync_at`, `port_sudan_id`) VALUES
(13, '7', '2025-07-21', NULL, NULL, 'CLM-20250721-6088', NULL, NULL, NULL, 'CMAU7702683', NULL, 1, '1', 'Mohamed Abdulla Ali Farh', 'احذية', 1, 12.00, 12.00, 24.00, 12.00, 2424.00, 'port_sudan', 'pending', NULL, 1, '2025-07-21 07:02:12', 1, '2025-07-21 07:03:59', 'synced', 1, '2025-07-21 07:02:55', 248),
(15, '64', '2025-07-24', NULL, NULL, 'CLM-20250724-4036', NULL, NULL, NULL, 'CMAU7702691', NULL, 1, '1', 'Mohamed Abdulla Ali Farh', 'طبل', 12, 12.00, 12.00, 24.00, 12.00, 2424.00, 'port_sudan', 'arrived', NULL, 1, '2025-07-24 15:12:54', 1, '2025-07-29 10:13:55', 'synced', 1, '2025-07-28 08:48:35', 250),
(16, '76', '2025-07-30', NULL, NULL, 'CLM-20250730-3450', NULL, NULL, NULL, 'CMAU7702685', NULL, 1, '1', 'Mohamed Abdulla Ali Farh', 'احذية', 12, 12.00, 12.00, 24.00, 12.00, 2424.00, 'port_sudan', 'pending', 'dgdg fgf ', 1, '2025-07-30 13:16:51', NULL, '2025-07-30 13:16:58', 'synced', 1, '2025-07-30 13:16:58', 252),
(17, '786', '2025-08-07', NULL, NULL, 'CLM-20250807-6673', NULL, NULL, NULL, 'CMAU7702685', NULL, 1, '1', 'Mohamed Abdulla Ali Farh', 'احذية', 12, 12.00, 12.00, 24.00, 12.00, 2424.00, 'port_sudan', 'pending', NULL, 1, '2025-08-07 12:56:48', NULL, '2025-08-10 17:06:33', 'synced', 1, '2025-08-10 17:06:33', NULL),
(18, '7642', '2025-08-07', NULL, NULL, 'CLM-20250807-8360', NULL, NULL, NULL, 'CMAU7702340', NULL, 1, '1', 'Mohamed Abdulla Ali Farh', 'احذية', 12, 223.00, 12.00, 235.00, 12.00, 2635.00, 'port_sudan', 'pending', NULL, 1, '2025-08-07 13:03:38', NULL, '2025-08-10 17:06:26', 'synced', 1, '2025-08-10 17:06:26', NULL),
(19, '24356', '2025-08-11', NULL, NULL, 'CLM-20250811-1532', NULL, NULL, NULL, 'CMAU7702360', NULL, 1, '1', 'Mohamed Abdulla Ali Farh', NULL, 121, 1212.00, 12.00, 1224.00, 122.00, 25624.00, 'port_sudan', 'pending', NULL, 1, '2025-08-10 17:05:55', NULL, '2025-08-10 17:06:19', 'synced', 1, '2025-08-10 17:06:19', NULL),
(20, '2554', '2025-08-12', NULL, NULL, 'CLM-20250812-5218', NULL, NULL, NULL, 'CMAU7702612', NULL, 1, '1', 'Mohamed Abdulla Ali Farh', 'احذية', 12, 12.00, 12.00, 24.00, 12.00, 2424.00, 'port_sudan', 'pending', NULL, 1, '2025-08-12 13:22:39', NULL, '2025-08-12 13:22:39', 'pending', 0, NULL, NULL),
(21, '5757', '2025-08-12', NULL, NULL, 'CLM-20250812-3308', NULL, NULL, NULL, 'CMAU7702661', NULL, 1, '1', 'Mohamed Abdulla Ali Farh', 'احذية', 12, 12.00, 12.00, 24.00, 12.00, 2424.00, 'port_sudan', 'pending', NULL, 1, '2025-08-12 13:30:48', NULL, '2025-08-12 13:30:48', 'pending', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loading_financial_records`
--

CREATE TABLE `loading_financial_records` (
  `id` int(11) NOT NULL,
  `loading_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','commission','shipping') NOT NULL,
  `amount_rmb` decimal(12,2) DEFAULT 0.00,
  `amount_usd` decimal(12,2) DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `loading_financial_records`
--

INSERT INTO `loading_financial_records` (`id`, `loading_id`, `client_id`, `transaction_type`, `amount_rmb`, `amount_usd`, `description`, `created_at`) VALUES
(4, 15, 1, 'purchase', 2424.00, 12.00, 'Automatic invoice created for loading', '2025-07-24 15:12:54'),
(5, 16, 1, 'purchase', 2424.00, 12.00, 'Automatic invoice created for loading', '2025-07-30 13:16:51'),
(6, 17, 1, 'purchase', 2424.00, 12.00, 'Automatic invoice created for loading', '2025-08-07 12:56:48'),
(7, 18, 1, 'purchase', 2635.00, 12.00, 'Automatic invoice created for loading', '2025-08-07 13:03:38'),
(8, 19, 1, 'purchase', 25624.00, 122.00, 'Automatic invoice created for loading', '2025-08-10 17:05:55'),
(9, 20, 1, 'purchase', 2424.00, 12.00, 'Automatic invoice created for loading', '2025-08-12 13:22:39'),
(10, 21, 1, 'purchase', 2424.00, 12.00, 'Automatic invoice created for loading', '2025-08-12 13:30:48');

-- --------------------------------------------------------

--
-- Table structure for table `loading_sync_log`
--

CREATE TABLE `loading_sync_log` (
  `id` int(11) NOT NULL,
  `loading_id` int(11) NOT NULL,
  `action` enum('create','update','delete','status') NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `synced_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loading_sync_log`
--

INSERT INTO `loading_sync_log` (`id`, `loading_id`, `action`, `status`, `error_message`, `request_data`, `response_data`, `synced_at`) VALUES
(3, 13, 'create', 'success', NULL, '{\"china_loading_id\":13,\"entry_date\":\"2025-07-21\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"7\",\"carton_count\":1,\"container_number\":\"CMAU7702683\",\"bill_number\":\"CLM-20250721-6088\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-20\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}', '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":248,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":{\"success\":true,\"container_id\":248,\"message\":\"Container created successfully\"},\"container_id\":248,\"message\":\"Container created successfully\"}', '2025-07-21 07:02:55'),
(4, 15, 'create', 'success', NULL, '{\"china_loading_id\":15,\"entry_date\":\"2025-07-24\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"64\",\"carton_count\":12,\"container_number\":\"CMAU7702685\",\"bill_number\":\"CLM-20250724-4036\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-23\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}', '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":250,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":{\"success\":true,\"container_id\":250,\"message\":\"Container created successfully\"},\"container_id\":250,\"message\":\"Container created successfully\"}', '2025-07-28 08:48:35'),
(5, 16, 'create', 'success', NULL, '{\"china_loading_id\":16,\"entry_date\":\"2025-07-30\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"76\",\"carton_count\":12,\"container_number\":\"CMAU7702685\",\"bill_number\":\"CLM-20250730-3450\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-29\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}', '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":252,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":{\"success\":true,\"container_id\":252,\"message\":\"Container created successfully\"},\"container_id\":252,\"message\":\"Container created successfully\"}', '2025-07-30 13:16:58'),
(6, 19, 'create', 'success', NULL, '{\"china_loading_id\":19,\"entry_date\":\"2025-08-11\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"24356\",\"carton_count\":121,\"container_number\":\"CMAU7702360\",\"bill_number\":\"CLM-20250811-1532\",\"category\":\"General Cargo\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-09-10\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}', '{\"success\":true,\"http_code\":200,\"response\":\"<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n{\\\"success\\\":true,\\\"container_id\\\":254,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":null}', '2025-08-10 17:06:19'),
(7, 18, 'create', 'success', NULL, '{\"china_loading_id\":18,\"entry_date\":\"2025-08-07\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"7642\",\"carton_count\":12,\"container_number\":\"CMAU7702340\",\"bill_number\":\"CLM-20250807-8360\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-09-06\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}', '{\"success\":true,\"http_code\":200,\"response\":\"<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n{\\\"success\\\":true,\\\"container_id\\\":255,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":null}', '2025-08-10 17:06:26'),
(8, 17, 'create', 'success', NULL, '{\"china_loading_id\":17,\"entry_date\":\"2025-08-07\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"786\",\"carton_count\":12,\"container_number\":\"CMAU7702685\",\"bill_number\":\"CLM-20250807-6673\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-09-06\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}', '{\"success\":true,\"http_code\":200,\"response\":\"<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n{\\\"success\\\":true,\\\"container_id\\\":253,\\\"message\\\":\\\"Container already exists\\\",\\\"existing\\\":true}\",\"data\":null}', '2025-08-10 17:06:33');

-- --------------------------------------------------------

--
-- Table structure for table `office_notifications`
--

CREATE TABLE `office_notifications` (
  `id` int(11) NOT NULL,
  `office` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_by` int(11) DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `office_notifications`
--

INSERT INTO `office_notifications` (`id`, `office`, `type`, `reference_id`, `reference_type`, `message`, `is_read`, `read_by`, `read_at`, `created_at`) VALUES
(1, 'port_sudan', 'new_container', 1, 'loading', 'New container CMAU7702685 assigned to your office', 0, NULL, NULL, '2025-07-17 09:22:40');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(20) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `updated_at`) VALUES
(1, 'exchange_rate_usd_rmb', '200', NULL, '2025-07-18 15:20:02'),
(2, 'exchange_rate_sdg_rmb', '4000', NULL, '2025-07-16 13:53:00'),
(3, 'exchange_rate_aed_rmb', '1.96', NULL, '2025-07-29 07:55:44'),
(4, 'company_name', 'شركة أبابيل للتنمية و الاستثمار المحدودة', NULL, '2025-07-16 14:49:23'),
(5, 'company_address', '', NULL, '2025-07-16 13:53:00'),
(6, 'company_phone', '', NULL, '2025-07-16 13:53:00'),
(13, 'banks_list', 'Bank of Khartoum,Faisal Islamic Bank,Omdurman National Bank,Blue Nile Bank,Agricultural Bank of Sudan', NULL, '2025-07-16 16:13:37'),
(20, 'port_sudan_api_url', 'https://ababel.net/app/api/china_sync.php', 'api', '2025-07-19 10:48:31'),
(21, 'port_sudan_api_key', 'AB@1234X-China2Port!', 'api', '2025-07-19 10:49:08'),
(22, 'sync_enabled', '1', 'api', '2025-07-17 12:37:04'),
(23, 'sync_retry_attempts', '3', 'api', '2025-07-17 12:37:04'),
(46, 'port_sudan_readonly', '1', 'system', '2025-07-29 07:55:44'),
(47, 'allow_admin_override', '1', 'system', '2025-07-29 07:55:44');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `loading_id` int(11) DEFAULT NULL,
  `transaction_no` varchar(50) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `transaction_type_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `invoice_no` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `loading_no` varchar(50) DEFAULT NULL,
  `goods_amount_rmb` decimal(15,2) DEFAULT 0.00,
  `commission_rmb` decimal(15,2) DEFAULT 0.00,
  `total_amount_rmb` decimal(15,2) DEFAULT 0.00,
  `payment_rmb` decimal(15,2) DEFAULT 0.00,
  `balance_rmb` decimal(15,2) DEFAULT 0.00,
  `shipping_usd` decimal(15,2) DEFAULT 0.00,
  `payment_usd` decimal(15,2) DEFAULT 0.00,
  `balance_usd` decimal(15,2) DEFAULT 0.00,
  `payment_sdg` decimal(15,2) DEFAULT 0.00,
  `payment_aed` decimal(15,2) DEFAULT 0.00,
  `balance_sdg` decimal(15,2) DEFAULT 0.00,
  `balance_aed` decimal(15,2) DEFAULT 0.00,
  `rate_usd_rmb` decimal(10,4) DEFAULT NULL,
  `rate_sdg_rmb` decimal(10,4) DEFAULT NULL,
  `rate_aed_rmb` decimal(10,4) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `loading_id`, `transaction_no`, `client_id`, `transaction_type_id`, `transaction_date`, `description`, `description_ar`, `invoice_no`, `bank_name`, `loading_no`, `goods_amount_rmb`, `commission_rmb`, `total_amount_rmb`, `payment_rmb`, `balance_rmb`, `shipping_usd`, `payment_usd`, `balance_usd`, `payment_sdg`, `payment_aed`, `balance_sdg`, `balance_aed`, `rate_usd_rmb`, `rate_sdg_rmb`, `rate_aed_rmb`, `created_by`, `approved_by`, `approved_at`, `status`, `created_at`, `updated_at`) VALUES
(12, 15, 'TRX-2025-000001', 1, 1, '2025-07-24', 'Invoice for Loading #64 - Container: CMAU7702685', 'فاتورة للتحميل رقم 64 - حاوية: CMAU7702685', 'INV-20250724-64', NULL, '64', 12.00, 12.00, 2424.00, 0.00, 2424.00, 12.00, 0.00, 12.00, 0.00, 0.00, 0.00, 0.00, 200.0000, NULL, NULL, 1, NULL, NULL, 'approved', '2025-07-24 15:12:54', '2025-07-24 15:12:54'),
(13, NULL, 'TRX-2025-000002', 1, 2, '2025-07-24', 'Payment received from Mohamed Abdulla Ali Farh (1)', NULL, NULL, '', NULL, 0.00, 0.00, 0.00, 2424.00, -2424.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 1, NULL, NULL, 'approved', '2025-07-24 15:13:26', '2025-07-24 15:13:26'),
(17, 16, 'TRX-2025-000003', 1, 1, '2025-07-30', 'Invoice for Loading #76 - Container: CMAU7702685', 'فاتورة للتحميل رقم 76 - حاوية: CMAU7702685', 'INV-20250730-76', NULL, '76', 12.00, 12.00, 2424.00, 0.00, 2424.00, 12.00, 0.00, 12.00, 0.00, 0.00, 0.00, 0.00, 200.0000, NULL, NULL, 1, NULL, NULL, 'approved', '2025-07-30 13:16:51', '2025-07-30 13:16:51'),
(18, 17, 'TRX-2025-000004', 1, 1, '2025-08-07', 'Invoice for Loading #786 - Container: CMAU7702685', 'فاتورة للتحميل رقم 786 - حاوية: CMAU7702685', 'INV-20250807-786', NULL, '786', 12.00, 12.00, 2424.00, 0.00, 2424.00, 12.00, 0.00, 12.00, 0.00, 0.00, 0.00, 0.00, 200.0000, NULL, NULL, 1, NULL, NULL, 'approved', '2025-08-07 12:56:48', '2025-08-07 12:56:48'),
(19, 18, 'TRX-2025-000005', 1, 1, '2025-08-07', 'Invoice for Loading #7642 - Container: CMAU7702340', 'فاتورة للتحميل رقم 7642 - حاوية: CMAU7702340', 'INV-20250807-7642', NULL, '7642', 223.00, 12.00, 2635.00, 0.00, 2635.00, 12.00, 0.00, 12.00, 0.00, 0.00, 0.00, 0.00, 200.0000, NULL, NULL, 1, NULL, NULL, 'approved', '2025-08-07 13:03:38', '2025-08-07 13:03:38'),
(20, 19, 'TRX-2025-000006', 1, 1, '2025-08-11', 'Invoice for Loading #24356 - Container: CMAU7702360', 'فاتورة للتحميل رقم 24356 - حاوية: CMAU7702360', 'INV-20250811-24356', NULL, '24356', 1212.00, 12.00, 25624.00, 0.00, 25624.00, 122.00, 0.00, 122.00, 0.00, 0.00, 0.00, 0.00, 200.0000, NULL, NULL, 1, NULL, NULL, 'approved', '2025-08-10 17:05:55', '2025-08-10 17:05:55'),
(21, 20, 'TRX-2025-000007', 1, 1, '2025-08-12', 'Invoice for Loading #2554 - Container: CMAU7702612', 'فاتورة للتحميل رقم 2554 - حاوية: CMAU7702612', 'INV-20250812-2554', NULL, '2554', 12.00, 12.00, 2424.00, 0.00, 2424.00, 12.00, 0.00, 12.00, 0.00, 0.00, 0.00, 0.00, 200.0000, NULL, NULL, 1, NULL, NULL, 'approved', '2025-08-12 13:22:39', '2025-08-12 13:22:39'),
(22, 21, 'TRX-2025-000008', 1, 1, '2025-08-12', 'Invoice for Loading #5757 - Container: CMAU7702661', 'فاتورة للتحميل رقم 5757 - حاوية: CMAU7702661', 'INV-20250812-5757', NULL, '5757', 12.00, 12.00, 2424.00, 0.00, 2424.00, 12.00, 0.00, 12.00, 0.00, 0.00, 0.00, 0.00, 200.0000, NULL, NULL, 1, NULL, NULL, 'approved', '2025-08-12 13:30:48', '2025-08-12 13:30:48');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_types`
--

CREATE TABLE `transaction_types` (
  `id` int(11) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `name_ar` varchar(100) DEFAULT NULL,
  `type` enum('income','expense','transfer') NOT NULL,
  `affects_cashbox` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `transaction_types`
--

INSERT INTO `transaction_types` (`id`, `code`, `name`, `name_ar`, `type`, `affects_cashbox`) VALUES
(1, 'GOODS_PURCHASE', 'Goods Purchase', 'شراء بضاعة', 'expense', 1),
(2, 'SHIPPING', 'Shipping Cost', 'تكلفة الشحن', 'expense', 1),
(3, 'PAYMENT_RECEIVED', 'Payment Received', 'دفعة مستلمة', 'income', 1),
(4, 'OFFICE_TRANSFER', 'Office Transfer', 'تحويل مكتب', 'transfer', 1),
(5, 'FACTORY_PAYMENT', 'Factory Payment', 'دفعة للمصنع', 'expense', 1),
(6, 'COMMISSION', 'Commission', 'عمولة', 'expense', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','accountant','viewer') DEFAULT 'accountant',
  `language` varchar(5) DEFAULT 'ar',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `language`, `is_active`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$crLrK32LCHVboWqNS5lEmubXH.YHsyOQoiuFJB1QtXY/qhmJeLNSa', 'admin admin', 'admin@china.ababel.net', 'admin', 'ar', 1, '2025-08-14 06:34:57', '2025-07-16 12:04:08');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `api_sync_log`
--
ALTER TABLE `api_sync_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_china_loading` (`china_loading_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_endpoint` (`endpoint`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cashbox_movements`
--
ALTER TABLE `cashbox_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_cash_date` (`movement_date`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_code` (`client_code`);

--
-- Indexes for table `loadings`
--
ALTER TABLE `loadings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `shipping_date` (`shipping_date`),
  ADD KEY `status` (`status`),
  ADD KEY `office` (`office`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_loading_no` (`loading_no`),
  ADD KEY `idx_claim_number` (`claim_number`),
  ADD KEY `idx_container_no` (`container_no`),
  ADD KEY `idx_sync_status` (`sync_status`),
  ADD KEY `idx_office_sync` (`office`,`sync_status`),
  ADD KEY `idx_loadings_office_status` (`office`,`status`),
  ADD KEY `idx_loadings_client_date` (`client_code`,`shipping_date`),
  ADD KEY `idx_bol_number` (`bol_number`),
  ADD KEY `fk_bol_issued_by` (`bol_issued_by`);

--
-- Indexes for table `loading_financial_records`
--
ALTER TABLE `loading_financial_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loading_id` (`loading_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `loading_sync_log`
--
ALTER TABLE `loading_sync_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loading_sync` (`loading_id`,`synced_at`);

--
-- Indexes for table `office_notifications`
--
ALTER TABLE `office_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `office` (`office`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `reference` (`reference_type`,`reference_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_no` (`transaction_no`),
  ADD UNIQUE KEY `unique_transaction_bank` (`transaction_no`,`bank_name`),
  ADD KEY `transaction_type_id` (`transaction_type_id`),
  ADD KEY `idx_trans_date` (`transaction_date`),
  ADD KEY `idx_trans_client` (`client_id`),
  ADD KEY `idx_loading_id` (`loading_id`);

--
-- Indexes for table `transaction_types`
--
ALTER TABLE `transaction_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `api_sync_log`
--
ALTER TABLE `api_sync_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashbox_movements`
--
ALTER TABLE `cashbox_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `loadings`
--
ALTER TABLE `loadings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `loading_financial_records`
--
ALTER TABLE `loading_financial_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `loading_sync_log`
--
ALTER TABLE `loading_sync_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `office_notifications`
--
ALTER TABLE `office_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `transaction_types`
--
ALTER TABLE `transaction_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- --------------------------------------------------------

--
-- Structure for view `cashbox_summary`
--
DROP TABLE IF EXISTS `cashbox_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`china_ababel`@`localhost` SQL SECURITY DEFINER VIEW `cashbox_summary`  AS SELECT sum(case when `cashbox_movements`.`movement_type` = 'in' then `cashbox_movements`.`amount_rmb` else -`cashbox_movements`.`amount_rmb` end) AS `balance_rmb`, sum(case when `cashbox_movements`.`movement_type` = 'in' then `cashbox_movements`.`amount_usd` else -`cashbox_movements`.`amount_usd` end) AS `balance_usd`, sum(case when `cashbox_movements`.`movement_type` = 'in' then `cashbox_movements`.`amount_sdg` else -`cashbox_movements`.`amount_sdg` end) AS `balance_sdg`, sum(case when `cashbox_movements`.`movement_type` = 'in' then `cashbox_movements`.`amount_aed` else -`cashbox_movements`.`amount_aed` end) AS `balance_aed` FROM `cashbox_movements` ;

-- --------------------------------------------------------

--
-- Structure for view `client_balances`
--
DROP TABLE IF EXISTS `client_balances`;

CREATE ALGORITHM=UNDEFINED DEFINER=`china_ababel`@`localhost` SQL SECURITY DEFINER VIEW `client_balances`  AS SELECT `c`.`id` AS `id`, `c`.`name` AS `name`, `c`.`client_code` AS `client_code`, coalesce(sum(`t`.`balance_rmb`),0) AS `total_balance_rmb`, coalesce(sum(`t`.`balance_usd`),0) AS `total_balance_usd`, count(`t`.`id`) AS `transaction_count` FROM (`clients` `c` left join `transactions` `t` on(`c`.`id` = `t`.`client_id` and `t`.`status` = 'approved')) GROUP BY `c`.`id` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `cashbox_movements`
--
ALTER TABLE `cashbox_movements`
  ADD CONSTRAINT `cashbox_movements_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`);

--
-- Constraints for table `loadings`
--
ALTER TABLE `loadings`
  ADD CONSTRAINT `fk_bol_issued_by` FOREIGN KEY (`bol_issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loadings_client_fk` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loadings_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loading_sync_log`
--
ALTER TABLE `loading_sync_log`
  ADD CONSTRAINT `loading_sync_log_ibfk_1` FOREIGN KEY (`loading_id`) REFERENCES `loadings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_types` (`id`),
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`loading_id`) REFERENCES `loadings` (`id`) ON DELETE SET NULL;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`china_ababel`@`localhost` EVENT `retry_sync_event` ON SCHEDULE EVERY 1 HOUR STARTS '2025-07-17 15:46:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL retry_failed_syncs()$$

CREATE DEFINER=`china_ababel`@`localhost` EVENT `clean_logs_event` ON SCHEDULE EVERY 1 WEEK STARTS '2025-07-17 15:46:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL clean_old_sync_logs()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
