-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Aug 14, 2025 at 04:41 PM
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
-- Database: `khan`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`khan`@`localhost` PROCEDURE `clean_old_sync_logs` ()   BEGIN
    -- Delete sync logs older than 90 days
    DELETE FROM loading_sync_log 
    WHERE synced_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Delete API logs older than 30 days (Port Sudan)
    DELETE FROM api_sync_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END$$

CREATE DEFINER=`khan`@`localhost` PROCEDURE `process_partial_payment` (IN `p_transaction_id` INT, IN `p_payment_currency` VARCHAR(3), IN `p_payment_amount` DECIMAL(15,2), IN `p_bank_name` VARCHAR(100), IN `p_user_id` INT)   BEGIN
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

CREATE DEFINER=`khan`@`localhost` PROCEDURE `retry_failed_syncs` ()   BEGIN
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

CREATE DEFINER=`khan`@`localhost` PROCEDURE `sp_update_client_analytics` ()   BEGIN
                DECLARE done INT DEFAULT FALSE;
                DECLARE v_client_id INT;
                DECLARE v_month_year VARCHAR(7);
                
                DECLARE cur CURSOR FOR 
                    SELECT DISTINCT client_id, DATE_FORMAT(transaction_date, '%Y-%m') as month_year
                    FROM transactions 
                    WHERE transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
                    AND client_id IS NOT NULL;
                
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
                
                OPEN cur;
                
                read_loop: LOOP
                    FETCH cur INTO v_client_id, v_month_year;
                    IF done THEN
                        LEAVE read_loop;
                    END IF;
                    
                    INSERT INTO client_analytics (client_id, month_year, transaction_count, total_volume_rmb, average_transaction_rmb)
                    SELECT 
                        v_client_id,
                        v_month_year,
                        COUNT(*),
                        SUM(total_amount_rmb),
                        AVG(total_amount_rmb)
                    FROM transactions 
                    WHERE client_id = v_client_id 
                    AND DATE_FORMAT(transaction_date, '%Y-%m') = v_month_year
                    ON DUPLICATE KEY UPDATE 
                        transaction_count = VALUES(transaction_count),
                        total_volume_rmb = VALUES(total_volume_rmb),
                        average_transaction_rmb = VALUES(average_transaction_rmb),
                        last_updated = CURRENT_TIMESTAMP;
                        
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
  `request_data` longtext DEFAULT 'NULL',
  `response_code` int(11) DEFAULT NULL,
  `response_data` longtext DEFAULT 'NULL',
  `ip_address` varchar(45) DEFAULT 'NULL',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `api_sync_log`
--

