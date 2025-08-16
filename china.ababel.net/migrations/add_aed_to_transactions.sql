-- Add AED columns to transactions table if they don't exist
ALTER TABLE `transactions` 
ADD COLUMN IF NOT EXISTS `payment_aed` decimal(15,2) DEFAULT 0.00 AFTER `payment_sdg`,
ADD COLUMN IF NOT EXISTS `balance_aed` decimal(15,2) DEFAULT 0.00 AFTER `balance_sdg`,
ADD COLUMN IF NOT EXISTS `rate_aed_rmb` decimal(10,4) DEFAULT NULL AFTER `rate_sdg_rmb`;

-- Add AED column to cashbox_movements if it doesn't exist
ALTER TABLE `cashbox_movements` 
ADD COLUMN IF NOT EXISTS `amount_aed` decimal(15,2) DEFAULT 0.00 AFTER `amount_sdg`;

-- Update fiscal year procedure to support AED
DELIMITER $$

DROP PROCEDURE IF EXISTS `calculate_client_balance_multi_currency`$$

CREATE PROCEDURE `calculate_client_balance_multi_currency`(IN client_id INT)
BEGIN
    DECLARE total_claims_rmb DECIMAL(15,2) DEFAULT 0;
    DECLARE total_claims_usd DECIMAL(15,2) DEFAULT 0;
    DECLARE total_claims_sdg DECIMAL(15,2) DEFAULT 0;
    DECLARE total_claims_aed DECIMAL(15,2) DEFAULT 0;
    DECLARE total_payments_rmb DECIMAL(15,2) DEFAULT 0;
    DECLARE total_payments_usd DECIMAL(15,2) DEFAULT 0;
    DECLARE total_payments_sdg DECIMAL(15,2) DEFAULT 0;
    DECLARE total_payments_aed DECIMAL(15,2) DEFAULT 0;
    
    -- Calculate total claims (from expense transactions)
    SELECT 
        COALESCE(SUM(balance_rmb), 0),
        COALESCE(SUM(balance_usd), 0),
        COALESCE(SUM(balance_sdg), 0),
        COALESCE(SUM(balance_aed), 0)
    INTO 
        total_claims_rmb, 
        total_claims_usd, 
        total_claims_sdg,
        total_claims_aed
    FROM transactions t
    JOIN transaction_types tt ON t.transaction_type_id = tt.id
    WHERE t.client_id = client_id 
    AND t.status IN ('approved', 'pending')
    AND tt.type = 'expense';
    
    -- Calculate total payments (from income transactions)
    SELECT 
        COALESCE(SUM(ABS(balance_rmb)), 0),
        COALESCE(SUM(ABS(balance_usd)), 0),
        COALESCE(SUM(ABS(balance_sdg)), 0),
        COALESCE(SUM(ABS(balance_aed)), 0)
    INTO 
        total_payments_rmb, 
        total_payments_usd, 
        total_payments_sdg,
        total_payments_aed
    FROM transactions t
    JOIN transaction_types tt ON t.transaction_type_id = tt.id
    WHERE t.client_id = client_id 
    AND t.status = 'approved'
    AND tt.type = 'income';
    
    -- Update client balance
    UPDATE clients 
    SET 
        balance_rmb = total_claims_rmb - total_payments_rmb,
        balance_usd = total_claims_usd - total_payments_usd,
        balance_sdg = total_claims_sdg - total_payments_sdg,
        balance_aed = total_claims_aed - total_payments_aed
    WHERE id = client_id;
END$$

DELIMITER ;