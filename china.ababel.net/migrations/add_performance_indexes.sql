-- Add indexes for better performance
-- Transactions table indexes
CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(transaction_date);
CREATE INDEX IF NOT EXISTS idx_transactions_client_date ON transactions(client_id, transaction_date);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_transactions_bank ON transactions(bank_name);
CREATE INDEX IF NOT EXISTS idx_transactions_invoice ON transactions(invoice_no);

-- Clients table indexes
CREATE INDEX IF NOT EXISTS idx_clients_code ON clients(client_code);
CREATE INDEX IF NOT EXISTS idx_clients_status ON clients(status);
CREATE INDEX IF NOT EXISTS idx_clients_name ON clients(name);

-- Cashbox movements indexes
CREATE INDEX IF NOT EXISTS idx_cashbox_date ON cashbox_movements(movement_date);
CREATE INDEX IF NOT EXISTS idx_cashbox_type ON cashbox_movements(movement_type);
CREATE INDEX IF NOT EXISTS idx_cashbox_category ON cashbox_movements(category);
CREATE INDEX IF NOT EXISTS idx_cashbox_transaction ON cashbox_movements(transaction_id);

-- Loadings table indexes
CREATE INDEX IF NOT EXISTS idx_loadings_date ON loadings(loading_date);
CREATE INDEX IF NOT EXISTS idx_loadings_client ON loadings(client_id);
CREATE INDEX IF NOT EXISTS idx_loadings_status ON loadings(status);
CREATE INDEX IF NOT EXISTS idx_loadings_container ON loadings(container_no);
CREATE INDEX IF NOT EXISTS idx_loadings_claim ON loadings(claim_number);

-- Users table indexes
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Settings table indexes
CREATE INDEX IF NOT EXISTS idx_settings_key ON settings(setting_key);

-- API sync log indexes
CREATE INDEX IF NOT EXISTS idx_sync_entity ON api_sync_log(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_sync_status ON api_sync_log(sync_status);
CREATE INDEX IF NOT EXISTS idx_sync_date ON api_sync_log(created_at);

-- Analyze tables to update statistics
ANALYZE TABLE transactions;
ANALYZE TABLE clients;
ANALYZE TABLE cashbox_movements;
ANALYZE TABLE loadings;
ANALYZE TABLE users;
ANALYZE TABLE settings;