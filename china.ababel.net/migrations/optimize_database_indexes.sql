-- Optimize Database Indexes for Better Performance
-- Created: 2025-01-07

-- Indexes for clients table
CREATE INDEX IF NOT EXISTS idx_clients_status ON clients(status);
CREATE INDEX IF NOT EXISTS idx_clients_client_code ON clients(client_code);
CREATE INDEX IF NOT EXISTS idx_clients_created_at ON clients(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_clients_name ON clients(name);
CREATE INDEX IF NOT EXISTS idx_clients_phone ON clients(phone);
CREATE INDEX IF NOT EXISTS idx_clients_balance_rmb ON clients(balance_rmb);
CREATE INDEX IF NOT EXISTS idx_clients_balance_usd ON clients(balance_usd);

-- Composite index for search
CREATE INDEX IF NOT EXISTS idx_clients_search ON clients(status, name, client_code);

-- Indexes for transactions table
CREATE INDEX IF NOT EXISTS idx_transactions_client_id ON transactions(client_id);
CREATE INDEX IF NOT EXISTS idx_transactions_transaction_date ON transactions(transaction_date DESC);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_transactions_type ON transactions(type);
CREATE INDEX IF NOT EXISTS idx_transactions_claim_number ON transactions(claim_number);
CREATE INDEX IF NOT EXISTS idx_transactions_created_at ON transactions(created_at DESC);

-- Composite indexes for common queries
CREATE INDEX IF NOT EXISTS idx_transactions_client_status ON transactions(client_id, status);
CREATE INDEX IF NOT EXISTS idx_transactions_date_status ON transactions(transaction_date, status);
CREATE INDEX IF NOT EXISTS idx_transactions_client_date ON transactions(client_id, transaction_date DESC);

-- Indexes for loadings table if exists
CREATE INDEX IF NOT EXISTS idx_loadings_container_number ON loadings(container_number);
CREATE INDEX IF NOT EXISTS idx_loadings_bol_number ON loadings(bol_number);
CREATE INDEX IF NOT EXISTS idx_loadings_status ON loadings(status);
CREATE INDEX IF NOT EXISTS idx_loadings_client_id ON loadings(client_id);
CREATE INDEX IF NOT EXISTS idx_loadings_loading_date ON loadings(loading_date DESC);

-- Indexes for cashbox table if exists
CREATE INDEX IF NOT EXISTS idx_cashbox_transaction_date ON cashbox_transactions(transaction_date DESC);
CREATE INDEX IF NOT EXISTS idx_cashbox_type ON cashbox_transactions(type);
CREATE INDEX IF NOT EXISTS idx_cashbox_created_at ON cashbox_transactions(created_at DESC);

-- Indexes for users table
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);

-- Analyze tables to update statistics
ANALYZE TABLE clients;
ANALYZE TABLE transactions;
ANALYZE TABLE loadings;
ANALYZE TABLE cashbox_transactions;
ANALYZE TABLE users;