-- Add AED support to transactions table
ALTER TABLE transactions
ADD COLUMN payment_aed DECIMAL(15,2) DEFAULT 0.00 AFTER payment_sdg,
ADD COLUMN balance_aed DECIMAL(15,2) DEFAULT 0.00 AFTER payment_aed,
ADD COLUMN rate_aed_rmb DECIMAL(10,4) DEFAULT NULL AFTER rate_sdg_rmb;

-- Add AED balance to clients table  
ALTER TABLE clients
ADD COLUMN balance_aed DECIMAL(15,2) DEFAULT 0.00 AFTER balance_sdg;

-- Update the exchange rate for AED if not exists
INSERT INTO settings (setting_key, setting_value, setting_type)
VALUES ('exchange_rate_aed_rmb', '2.00', 'exchange_rate')
ON DUPLICATE KEY UPDATE setting_value = setting_value;