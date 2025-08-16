-- Create financial audit log table
CREATE TABLE IF NOT EXISTS financial_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add triggers for transaction auditing
DELIMITER $$

CREATE TRIGGER transaction_insert_audit
AFTER INSERT ON transactions
FOR EACH ROW
BEGIN
    INSERT INTO financial_audit_log (
        user_id,
        action,
        entity_type,
        entity_id,
        new_values,
        description,
        created_at
    ) VALUES (
        IFNULL(NEW.created_by, 0),
        'CREATE',
        'transaction',
        NEW.id,
        JSON_OBJECT(
            'transaction_no', NEW.transaction_no,
            'client_id', NEW.client_id,
            'total_amount_rmb', NEW.total_amount_rmb,
            'payment_rmb', NEW.payment_rmb,
            'payment_usd', NEW.payment_usd,
            'payment_sdg', NEW.payment_sdg,
            'payment_aed', NEW.payment_aed,
            'status', NEW.status
        ),
        CONCAT('Transaction created: ', NEW.transaction_no),
        NOW()
    );
END$$

CREATE TRIGGER transaction_update_audit
AFTER UPDATE ON transactions
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status OR 
       OLD.payment_rmb != NEW.payment_rmb OR 
       OLD.payment_usd != NEW.payment_usd OR
       OLD.payment_sdg != NEW.payment_sdg OR
       OLD.payment_aed != NEW.payment_aed OR
       OLD.balance_rmb != NEW.balance_rmb OR
       OLD.balance_usd != NEW.balance_usd OR
       OLD.balance_sdg != NEW.balance_sdg OR
       OLD.balance_aed != NEW.balance_aed THEN
        
        INSERT INTO financial_audit_log (
            user_id,
            action,
            entity_type,
            entity_id,
            old_values,
            new_values,
            description,
            created_at
        ) VALUES (
            IFNULL(NEW.approved_by, IFNULL(NEW.created_by, 0)),
            'UPDATE',
            'transaction',
            NEW.id,
            JSON_OBJECT(
                'status', OLD.status,
                'payment_rmb', OLD.payment_rmb,
                'payment_usd', OLD.payment_usd,
                'payment_sdg', OLD.payment_sdg,
                'payment_aed', OLD.payment_aed,
                'balance_rmb', OLD.balance_rmb,
                'balance_usd', OLD.balance_usd,
                'balance_sdg', OLD.balance_sdg,
                'balance_aed', OLD.balance_aed
            ),
            JSON_OBJECT(
                'status', NEW.status,
                'payment_rmb', NEW.payment_rmb,
                'payment_usd', NEW.payment_usd,
                'payment_sdg', NEW.payment_sdg,
                'payment_aed', NEW.payment_aed,
                'balance_rmb', NEW.balance_rmb,
                'balance_usd', NEW.balance_usd,
                'balance_sdg', NEW.balance_sdg,
                'balance_aed', NEW.balance_aed
            ),
            CONCAT('Transaction updated: ', NEW.transaction_no),
            NOW()
        );
    END IF;
END$$

DELIMITER ;