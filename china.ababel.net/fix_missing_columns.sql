-- Fix Missing Database Columns
-- Generated on 2025-08-15

-- 1. Add missing balance_after columns to cashbox_movements table
ALTER TABLE `cashbox_movements` 
ADD COLUMN `balance_after_sdg` DECIMAL(15,2) DEFAULT NULL COMMENT 'Balance after movement in SDG',
ADD COLUMN `balance_after_aed` DECIMAL(15,2) DEFAULT NULL COMMENT 'Balance after movement in AED';

-- 2. Add missing timestamp columns to exchange_rates table
ALTER TABLE `exchange_rates` 
ADD COLUMN `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last time rate was updated',
ADD COLUMN `effective_date` DATE DEFAULT (CURDATE()) COMMENT 'Date when rate becomes effective';

-- 3. Add missing audit columns to clients table
ALTER TABLE `clients` 
ADD COLUMN `created_by` INT(11) DEFAULT NULL COMMENT 'User who created the client',
ADD COLUMN `updated_by` INT(11) DEFAULT NULL COMMENT 'User who last updated the client';

-- 4. Add missing audit columns to transaction_types table
ALTER TABLE `transaction_types` 
ADD COLUMN `created_by` INT(11) DEFAULT NULL COMMENT 'User who created the transaction type',
ADD COLUMN `updated_by` INT(11) DEFAULT NULL COMMENT 'User who last updated the transaction type',
ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp',
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp';

-- 5. Add missing audit columns to office_notifications table
ALTER TABLE `office_notifications` 
ADD COLUMN `created_by` INT(11) DEFAULT NULL COMMENT 'User who created the notification',
ADD COLUMN `updated_by` INT(11) DEFAULT NULL COMMENT 'User who last updated the notification',
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp';

-- 6. Add missing audit columns to settings table
ALTER TABLE `settings` 
ADD COLUMN `created_by` INT(11) DEFAULT NULL COMMENT 'User who created the setting',
ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp';

-- 7. Add foreign key constraints for audit columns
ALTER TABLE `cashbox_movements` 
ADD CONSTRAINT `fk_cashbox_movements_created_by` 
FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `clients` 
ADD CONSTRAINT `fk_clients_created_by` 
FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_clients_updated_by` 
FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `transaction_types` 
ADD CONSTRAINT `fk_transaction_types_created_by` 
FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_transaction_types_updated_by` 
FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `office_notifications` 
ADD CONSTRAINT `fk_office_notifications_created_by` 
FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_office_notifications_updated_by` 
FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `settings` 
ADD CONSTRAINT `fk_settings_created_by` 
FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- 8. Update existing exchange_rates records to set effective_date to created_at date
UPDATE `exchange_rates` 
SET `effective_date` = DATE(`created_at`), 
    `last_updated` = `updated_at` 
WHERE `effective_date` IS NULL OR `last_updated` IS NULL;

-- 9. Create indexes for performance
CREATE INDEX `idx_cashbox_movements_balance_after` ON `cashbox_movements`(`balance_after_rmb`, `balance_after_usd`, `balance_after_sdg`, `balance_after_aed`);
CREATE INDEX `idx_exchange_rates_effective_date` ON `exchange_rates`(`effective_date`);
CREATE INDEX `idx_exchange_rates_last_updated` ON `exchange_rates`(`last_updated`);
CREATE INDEX `idx_clients_created_by` ON `clients`(`created_by`);
CREATE INDEX `idx_clients_updated_by` ON `clients`(`updated_by`);

-- 10. Add comments to existing tables for better documentation
ALTER TABLE `cashbox_movements` COMMENT = 'Tracks all cashbox movement transactions with balance tracking';
ALTER TABLE `exchange_rates` COMMENT = 'Current exchange rates with effective dates and update tracking';
ALTER TABLE `clients` COMMENT = 'Client information with multi-currency balance tracking';
ALTER TABLE `transaction_types` COMMENT = 'Transaction type definitions with audit trail';
ALTER TABLE `office_notifications` COMMENT = 'Office notification system with audit trail';
ALTER TABLE `settings` COMMENT = 'Application settings with audit trail';