-- Exchange rates base tables
CREATE TABLE IF NOT EXISTS exchange_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  currency_pair VARCHAR(16) NOT NULL,
  rate DECIMAL(18,8) NOT NULL,
  source VARCHAR(32) DEFAULT 'manual',
  effective_date DATE NOT NULL,
  last_updated DATETIME NOT NULL,
  UNIQUE KEY uniq_pair (currency_pair)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exchange_rate_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  currency_pair VARCHAR(16) NOT NULL,
  rate DECIMAL(18,8) NOT NULL,
  source VARCHAR(32) DEFAULT 'manual',
  recorded_at DATETIME NOT NULL,
  INDEX idx_pair_time (currency_pair, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS currency_conversions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  original_amount DECIMAL(18,4) NOT NULL,
  from_currency VARCHAR(8) NOT NULL,
  converted_amount DECIMAL(18,4) NOT NULL,
  to_currency VARCHAR(8) NOT NULL,
  exchange_rate DECIMAL(18,8) NOT NULL,
  conversion_time DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_convert_time (conversion_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;