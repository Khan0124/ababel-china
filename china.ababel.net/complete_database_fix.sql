-- ================================================
-- COMPLETE DATABASE SCHEMA FIX
-- China Ababel Net - All Missing Tables and Columns
-- Generated on: 2025-08-15
-- ================================================

-- Disable foreign key checks during schema changes
SET FOREIGN_KEY_CHECKS = 0;

-- ================================================
-- CREATE MISSING TABLES
-- ================================================

-- 1. Create fiscal_year_sequences table
CREATE TABLE IF NOT EXISTS fiscal_year_sequences (
    id INT(11) NOT NULL AUTO_INCREMENT,
    fiscal_year VARCHAR(20) NOT NULL,
    last_loading_no INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_fiscal_year (fiscal_year),
    INDEX idx_fiscal_year (fiscal_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Fiscal year loading number sequences';

-- 2. Create access_log table for system monitoring
CREATE TABLE IF NOT EXISTS access_log (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    url VARCHAR(500) NULL,
    method VARCHAR(10) NULL,
    request_data LONGTEXT NULL,
    response_code INT(11) NULL,
    response_time DECIMAL(10,3) NULL,
    session_id VARCHAR(128) NULL,
    referrer VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address),
    INDEX idx_response_code (response_code),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='System access logs for monitoring';

-- 3. Create financial_audit_log table
CREATE TABLE IF NOT EXISTS financial_audit_log (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT(11) NOT NULL,
    old_values LONGTEXT NULL,
    new_values LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Financial operations audit trail';

-- 4. Create error_logs table
CREATE TABLE IF NOT EXISTS error_logs (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    error_type VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    context LONGTEXT NULL,
    stack_trace LONGTEXT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    user_id INT(11) NULL,
    ip_address VARCHAR(45) NULL,
    url VARCHAR(500) NULL,
    resolved TINYINT(1) DEFAULT 0,
    resolved_by INT(11) NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_error_type (error_type),
    INDEX idx_severity (severity),
    INDEX idx_resolved (resolved),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Application error logs';

-- ================================================
-- ADD MISSING COLUMNS TO EXISTING TABLES
-- ================================================

-- Add missing columns to currency_conversions table if they don't exist
ALTER TABLE currency_conversions 
ADD COLUMN IF NOT EXISTS conversion_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER exchange_rate,
ADD COLUMN IF NOT EXISTS debit_movement_id INT(11) NULL AFTER created_by,
ADD COLUMN IF NOT EXISTS credit_movement_id INT(11) NULL AFTER debit_movement_id,
ADD COLUMN IF NOT EXISTS updated_by INT(11) NULL AFTER created_by;

-- Add foreign key indexes for currency_conversions
ALTER TABLE currency_conversions 
ADD INDEX IF NOT EXISTS idx_debit_movement (debit_movement_id),
ADD INDEX IF NOT EXISTS idx_credit_movement (credit_movement_id),
ADD INDEX IF NOT EXISTS idx_updated_by (updated_by);

-- Add foreign keys for currency_conversions
ALTER TABLE currency_conversions 
ADD CONSTRAINT IF NOT EXISTS fk_currency_conv_debit FOREIGN KEY (debit_movement_id) REFERENCES cashbox_movements(id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_currency_conv_credit FOREIGN KEY (credit_movement_id) REFERENCES cashbox_movements(id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_currency_conv_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- Ensure all balance columns exist in clients table (they already exist based on DESCRIBE output)
-- Just adding an updated_by column if missing
ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS updated_by INT(11) NULL AFTER created_by;

-- Add index for updated_by in clients
ALTER TABLE clients 
ADD INDEX IF NOT EXISTS idx_clients_updated_by (updated_by);

-- Add foreign key for clients.updated_by
ALTER TABLE clients 
ADD CONSTRAINT IF NOT EXISTS fk_clients_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- Ensure all transaction columns exist (they appear to exist based on DESCRIBE)
-- Add updated_by column to transactions if missing
ALTER TABLE transactions 
ADD COLUMN IF NOT EXISTS updated_by INT(11) NULL AFTER approved_by;

-- Add index for updated_by in transactions
ALTER TABLE transactions 
ADD INDEX IF NOT EXISTS idx_transactions_updated_by (updated_by);

-- Add foreign key for transactions.updated_by
ALTER TABLE transactions 
ADD CONSTRAINT IF NOT EXISTS fk_transactions_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- Add conversion_time to currency_conversions if it doesn't exist (duplicate safe)
ALTER TABLE currency_conversions 
ADD COLUMN IF NOT EXISTS conversion_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- ================================================
-- ADD MISSING INDEXES FOR PERFORMANCE
-- ================================================

-- Add performance indexes to transactions table
ALTER TABLE transactions 
ADD INDEX IF NOT EXISTS idx_transaction_client_status (client_id, status),
ADD INDEX IF NOT EXISTS idx_transaction_type_date (transaction_type_id, transaction_date),
ADD INDEX IF NOT EXISTS idx_transaction_loading (loading_id),
ADD INDEX IF NOT EXISTS idx_transaction_approved (approved_by, approved_at),
ADD INDEX IF NOT EXISTS idx_transaction_created (created_by, created_at);

-- Add performance indexes to clients table
ALTER TABLE clients 
ADD INDEX IF NOT EXISTS idx_clients_status (status),
ADD INDEX IF NOT EXISTS idx_clients_code_status (client_code, status),
ADD INDEX IF NOT EXISTS idx_clients_balances (balance_rmb, balance_usd, balance_sdg, balance_aed);

-- Add performance indexes to loadings table
ALTER TABLE loadings 
ADD INDEX IF NOT EXISTS idx_loadings_date_status (shipping_date, status),
ADD INDEX IF NOT EXISTS idx_loadings_client_status (client_id, status),
ADD INDEX IF NOT EXISTS idx_loadings_office_status (office, status),
ADD INDEX IF NOT EXISTS idx_loadings_sync (sync_status, last_sync_at);

-- Add performance indexes to cashbox_movements table
ALTER TABLE cashbox_movements 
ADD INDEX IF NOT EXISTS idx_cashbox_date_type (movement_date, movement_type),
ADD INDEX IF NOT EXISTS idx_cashbox_transaction (transaction_id),
ADD INDEX IF NOT EXISTS idx_cashbox_category (category),
ADD INDEX IF NOT EXISTS idx_cashbox_status (status);

-- Add performance indexes to exchange_rates table
ALTER TABLE exchange_rates 
ADD INDEX IF NOT EXISTS idx_exchange_pair_active (currency_pair, is_active),
ADD INDEX IF NOT EXISTS idx_exchange_from_to (from_currency, to_currency),
ADD INDEX IF NOT EXISTS idx_exchange_updated (last_updated);

-- ================================================
-- UPDATE EXISTING DATA TO ENSURE CONSISTENCY
-- ================================================

-- Initialize fiscal year sequences for existing loadings
INSERT IGNORE INTO fiscal_year_sequences (fiscal_year, last_loading_no)
SELECT 
    CONCAT(
        CASE 
            WHEN MONTH(shipping_date) >= 3 THEN YEAR(shipping_date)
            ELSE YEAR(shipping_date) - 1
        END,
        '-',
        CASE 
            WHEN MONTH(shipping_date) >= 3 THEN YEAR(shipping_date) + 1
            ELSE YEAR(shipping_date)
        END
    ) as fiscal_year,
    COALESCE(MAX(CAST(SUBSTRING_INDEX(loading_no, '-', -1) AS UNSIGNED)), 0) as last_loading_no
FROM loadings 
WHERE loading_no IS NOT NULL AND loading_no != ''
GROUP BY fiscal_year;

-- ================================================
-- CREATE SYSTEM SETTINGS FOR EXCHANGE RATES
-- ================================================

-- Insert default exchange rate settings if they don't exist
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, created_by) VALUES
('exchange_rate_usd_rmb', '7.25', 'decimal', 1),
('exchange_rate_sdg_rmb', '0.012', 'decimal', 1),
('exchange_rate_aed_rmb', '1.97', 'decimal', 1),
('exchange_rate_usd_sdg', '604.17', 'decimal', 1),
('exchange_rate_usd_aed', '3.68', 'decimal', 1),
('exchange_rate_rmb_sdg', '83.33', 'decimal', 1),
('exchange_rate_rmb_aed', '0.51', 'decimal', 1),
('exchange_rate_sdg_aed', '0.0061', 'decimal', 1),
('auto_update_rates', '1', 'boolean', 1),
('rate_update_interval', '3600', 'integer', 1),
('default_currency', 'RMB', 'string', 1);

-- ================================================
-- ENSURE FOREIGN KEY RELATIONSHIPS ARE PROPER
-- ================================================

-- Add missing foreign keys for existing tables (using IF NOT EXISTS equivalent approach)
-- Note: MySQL doesn't support IF NOT EXISTS for foreign keys, so we need to use a different approach

-- Check and add foreign keys for transactions table
SET @foreign_key_exists = (
    SELECT COUNT(*) 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'transactions' 
    AND CONSTRAINT_NAME = 'fk_transactions_client'
);

SET @sql = IF(@foreign_key_exists = 0, 
    'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT',
    'SELECT "Foreign key fk_transactions_client already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for loading_id in transactions
SET @foreign_key_exists = (
    SELECT COUNT(*) 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'transactions' 
    AND CONSTRAINT_NAME = 'fk_transactions_loading'
);

SET @sql = IF(@foreign_key_exists = 0, 
    'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_loading FOREIGN KEY (loading_id) REFERENCES loadings(id) ON DELETE SET NULL',
    'SELECT "Foreign key fk_transactions_loading already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for transaction_type_id in transactions
SET @foreign_key_exists = (
    SELECT COUNT(*) 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'transactions' 
    AND CONSTRAINT_NAME = 'fk_transactions_type'
);

SET @sql = IF(@foreign_key_exists = 0, 
    'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_type FOREIGN KEY (transaction_type_id) REFERENCES transaction_types(id) ON DELETE RESTRICT',
    'SELECT "Foreign key fk_transactions_type already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ================================================
-- VERIFY DATA INTEGRITY
-- ================================================

-- Update NULL balance values to 0 in clients table
UPDATE clients SET 
    balance_rmb = COALESCE(balance_rmb, 0),
    balance_usd = COALESCE(balance_usd, 0),
    balance_sdg = COALESCE(balance_sdg, 0),
    balance_aed = COALESCE(balance_aed, 0)
WHERE balance_rmb IS NULL OR balance_usd IS NULL OR balance_sdg IS NULL OR balance_aed IS NULL;

-- Update NULL payment values to 0 in transactions table
UPDATE transactions SET 
    payment_rmb = COALESCE(payment_rmb, 0),
    payment_usd = COALESCE(payment_usd, 0),
    payment_sdg = COALESCE(payment_sdg, 0),
    payment_aed = COALESCE(payment_aed, 0),
    balance_rmb = COALESCE(balance_rmb, 0),
    balance_usd = COALESCE(balance_usd, 0),
    balance_sdg = COALESCE(balance_sdg, 0),
    balance_aed = COALESCE(balance_aed, 0)
WHERE 
    payment_rmb IS NULL OR payment_usd IS NULL OR payment_sdg IS NULL OR payment_aed IS NULL OR
    balance_rmb IS NULL OR balance_usd IS NULL OR balance_sdg IS NULL OR balance_aed IS NULL;

-- Update NULL amount values to 0 in cashbox_movements table
UPDATE cashbox_movements SET 
    amount_rmb = COALESCE(amount_rmb, 0),
    amount_usd = COALESCE(amount_usd, 0),
    amount_sdg = COALESCE(amount_sdg, 0),
    amount_aed = COALESCE(amount_aed, 0)
WHERE 
    amount_rmb IS NULL OR amount_usd IS NULL OR amount_sdg IS NULL OR amount_aed IS NULL;

-- ================================================
-- CREATE ADDITIONAL PERFORMANCE INDEXES
-- ================================================

-- Add composite indexes for common queries
ALTER TABLE transactions 
ADD INDEX IF NOT EXISTS idx_client_date_status (client_id, transaction_date, status),
ADD INDEX IF NOT EXISTS idx_type_status_date (transaction_type_id, status, transaction_date);

ALTER TABLE loadings 
ADD INDEX IF NOT EXISTS idx_client_office_status (client_id, office, status),
ADD INDEX IF NOT EXISTS idx_shipping_office (shipping_date, office);

ALTER TABLE cashbox_movements 
ADD INDEX IF NOT EXISTS idx_date_type_category (movement_date, movement_type, category),
ADD INDEX IF NOT EXISTS idx_created_by_date (created_by, created_at);

-- ================================================
-- RE-ENABLE FOREIGN KEY CHECKS
-- ================================================

SET FOREIGN_KEY_CHECKS = 1;

-- ================================================
-- CREATE VERIFICATION QUERIES
-- ================================================

-- Create a view to check data integrity
CREATE OR REPLACE VIEW data_integrity_check AS
SELECT 
    'clients' as table_name,
    COUNT(*) as total_records,
    SUM(CASE WHEN balance_rmb IS NULL OR balance_usd IS NULL OR balance_sdg IS NULL OR balance_aed IS NULL THEN 1 ELSE 0 END) as null_balance_count,
    SUM(CASE WHEN client_code IS NULL OR client_code = '' THEN 1 ELSE 0 END) as missing_code_count
FROM clients
UNION ALL
SELECT 
    'transactions',
    COUNT(*),
    SUM(CASE WHEN payment_rmb IS NULL OR payment_usd IS NULL OR payment_sdg IS NULL OR payment_aed IS NULL THEN 1 ELSE 0 END),
    SUM(CASE WHEN transaction_no IS NULL OR transaction_no = '' THEN 1 ELSE 0 END)
FROM transactions
UNION ALL
SELECT 
    'cashbox_movements',
    COUNT(*),
    SUM(CASE WHEN amount_rmb IS NULL OR amount_usd IS NULL OR amount_sdg IS NULL OR amount_aed IS NULL THEN 1 ELSE 0 END),
    SUM(CASE WHEN movement_type IS NULL THEN 1 ELSE 0 END)
FROM cashbox_movements;

-- Show completion summary
SELECT 
    'DATABASE SCHEMA FIX COMPLETED' as message,
    NOW() as completed_at,
    (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()) as total_tables,
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE()) as total_columns,
    (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE table_schema = DATABASE() AND CONSTRAINT_NAME LIKE 'fk_%') as foreign_keys;

-- Show the integrity check results
SELECT * FROM data_integrity_check;