INSERT INTO `api_sync_log` (`id`, `endpoint`, `method`, `china_loading_id`, `container_id`, `request_data`, `response_code`, `response_data`, `ip_address`, `created_at`) VALUES
(1, 'https://ababel.net/app/api/china_sync.php', 'POST', 11, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":11,\"entry_date\":\"2025-07-19\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"80\",\"carton_count\":12,\"container_number\":\"CMAU7702683\",\"bill_number\":\"CLM-20250719-6442\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-18\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":242,\\\"message\\\":\\\"Container already exists\\\",\\\"existing\\\":true}\",\"data\":{\"success\":true,\"container_id\":242,\"message\":\"Container already exists\",\"existing\":true},\"container_id\":242,\"message\":\"Container already exists\",\"existing\":true}', '172.71.103.169', '2025-07-19 10:55:30'),
(2, 'https://ababel.net/app/api/china_sync.php', 'POST', 10, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":10,\"entry_date\":\"2025-07-19\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"7\",\"carton_count\":400,\"container_number\":\"CMAU7702683\",\"bill_number\":\"CLM-20250719-5499\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-18\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":243,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":{\"success\":true,\"container_id\":243,\"message\":\"Container created successfully\"},\"container_id\":243,\"message\":\"Container created successfully\"}', '172.71.103.169', '2025-07-19 10:57:06'),
(3, 'https://ababel.net/app/api/china_sync.php', 'POST', 13, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":13,\"entry_date\":\"2025-07-21\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"7\",\"carton_count\":1,\"container_number\":\"CMAU7702683\",\"bill_number\":\"CLM-20250721-6088\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-20\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":248,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":{\"success\":true,\"container_id\":248,\"message\":\"Container created successfully\"},\"container_id\":248,\"message\":\"Container created successfully\"}', '104.23.168.113', '2025-07-21 07:02:55'),
(4, 'https://ababel.net/app/api/china_sync.php', 'POST', 15, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":15,\"entry_date\":\"2025-07-24\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"64\",\"carton_count\":12,\"container_number\":\"CMAU7702685\",\"bill_number\":\"CLM-20250724-4036\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-08-23\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"{\\\"success\\\":true,\\\"container_id\\\":250,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":{\"success\":true,\"container_id\":250,\"message\":\"Container created successfully\"},\"container_id\":250,\"message\":\"Container created successfully\"}', '172.71.103.173', '2025-07-28 08:48:35'),
(5, 'https://ababel.net/app/api/china_sync.php', 'POST', 17, NULL, '{\"action\":\"create_container\",\"data\":{\"china_loading_id\":17,\"entry_date\":\"2025-08-07\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"786\",\"carton_count\":12,\"container_number\":\"CMAU7702685\",\"bill_number\":\"CLM-20250807-6673\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-09-06\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}}', 200, '{\"success\":true,\"http_code\":200,\"response\":\"<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n<br \\/>\\n<b>Warning<\\/b>:  file_put_contents(\\/www\\/wwwroot\\/ababel\\/app\\/api\\/sync_debug.log): Failed to open stream: Permission denied in <b>\\/www\\/wwwroot\\/ababel\\/app\\/api\\/china_sync.php<\\/b> on line <b>16<\\/b><br \\/>\\n{\\\"success\\\":true,\\\"container_id\\\":253,\\\"message\\\":\\\"Container created successfully\\\"}\",\"data\":null}', '104.23.166.114', '2025-08-07 12:59:03'),
(6, '', 'POST', 27, NULL, '{\"china_loading_id\":\"27\",\"entry_date\":\"2025-08-12\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"2424\",\"carton_count\":12,\"container_number\":\"CMAU7702612\",\"bill_number\":\"CLM-20250812-7829\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-09-11\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}', 400, '{\"success\":false,\"error\":\"Invalid endpoint: \\/app\\/api\\/china_sync.php\",\"request_id\":\"req_689b3fde27c7f0.41620593\",\"timestamp\":\"2025-08-12T21:21:34+08:00\",\"performance\":{\"processing_time_ms\":0.19,\"memory_usage_mb\":0.82}}', '162.158.19.147', '2025-08-12 13:21:34'),
(7, '', 'POST', 27, NULL, '{\"china_loading_id\":\"27\",\"entry_date\":\"2025-08-12\",\"code\":\"1\",\"client_name\":\"Mohamed Abdulla Ali Farh\",\"loading_number\":\"2424\",\"carton_count\":12,\"container_number\":\"CMAU7702612\",\"bill_number\":\"CLM-20250812-7829\",\"category\":\"\\u0627\\u062d\\u0630\\u064a\\u0629\",\"carrier\":\"TBD\",\"expected_arrival\":\"2025-09-11\",\"ship_name\":\"TBD\",\"custom_station\":\"Port Sudan\",\"office\":\"\\u0628\\u0648\\u0631\\u062a\\u0633\\u0648\\u062f\\u0627\\u0646\"}', 0, '{\"error\":\"API Error: Invalid endpoint: \\/app\\/api\\/china_sync.php\"}', '162.158.19.147', '2025-08-12 13:21:34');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) DEFAULT 'NULL',
  `table_name` varchar(50) DEFAULT 'NULL',
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext DEFAULT 'NULL',
  `new_values` longtext DEFAULT 'NULL',
  `ip_address` varchar(45) DEFAULT 'NULL',
  `user_agent` varchar(255) DEFAULT 'NULL',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'issue_bol', 'loadings', 15, NULL, '{\"bol_number\":\"BOL-20250730-0015\"}', NULL, NULL, '2025-07-30 13:44:51'),
(2, 1, 'issue_bol', 'loadings', 17, NULL, '{\"bol_number\":\"BOL-20250731-0017\"}', NULL, NULL, '2025-07-31 06:50:23'),
(3, 4, 'transaction_create', 'transactions', 999, NULL, '{\"description\":\"تجربة تسجيل إضافة معاملة من المستخدم Khan\"}', '192.168.1.100', 'Test Browser', '2025-08-11 08:50:17'),
(4, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:05'),
(5, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:28'),
(6, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:30'),
(7, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:30'),
(8, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:31'),
(9, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:35'),
(10, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:36'),
(11, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:37'),
(12, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:38'),
(13, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:38'),
(14, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:39'),
(15, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:39'),
(16, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:40'),
(17, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:40'),
(18, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:40'),
(19, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:41'),
(20, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:41'),
(21, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:41'),
(22, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:42'),
(23, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:43'),
(24, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:43'),
(25, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:43'),
(26, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:43'),
(27, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:51:44'),
(28, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.32', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:57:25'),
(29, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.82', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:59:44'),
(30, 4, 'cashbox_movement', 'cashbox', 21, NULL, '{\"description\":\"حركة خزنة: سحب - 12 RMB, 12 USD, 12 SDG, 12 AED - \"}', '162.158.22.74', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-11 09:00:24'),
(31, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:00:27'),
(32, 1, 'logout', 'users', 1, NULL, '{\"description\":\"تسجيل خروج: Updated Admin\"}', '162.158.23.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:00:55'),
(33, 1, 'login', 'users', 1, NULL, '{\"description\":\"تسجيل دخول: admin\"}', '162.158.23.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:01:10'),
(34, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.23.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:01:24'),
(35, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.70.108.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:02:43'),
(36, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.70.108.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:02:46'),
(37, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.70.108.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:03:06'),
(38, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.70.108.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:03:31'),
(39, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.70.108.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:03:37'),
(40, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:46:15'),
(41, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.158', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:46:31'),
(42, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.158', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:46:49'),
(43, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.158', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:46:56'),
(44, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.158', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:46:57'),
(45, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.158', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:46:58'),
(46, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.158', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:46:58'),
(47, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.158', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:47:02'),
(48, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.82', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:51:51'),
(49, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.82', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:51:56'),
(50, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.82', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:51:56'),
(51, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '172.68.234.82', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 09:52:00'),
(52, 1, 'activity_viewed', 'users', 4, NULL, '{\"description\":\"عرض سجل النشاطات للمستخدم: Khan\"}', '162.158.22.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 10:00:05'),
(53, 1, 'login', 'users', 1, NULL, '{\"description\":\"تسجيل دخول: admin\"}', '162.158.19.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-12 13:21:03'),
(54, 1, 'loading_create', 'loadings', 27, NULL, '{\"description\":\"إضافة تحميل جديد: 2424 - Mohamed Abdulla Ali Farh\"}', '162.158.19.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-12 13:21:34'),
(55, 1, 'login', 'users', 1, NULL, '{\"description\":\"تسجيل دخول: admin\"}', '172.71.103.144', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-13 15:39:00'),
(56, 1, 'login', 'users', 1, NULL, '{\"description\":\"تسجيل دخول: admin\"}', '172.71.103.144', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-13 15:39:14'),
(57, 1, 'login', 'users', 1, NULL, '{\"description\":\"تسجيل دخول: admin\"}', '172.71.103.144', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-13 15:39:27'),
(58, 1, 'logout', 'users', 1, 'NULL', '{\"description\":\"تسجيل خروج: Updated Admin\"}', '172.71.182.84', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-13 17:15:58'),
(59, 1, 'login', 'users', 1, 'NULL', '{\"description\":\"تسجيل دخول: admin\"}', '172.71.182.84', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-13 17:16:09'),
(60, 1, 'logout', 'users', 1, 'NULL', '{\"description\":\"تسجيل خروج: Updated Admin\"}', '172.71.182.84', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-13 17:16:14'),
(61, 1, 'login', 'users', 1, 'NULL', '{\"description\":\"تسجيل دخول: admin\"}', '172.71.182.84', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-13 17:16:26'),
(62, 1, 'login', 'users', 1, 'NULL', '{\"description\":\"تسجيل دخول: admin\"}', '172.70.108.197', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 06:55:04'),
(63, 1, 'login', 'users', 1, 'NULL', '{\"description\":\"تسجيل دخول: admin\"}', '172.70.223.182', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 07:07:05');

-- --------------------------------------------------------

--
-- Table structure for table `cashbox_currency_conversions`
--

CREATE TABLE `cashbox_currency_conversions` (
  `id` int(11) NOT NULL,
  `from_currency` varchar(5) NOT NULL,
  `to_currency` varchar(5) NOT NULL,
  `original_amount` decimal(15,2) NOT NULL,
  `converted_amount` decimal(15,2) NOT NULL,
  `exchange_rate` decimal(15,6) NOT NULL,
  `debit_movement_id` int(11) NOT NULL,
  `credit_movement_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `converted_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cashbox_movements`
--

CREATE TABLE `cashbox_movements` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `movement_date` date NOT NULL,
  `movement_type` enum('in','out','transfer') NOT NULL,
  `category` varchar(50) DEFAULT 'NULL',
  `amount_rmb` decimal(15,2) DEFAULT 0.00,
  `amount_usd` decimal(15,2) DEFAULT 0.00,
  `amount_sdg` decimal(15,2) DEFAULT 0.00,
  `amount_aed` decimal(15,2) DEFAULT 0.00,
  `bank_name` varchar(100) DEFAULT 'NULL',
  `tt_number` varchar(50) DEFAULT 'NULL',
  `receipt_no` varchar(50) DEFAULT 'NULL',
  `description` mediumtext DEFAULT 'NULL',
  `balance_after_rmb` decimal(15,2) DEFAULT NULL,
  `balance_after_usd` decimal(15,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `client_code` varchar(50) DEFAULT 'NULL',
  `name` varchar(255) DEFAULT 'NULL',
  `name_ar` varchar(255) DEFAULT 'NULL',
  `phone` varchar(100) DEFAULT 'NULL',
  `email` varchar(255) DEFAULT 'NULL',
  `address` mediumtext DEFAULT 'NULL',
  `balance_rmb` decimal(15,2) DEFAULT 0.00,
  `balance_usd` decimal(15,2) DEFAULT 0.00,
  `balance_sdg` decimal(15,2) DEFAULT 0.00,
  `balance_aed` decimal(15,2) DEFAULT 0.00,
  `credit_limit` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `client_code`, `name`, `name_ar`, `phone`, `email`, `address`, `balance_rmb`, `balance_usd`, `balance_sdg`, `balance_aed`, `credit_limit`, `status`, `created_at`, `updated_at`) VALUES
(1, '1', 'Mohamed Abdulla Ali Farh', 'محمد عبدالله علي', '0910564187', 'hmadakhan686@gmail.com', 'Portsudan', 8080.00, 35.00, 0.05, 0.00, 0.00, 'active', '2025-07-16 13:58:13', '2025-08-12 13:21:34'),
(2, 'LTP001', 'Lite Tech Proposals', 'شركة لايت تك للمقترحات', '+1-555-0123', 'contact@litetechproposals.com', '123 Tech Street, Innovation City', 0.00, 0.00, 0.00, 0.00, 50000.00, 'active', '2025-08-13 16:29:32', '2025-08-13 16:29:32');

-- --------------------------------------------------------

--
-- Table structure for table `client_analytics`
--

CREATE TABLE `client_analytics` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `month_year` varchar(7) NOT NULL,
  `transaction_count` int(11) DEFAULT 0,
  `total_volume_rmb` decimal(15,2) DEFAULT 0.00,
  `average_transaction_rmb` decimal(15,2) DEFAULT 0.00,
  `payment_score` int(11) DEFAULT 100,
  `risk_level` enum('LOW','MEDIUM','HIGH') DEFAULT 'LOW',
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `currency_conversions`
--

CREATE TABLE `currency_conversions` (
  `id` int(11) NOT NULL,
  `original_amount` decimal(15,2) NOT NULL,
  `from_currency` varchar(5) NOT NULL,
  `converted_amount` decimal(15,2) NOT NULL,
  `to_currency` varchar(5) NOT NULL,
  `exchange_rate` decimal(15,6) NOT NULL,
  `conversion_time` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `currency_conversions`
--

INSERT INTO `currency_conversions` (`id`, `original_amount`, `from_currency`, `converted_amount`, `to_currency`, `exchange_rate`, `conversion_time`, `created_at`) VALUES
(1, 100.00, 'USD', 724.00, 'RMB', 7.240000, '2025-08-14 15:24:02', '2025-08-14 15:24:02'),
(2, 12.00, 'RMB', 1.66, 'USD', 0.138000, '2025-08-14 15:25:08', '2025-08-14 15:25:08');

-- --------------------------------------------------------

--
-- Table structure for table `exchange_rates`
--

CREATE TABLE `exchange_rates` (
  `id` int(11) NOT NULL,
  `currency_pair` varchar(10) NOT NULL,
  `rate` decimal(10,6) NOT NULL,
  `source` varchar(50) DEFAULT 'manual',
  `last_updated` datetime NOT NULL,
  `effective_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exchange_rates`
--

INSERT INTO `exchange_rates` (`id`, `currency_pair`, `rate`, `source`, `last_updated`, `effective_date`, `created_at`) VALUES
(25, 'USD_RMB', 7.240000, 'manual', '2025-08-14 15:10:47', '2025-08-14', '2025-08-14 07:10:47'),
(26, 'RMB_USD', 0.138000, 'manual', '2025-08-14 15:10:47', '2025-08-14', '2025-08-14 07:10:47'),
(27, 'USD_SDG', 601.500000, 'manual', '2025-08-14 15:10:47', '2025-08-14', '2025-08-14 07:10:47'),
(28, 'SDG_USD', 0.001660, 'manual', '2025-08-14 15:10:47', '2025-08-14', '2025-08-14 07:10:47'),
(29, 'USD_AED', 3.673000, 'manual', '2025-08-14 15:10:47', '2025-08-14', '2025-08-14 07:10:47'),
(30, 'AED_USD', 0.272000, 'manual', '2025-08-14 15:10:47', '2025-08-14', '2025-08-14 07:10:47'),
(31, 'RMB_SDG', 83.500000, 'manual', '2025-08-14 15:19:36', '2025-08-14', '2025-08-14 07:19:09'),
(35, 'SDG_AED', 0.006100, 'manual', '2025-08-14 15:23:44', '2025-08-14', '2025-08-14 07:23:20');

-- --------------------------------------------------------

--
-- Table structure for table `exchange_rate_history`
--

CREATE TABLE `exchange_rate_history` (
  `id` int(11) NOT NULL,
  `currency_pair` varchar(10) NOT NULL,
  `rate` decimal(15,6) NOT NULL,
  `source` varchar(50) DEFAULT 'manual',
  `recorded_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exchange_rate_history`
--

INSERT INTO `exchange_rate_history` (`id`, `currency_pair`, `rate`, `source`, `recorded_at`) VALUES
(33, 'USD_RMB', 7.240000, 'manual', '2025-08-14 15:10:47'),
(34, 'RMB_USD', 0.138000, 'manual', '2025-08-14 15:10:47'),
(35, 'USD_SDG', 601.500000, 'manual', '2025-08-14 15:10:47'),
(36, 'SDG_USD', 0.001660, 'manual', '2025-08-14 15:10:47'),
(37, 'USD_AED', 3.673000, 'manual', '2025-08-14 15:10:47'),
(38, 'AED_USD', 0.272000, 'manual', '2025-08-14 15:10:47'),
(39, 'RMB_SDG', 83.500000, 'manual', '2025-08-14 15:19:09'),
(40, 'RMB_SDG', 83.500000, 'manual', '2025-08-14 15:19:15'),
(41, 'RMB_SDG', 83.500000, 'manual', '2025-08-14 15:19:36'),
(42, 'TEST_PAIR', 1.500000, 'manual', '2025-08-14 15:21:31'),
(43, 'SDG_AED', 0.006100, 'manual', '2025-08-14 15:23:20'),
(44, 'SDG_AED', 0.006100, 'manual', '2025-08-14 15:23:44'),
(45, 'TEST_PAIR', 5.550000, 'manual', '2025-08-14 15:24:02'),
(46, 'TEST_PAIR', 0.000000, 'delete', '2025-08-14 15:24:02');

-- --------------------------------------------------------

--
-- Table structure for table `financial_audit_log`
--

CREATE TABLE `financial_audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `claim_number` varchar(50) DEFAULT 'NULL',
  `bol_number` varchar(50) DEFAULT 'NULL',
  `bol_issued_date` date DEFAULT NULL,
  `bol_issued_by` int(11) DEFAULT NULL,
  `container_no` varchar(50) NOT NULL,
  `bl_number` varchar(50) DEFAULT 'NULL',
  `client_id` int(11) DEFAULT NULL,
  `client_code` varchar(20) DEFAULT 'NULL',
  `client_name` varchar(255) DEFAULT 'NULL',
  `item_description` text DEFAULT 'NULL',
  `cartons_count` int(11) DEFAULT 0,
  `purchase_amount` decimal(15,2) DEFAULT 0.00,
  `commission_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_usd` decimal(15,2) DEFAULT 0.00,
  `total_with_shipping` decimal(15,2) DEFAULT 0.00,
  `office` enum('port_sudan','uae','tanzania','egypt') DEFAULT NULL,
  `status` enum('pending','shipped','arrived','cleared','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT 'NULL',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sync_status` enum('pending','synced','failed') DEFAULT 'pending',
  `sync_attempts` int(11) DEFAULT 0,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `port_sudan_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loadings`
--

INSERT INTO `loadings` (`id`, `loading_no`, `shipping_date`, `actual_shipping_date`, `arrival_date`, `claim_number`, `bol_number`, `bol_issued_date`, `bol_issued_by`, `container_no`, `bl_number`, `client_id`, `client_code`, `client_name`, `item_description`, `cartons_count`, `purchase_amount`, `commission_amount`, `total_amount`, `shipping_usd`, `total_with_shipping`, `office`, `status`, `notes`, `created_by`, `created_at`, `updated_by`, `updated_at`, `sync_status`, `sync_attempts`, `last_sync_at`, `port_sudan_id`) VALUES
(25, '76', '2025-08-07', NULL, NULL, 'CLM-20250807-7085', NULL, NULL, NULL, 'CMAU7702691', NULL, 1, '1', 'Mohamed Abdulla Ali Farh', 'احذية', 12, 12.00, 12.00, 24.00, 12.00, 2424.00, 'port_sudan', 'pending', NULL, 1, '2025-08-07 12:38:49', NULL, '2025-08-07 12:38:49', 'pending', 0, NULL, NULL),
(26, '6753', '2025-08-07', NULL, NULL, 'CLM-20250807-1934', NULL, NULL, NULL, 'CMAU7702343', NULL, 1, '1', 'Mohamed Abdulla Ali Farh', 'احذية', 34, 34.00, 34.00, 68.00, 34.00, 6868.00, 'port_sudan', 'pending', NULL, 1, '2025-08-07 13:01:13', NULL, '2025-08-07 13:01:13', 'pending', 0, NULL, NULL),
(27, '2424', '2025-08-12', NULL, NULL, 'CLM-20250812-7829', NULL, NULL, NULL, 'CMAU7702612', NULL, 1, '1', 'Mohamed Abdulla Ali Farh', 'احذية', 12, 12.00, 12.00, 24.00, 12.00, 2424.00, 'port_sudan', 'pending', NULL, 1, '2025-08-12 13:21:34', NULL, '2025-08-12 13:21:34', 'pending', 0, NULL, NULL);

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
  `description` varchar(255) DEFAULT 'NULL',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loading_financial_records`
--

INSERT INTO `loading_financial_records` (`id`, `loading_id`, `client_id`, `transaction_type`, `amount_rmb`, `amount_usd`, `description`, `created_at`) VALUES
(6, 17, 1, 'purchase', 2424.00, 12.00, 'Automatic invoice created for loading', '2025-07-31 06:49:59'),
(7, 18, 1, 'purchase', 2424.00, 12.00, 'Automatic claim/invoice created for loading', '2025-07-31 07:14:08'),
(8, 19, 1, 'purchase', 24844.00, 123.00, 'Automatic claim/invoice created for loading', '2025-07-31 10:53:59'),
(9, 20, 1, 'purchase', 2424.00, 12.00, 'Automatic claim/invoice created for loading', '2025-08-07 10:52:23'),
(10, 21, 1, 'purchase', 2424.00, 12.00, 'Automatic claim/invoice created for loading', '2025-08-07 12:13:03'),
(11, 22, 1, 'purchase', 2424.00, 12.00, 'Automatic claim/invoice created for loading', '2025-08-07 12:20:31'),
(12, 23, 1, 'purchase', 2424.00, 12.00, 'Automatic claim/invoice created for loading', '2025-08-07 12:25:38'),
(13, 24, 1, 'purchase', 2412.00, 12.00, 'Automatic claim/invoice created for loading', '2025-08-07 12:31:24'),
(14, 25, 1, 'purchase', 2424.00, 12.00, 'Automatic claim/invoice created for loading', '2025-08-07 12:38:49'),
(15, 26, 1, 'purchase', 6868.00, 34.00, 'Automatic claim/invoice created for loading', '2025-08-07 13:01:13'),
(16, 27, 1, 'purchase', 2424.00, 12.00, 'Automatic claim/invoice created for loading', '2025-08-12 13:21:34');

-- --------------------------------------------------------

--
-- Table structure for table `loading_sync_log`
--

CREATE TABLE `loading_sync_log` (
  `id` int(11) NOT NULL,
  `loading_id` int(11) NOT NULL,
  `action` enum('create','update','delete','status') NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `error_message` text DEFAULT 'NULL',
  `request_data` longtext DEFAULT 'NULL',
  `response_data` longtext DEFAULT 'NULL',
  `synced_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `attempt_time` timestamp NULL DEFAULT current_timestamp(),
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `office_notifications`
--

CREATE TABLE `office_notifications` (
  `id` int(11) NOT NULL,
  `office` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT 'NULL',
  `message` text DEFAULT 'NULL',
  `is_read` tinyint(1) DEFAULT 0,
  `read_by` int(11) DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `office_notifications`
--

INSERT INTO `office_notifications` (`id`, `office`, `type`, `reference_id`, `reference_type`, `message`, `is_read`, `read_by`, `read_at`, `created_at`) VALUES
(1, 'port_sudan', 'new_container', 1, 'loading', 'New container CMAU7702685 assigned to your office', 0, NULL, NULL, '2025-07-17 09:22:40');

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `action` varchar(100) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `first_attempt_at` timestamp NULL DEFAULT current_timestamp(),
  `last_attempt_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `blocked_until` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_audit`
--

CREATE TABLE `security_audit` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_affected` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `description` text DEFAULT 'NULL',
  `ip_address` varchar(45) DEFAULT 'NULL',
  `user_agent` text DEFAULT 'NULL',
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) DEFAULT 'NULL',
  `setting_value` mediumtext DEFAULT 'NULL',
  `setting_type` varchar(20) DEFAULT 'NULL',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `system_metrics`
--

CREATE TABLE `system_metrics` (
  `id` int(11) NOT NULL,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` text DEFAULT NULL,
  `recorded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_metrics`
--

INSERT INTO `system_metrics` (`id`, `metric_name`, `metric_value`, `recorded_at`) VALUES
(1, 'system_version', '2.1.0', '2025-08-13 16:31:26'),
(2, 'last_optimization', '2025-08-14 00:31:26', '2025-08-13 16:31:26'),
(3, 'performance_status', 'optimized', '2025-08-13 16:31:26'),
(4, 'security_level', 'enhanced', '2025-08-13 16:31:26');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `parent_transaction_id` int(11) DEFAULT NULL,
  `loading_id` int(11) DEFAULT NULL,
  `transaction_no` varchar(50) DEFAULT 'NULL',
  `client_id` int(11) DEFAULT NULL,
  `transaction_type_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `description` mediumtext DEFAULT 'NULL',
  `description_ar` mediumtext DEFAULT 'NULL',
  `invoice_no` varchar(50) DEFAULT 'NULL',
  `bank_name` varchar(100) DEFAULT 'NULL',
  `loading_no` varchar(50) DEFAULT 'NULL',
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `parent_transaction_id`, `loading_id`, `transaction_no`, `client_id`, `transaction_type_id`, `transaction_date`, `description`, `description_ar`, `invoice_no`, `bank_name`, `loading_no`, `goods_amount_rmb`, `commission_rmb`, `total_amount_rmb`, `payment_rmb`, `balance_rmb`, `shipping_usd`, `payment_usd`, `balance_usd`, `payment_sdg`, `payment_aed`, `balance_sdg`, `balance_aed`, `rate_usd_rmb`, `rate_sdg_rmb`, `rate_aed_rmb`, `created_by`, `approved_by`, `approved_at`, `status`, `created_at`, `updated_at`, `deleted_at`, `deleted_by`) VALUES
(26, NULL, 25, 'TRX-2025-000001', 1, 1, '2025-08-07', 'Invoice for Loading #76 - Container: CMAU7702691', 'فاتورة للتحميل رقم 76 - حاوية: CMAU7702691', 'INV-20250807-76', NULL, '76', 12.00, 12.00, 2424.00, 2424.00, 0.00, 12.00, 12.00, 0.00, 0.00, 0.00, 0.00, 0.00, 200.0000, NULL, NULL, 1, 1, '2025-08-07 20:39:05', 'approved', '2025-08-07 12:38:49', '2025-08-07 17:42:54', NULL, NULL),
(27, NULL, NULL, 'TRX-2025-000002', 1, 2, '2025-08-07', 'Payment of 1 USD from Mohamed Abdulla Ali Farh (1)', NULL, NULL, 'bank khartoum', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 12.00, -12.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 1, NULL, NULL, 'approved', '2025-08-07 12:45:42', '2025-08-07 12:45:42', NULL, NULL),
(28, NULL, NULL, 'TRX-2025-000003', 1, 2, '2025-08-07', 'Payment of 2 RMB from Mohamed Abdulla Ali Farh (1)', NULL, NULL, '', NULL, 0.00, 0.00, 0.00, 2424.00, -2424.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 1, NULL, NULL, 'approved', '2025-08-07 12:46:36', '2025-08-07 12:46:36', NULL, NULL),
(29, NULL, 26, 'TRX-2025-000004', 1, 1, '2025-08-07', 'Invoice for Loading #6753 - Container: CMAU7702343', 'فاتورة للتحميل رقم 6753 - حاوية: CMAU7702343', 'INV-20250807-6753', NULL, '6753', 34.00, 34.00, 6868.00, 6868.00, 0.00, 34.00, 34.00, 0.00, 0.00, 0.00, 0.00, 0.00, 200.0000, NULL, NULL, 1, 1, '2025-08-07 21:10:21', 'approved', '2025-08-07 13:01:13', '2025-08-07 13:10:46', NULL, NULL),
(30, 29, 26, 'PAY-20250807-211046', 1, 3, '2025-08-07', 'Payment for invoice #TRX-2025-000004', NULL, NULL, 'Bank of Khartoum', NULL, 0.00, 0.00, 0.00, 6868.00, 0.00, 0.00, 34.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 1, 1, '2025-08-07 21:10:46', 'approved', '2025-08-07 13:10:46', '2025-08-07 13:10:46', NULL, NULL),
(31, 26, 25, 'PAY-20250808-014208', 1, 3, '2025-08-08', 'Payment for invoice #TRX-2025-000001', NULL, NULL, 'Bank of Khartoum', NULL, 0.00, 0.00, 0.00, 2424.00, 0.00, 0.00, 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 1, 1, '2025-08-08 01:42:08', 'approved', '2025-08-07 17:42:08', '2025-08-07 17:42:08', NULL, NULL),
(32, 26, 25, 'PAY-20250808-014254', 1, 3, '2025-08-08', 'Payment for invoice #TRX-2025-000001', NULL, NULL, 'Bank of Khartoum', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 1, 1, '2025-08-08 01:42:54', 'approved', '2025-08-07 17:42:54', '2025-08-07 17:42:54', NULL, NULL),
(33, NULL, NULL, 'TRX-2025-000026', 1, 6, '2025-08-11', 'ggf', NULL, '45', 'Bank of Khartoum', '80', 1212.00, 12.00, 1224.00, 12.00, 1212.00, 12.00, 1.00, 11.00, 0.05, 0.00, 0.00, 0.00, NULL, NULL, NULL, 4, 4, '2025-08-11 15:36:26', 'approved', '2025-08-11 07:36:05', '2025-08-11 07:36:26', NULL, NULL),
(34, NULL, 27, 'TRX-2025-000027', 1, 1, '2025-08-12', 'Invoice for Loading #2424 - Container: CMAU7702612', 'فاتورة للتحميل رقم 2424 - حاوية: CMAU7702612', 'INV-20250812-2424', NULL, '2424', 12.00, 12.00, 2424.00, 0.00, 2424.00, 12.00, 0.00, 12.00, 0.00, 0.00, 0.00, 0.00, 200.0000, NULL, NULL, 1, NULL, NULL, 'pending', '2025-08-12 13:21:34', '2025-08-12 13:21:34', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `transaction_types`
--

CREATE TABLE `transaction_types` (
  `id` int(11) NOT NULL,
  `code` varchar(20) DEFAULT 'NULL',
  `name` varchar(100) NOT NULL,
  `name_ar` varchar(100) DEFAULT 'NULL',
  `type` enum('income','expense','transfer') NOT NULL,
  `affects_cashbox` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `name` varchar(100) DEFAULT 'NULL',
  `full_name` varchar(100) DEFAULT 'NULL',
  `email` varchar(100) NOT NULL,
  `role` enum('admin','accountant','manager','user') DEFAULT 'user',
  `status` enum('active','inactive','deleted') DEFAULT 'active',
  `language` varchar(5) DEFAULT 'ar',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `permissions` longtext DEFAULT NULL CHECK (json_valid(`permissions`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `full_name`, `email`, `role`, `status`, `language`, `is_active`, `last_login`, `created_by`, `created_at`, `updated_at`, `permissions`) VALUES
(1, 'admin', '$2y$10$crLrK32LCHVboWqNS5lEmubXH.YHsyOQoiuFJB1QtXY/qhmJeLNSa', 'Updated Admin', 'Updated Admin', 'admin@test-update.com', 'admin', 'active', 'ar', 1, '2025-08-14 07:07:05', NULL, '2025-07-16 12:04:08', '2025-08-14 07:07:05', NULL),
(3, 'testuser', 'y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User', 'Test User', 'test@example.com', 'user', 'deleted', 'ar', 1, NULL, NULL, '2025-08-10 18:08:48', '2025-08-10 18:14:02', NULL),
(4, 'Khan', '$2y$10$Z3c49dJ0.8bIANGm99qERet72H9fxsvWhXFID9NvIIoJp8ZJi.7aC', 'Mohamed Abdulla Ali Farh', 'Mohamed Abdulla Ali Farh', 'hmadakhan686@gmail.com', 'accountant', 'active', 'ar', 1, '2025-08-11 08:35:00', 1, '2025-08-10 18:10:43', '2025-08-11 08:35:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('admin','lab_employee') NOT NULL,
  `session_token` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT 'NULL',
  `user_agent` text DEFAULT 'NULL',
  `created_at` datetime DEFAULT current_timestamp(),
  `last_activity` datetime DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_client_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_client_summary` (
`id` int(11)
,`client_code` varchar(50)
,`name` varchar(255)
,`name_ar` varchar(255)
,`status` enum('active','inactive')
,`balance_rmb` decimal(15,2)
,`balance_usd` decimal(15,2)
,`balance_sdg` decimal(15,2)
,`balance_aed` decimal(15,2)
,`credit_limit` decimal(15,2)
,`transaction_count` bigint(21)
,`total_volume` decimal(37,2)
,`last_transaction_date` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_daily_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_daily_summary` (
`summary_date` date
,`total_in_rmb` decimal(37,2)
,`total_out_rmb` decimal(37,2)
,`total_in_usd` decimal(37,2)
,`total_out_usd` decimal(37,2)
,`transaction_count` bigint(21)
);

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
-- Indexes for table `cashbox_currency_conversions`
--
ALTER TABLE `cashbox_currency_conversions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_currencies` (`from_currency`,`to_currency`),
  ADD KEY `idx_converted_at` (`converted_at`),
  ADD KEY `debit_movement_id` (`debit_movement_id`),
  ADD KEY `credit_movement_id` (`credit_movement_id`);

--
-- Indexes for table `cashbox_movements`
--
ALTER TABLE `cashbox_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_cash_date` (`movement_date`),
  ADD KEY `idx_cashbox_date_type` (`movement_date`,`movement_type`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_code` (`client_code`),
  ADD KEY `idx_clients_status_balance` (`status`,`balance_rmb`);

--
-- Indexes for table `client_analytics`
--
ALTER TABLE `client_analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_client_month` (`client_id`,`month_year`);

--
-- Indexes for table `currency_conversions`
--
ALTER TABLE `currency_conversions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversion_time` (`conversion_time`),
  ADD KEY `idx_currencies` (`from_currency`,`to_currency`);

--
-- Indexes for table `exchange_rates`
--
ALTER TABLE `exchange_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_pair_date` (`currency_pair`,`effective_date`);

--
-- Indexes for table `exchange_rate_history`
--
ALTER TABLE `exchange_rate_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pair_date` (`currency_pair`,`recorded_at`);

--
-- Indexes for table `financial_audit_log`
--
ALTER TABLE `financial_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

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
  ADD KEY `fk_bol_issued_by` (`bol_issued_by`),
  ADD KEY `idx_loadings_container_status` (`container_no`,`status`);

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
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username_ip` (`username`,`ip_address`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `office_notifications`
--
ALTER TABLE `office_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `office` (`office`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `reference` (`reference_type`,`reference_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier_action` (`identifier`,`action`),
  ADD KEY `idx_blocked_until` (`blocked_until`);

--
-- Indexes for table `security_audit`
--
ALTER TABLE `security_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_id`,`action`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `system_metrics`
--
ALTER TABLE `system_metrics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_metric_name_date` (`metric_name`,`recorded_at`);

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
  ADD KEY `idx_loading_id` (`loading_id`),
  ADD KEY `idx_parent_transaction` (`parent_transaction_id`),
  ADD KEY `idx_transactions_client_date` (`client_id`,`transaction_date`),
  ADD KEY `idx_transactions_status_amount` (`status`,`total_amount_rmb`);

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
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `api_sync_log`
--
ALTER TABLE `api_sync_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `cashbox_currency_conversions`
--
ALTER TABLE `cashbox_currency_conversions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashbox_movements`
--
ALTER TABLE `cashbox_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `client_analytics`
--
ALTER TABLE `client_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currency_conversions`
--
ALTER TABLE `currency_conversions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `exchange_rates`
--
ALTER TABLE `exchange_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `exchange_rate_history`
--
ALTER TABLE `exchange_rate_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `financial_audit_log`
--
ALTER TABLE `financial_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loadings`
--
ALTER TABLE `loadings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `loading_financial_records`
--
ALTER TABLE `loading_financial_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `loading_sync_log`
--
ALTER TABLE `loading_sync_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `office_notifications`
--
ALTER TABLE `office_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_audit`
--
ALTER TABLE `security_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `system_metrics`
--
ALTER TABLE `system_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `transaction_types`
--
ALTER TABLE `transaction_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `cashbox_summary`
--
DROP TABLE IF EXISTS `cashbox_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`khan`@`localhost` SQL SECURITY DEFINER VIEW `cashbox_summary`  AS SELECT sum(case when `cashbox_movements`.`movement_type` = 'in' then `cashbox_movements`.`amount_rmb` else -`cashbox_movements`.`amount_rmb` end) AS `balance_rmb`, sum(case when `cashbox_movements`.`movement_type` = 'in' then `cashbox_movements`.`amount_usd` else -`cashbox_movements`.`amount_usd` end) AS `balance_usd`, sum(case when `cashbox_movements`.`movement_type` = 'in' then `cashbox_movements`.`amount_sdg` else -`cashbox_movements`.`amount_sdg` end) AS `balance_sdg`, sum(case when `cashbox_movements`.`movement_type` = 'in' then `cashbox_movements`.`amount_aed` else -`cashbox_movements`.`amount_aed` end) AS `balance_aed` FROM `cashbox_movements` ;

-- --------------------------------------------------------

--
-- Structure for view `client_balances`
--
DROP TABLE IF EXISTS `client_balances`;

CREATE ALGORITHM=UNDEFINED DEFINER=`khan`@`localhost` SQL SECURITY DEFINER VIEW `client_balances`  AS SELECT `c`.`id` AS `id`, `c`.`name` AS `name`, `c`.`client_code` AS `client_code`, coalesce(sum(`t`.`balance_rmb`),0) AS `total_balance_rmb`, coalesce(sum(`t`.`balance_usd`),0) AS `total_balance_usd`, count(`t`.`id`) AS `transaction_count` FROM (`clients` `c` left join `transactions` `t` on(`c`.`id` = `t`.`client_id` and `t`.`status` = 'approved')) GROUP BY `c`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_client_summary`
--
DROP TABLE IF EXISTS `v_client_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`khan`@`localhost` SQL SECURITY DEFINER VIEW `v_client_summary`  AS SELECT `c`.`id` AS `id`, `c`.`client_code` AS `client_code`, `c`.`name` AS `name`, `c`.`name_ar` AS `name_ar`, `c`.`status` AS `status`, `c`.`balance_rmb` AS `balance_rmb`, `c`.`balance_usd` AS `balance_usd`, `c`.`balance_sdg` AS `balance_sdg`, `c`.`balance_aed` AS `balance_aed`, `c`.`credit_limit` AS `credit_limit`, coalesce(`t`.`transaction_count`,0) AS `transaction_count`, coalesce(`t`.`total_volume`,0) AS `total_volume`, coalesce(`t`.`last_transaction`,NULL) AS `last_transaction_date` FROM (`clients` `c` left join (select `transactions`.`client_id` AS `client_id`,count(0) AS `transaction_count`,sum(`transactions`.`total_amount_rmb`) AS `total_volume`,max(`transactions`.`transaction_date`) AS `last_transaction` from `transactions` where `transactions`.`status` = 'approved' group by `transactions`.`client_id`) `t` on(`c`.`id` = `t`.`client_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_daily_summary`
--
DROP TABLE IF EXISTS `v_daily_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`khan`@`localhost` SQL SECURITY DEFINER VIEW `v_daily_summary`  AS SELECT cast(`cashbox_movements`.`movement_date` as date) AS `summary_date`, sum(case when `cashbox_movements`.`movement_type` = 'in' then `cashbox_movements`.`amount_rmb` else 0 end) AS `total_in_rmb`, sum(case when `cashbox_movements`.`movement_type` = 'out' then `cashbox_movements`.`amount_rmb` else 0 end) AS `total_out_rmb`, sum(case when `cashbox_movements`.`movement_type` = 'in' then `cashbox_movements`.`amount_usd` else 0 end) AS `total_in_usd`, sum(case when `cashbox_movements`.`movement_type` = 'out' then `cashbox_movements`.`amount_usd` else 0 end) AS `total_out_usd`, count(0) AS `transaction_count` FROM `cashbox_movements` GROUP BY cast(`cashbox_movements`.`movement_date` as date) ORDER BY cast(`cashbox_movements`.`movement_date` as date) DESC ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `cashbox_currency_conversions`
--
ALTER TABLE `cashbox_currency_conversions`
  ADD CONSTRAINT `cashbox_currency_conversions_ibfk_1` FOREIGN KEY (`debit_movement_id`) REFERENCES `cashbox_movements` (`id`),
  ADD CONSTRAINT `cashbox_currency_conversions_ibfk_2` FOREIGN KEY (`credit_movement_id`) REFERENCES `cashbox_movements` (`id`);

--
-- Constraints for table `cashbox_movements`
--
ALTER TABLE `cashbox_movements`
  ADD CONSTRAINT `cashbox_movements_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`);

--
-- Constraints for table `client_analytics`
--
ALTER TABLE `client_analytics`
  ADD CONSTRAINT `client_analytics_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);

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
CREATE DEFINER=`khan`@`localhost` EVENT `retry_sync_event` ON SCHEDULE EVERY 1 HOUR STARTS '2025-07-17 15:46:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL retry_failed_syncs()$$

CREATE DEFINER=`khan`@`localhost` EVENT `clean_logs_event` ON SCHEDULE EVERY 1 WEEK STARTS '2025-07-17 15:46:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL clean_old_sync_logs()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
