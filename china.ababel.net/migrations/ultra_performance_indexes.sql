-- فهارس الأداء المتقدمة للسرعة القصوى
-- تاريخ الإنشاء: 2025-08-15

-- فهارس مركبة للعمليات الأكثر استخداماً
CREATE INDEX IF NOT EXISTS idx_transactions_client_status_date 
ON transactions(client_id, status, created_at);

CREATE INDEX IF NOT EXISTS idx_transactions_type_currency_amount 
ON transactions(type, currency, amount);

CREATE INDEX IF NOT EXISTS idx_clients_active_balance 
ON clients(status, balance_rmb, balance_usd, balance_aed);

CREATE INDEX IF NOT EXISTS idx_loadings_status_shipping_date 
ON loadings(status, shipping_date, created_at);

CREATE INDEX IF NOT EXISTS idx_cashbox_movements_date_type 
ON cashbox_movements(DATE(created_at), type, amount);

-- فهارس للبحث السريع
CREATE INDEX IF NOT EXISTS idx_clients_name_code 
ON clients(name(20), client_code);

CREATE INDEX IF NOT EXISTS idx_transactions_invoice_loading 
ON transactions(invoice_no(10), loading_id);

CREATE INDEX IF NOT EXISTS idx_loadings_container_claim 
ON loadings(container_no(15), claim_number(10));

-- فهارس للتقارير اليومية والشهرية
CREATE INDEX IF NOT EXISTS idx_transactions_daily_reports 
ON transactions(DATE(created_at), type, status);

CREATE INDEX IF NOT EXISTS idx_transactions_monthly_reports 
ON transactions(YEAR(created_at), MONTH(created_at), currency);

-- فهارس للعمليات المالية
CREATE INDEX IF NOT EXISTS idx_transactions_payment_status 
ON transactions(status, due_date, amount_rmb);

CREATE INDEX IF NOT EXISTS idx_client_balances_currency_amount 
ON client_balances(currency, balance_amount, last_updated);

-- فهارس لتحسين الاستعلامات التجميعية
CREATE INDEX IF NOT EXISTS idx_transactions_client_sum 
ON transactions(client_id, currency, amount, status);

CREATE INDEX IF NOT EXISTS idx_cashbox_summary 
ON cashbox_movements(type, currency, DATE(created_at));

-- فهارس للأمان والتدقيق
CREATE INDEX IF NOT EXISTS idx_access_log_user_date 
ON access_log(user_id, created_at, success);

CREATE INDEX IF NOT EXISTS idx_financial_audit_user_date 
ON financial_audit_log(user_id, created_at, entity_type);

-- فهارس لتحسين الصفحات المرقمة
CREATE INDEX IF NOT EXISTS idx_transactions_pagination 
ON transactions(id DESC, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_clients_pagination 
ON clients(id DESC, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_loadings_pagination 
ON loadings(id DESC, created_at DESC);

-- فهارس للبحث النصي المحسن
CREATE FULLTEXT INDEX IF NOT EXISTS ft_clients_search 
ON clients(name, address, notes);

CREATE FULLTEXT INDEX IF NOT EXISTS ft_transactions_search 
ON transactions(description, notes);

CREATE FULLTEXT INDEX IF NOT EXISTS ft_loadings_search 
ON loadings(item_description, notes);

-- فهارس للعملات وأسعار الصرف
CREATE INDEX IF NOT EXISTS idx_exchange_rates_active 
ON exchange_rates(is_active, last_updated, from_currency, to_currency);

-- تحليل الجداول لتحديث الإحصائيات
ANALYZE TABLE clients;
ANALYZE TABLE transactions;
ANALYZE TABLE loadings;
ANALYZE TABLE cashbox_movements;
ANALYZE TABLE exchange_rates;
ANALYZE TABLE access_log;
ANALYZE TABLE financial_audit_log;