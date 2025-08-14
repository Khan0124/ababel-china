-- Cashbox currency conversions and related audit tables
CREATE TABLE IF NOT EXISTS cashbox_currency_conversions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  from_currency VARCHAR(8) NOT NULL,
  to_currency VARCHAR(8) NOT NULL,
  original_amount DECIMAL(18,4) NOT NULL,
  converted_amount DECIMAL(18,4) NOT NULL,
  exchange_rate DECIMAL(18,8) NOT NULL,
  debit_movement_id INT NULL,
  credit_movement_id INT NULL,
  description VARCHAR(255) NULL,
  converted_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_converted_at (converted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fiscal_year_sequences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fiscal_year VARCHAR(9) NOT NULL UNIQUE,
  last_loading_no INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loading_financial_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loading_id INT NOT NULL,
  client_id INT NOT NULL,
  transaction_type VARCHAR(32) NOT NULL,
  amount_rmb DECIMAL(18,2) NOT NULL DEFAULT 0,
  amount_usd DECIMAL(18,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_loading (loading_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  office VARCHAR(64) NOT NULL,
  type VARCHAR(64) NOT NULL,
  message VARCHAR(255) NOT NULL,
  reference_type VARCHAR(64) NOT NULL,
  reference_id INT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_office_read (office, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loading_sync_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loading_id INT NOT NULL,
  action VARCHAR(32) NOT NULL,
  status VARCHAR(32) NOT NULL,
  response_data TEXT NULL,
  error_message TEXT NULL,
  synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_loading_action (loading_id, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_sync_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  endpoint VARCHAR(255) NOT NULL,
  method VARCHAR(16) NOT NULL,
  china_loading_id INT NULL,
  request_data LONGTEXT NULL,
  response_code INT NULL,
  response_data LONGTEXT NULL,
  ip_address VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_endpoint_created (endpoint(100), created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;