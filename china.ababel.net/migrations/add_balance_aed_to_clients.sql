-- Add balance_aed field to clients table if it doesn't exist
ALTER TABLE `clients` 
ADD COLUMN IF NOT EXISTS `balance_aed` decimal(15,2) DEFAULT 0.00 
AFTER `balance_sdg`;