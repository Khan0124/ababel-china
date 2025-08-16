-- Create table to track loading number sequences per fiscal year
CREATE TABLE IF NOT EXISTS fiscal_year_sequences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fiscal_year VARCHAR(10) NOT NULL UNIQUE,
    last_loading_no INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for quick lookups
CREATE INDEX idx_fiscal_year ON fiscal_year_sequences(fiscal_year);