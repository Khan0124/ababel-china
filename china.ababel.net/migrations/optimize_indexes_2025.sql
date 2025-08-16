-- تحسين فهارس قاعدة البيانات
-- التاريخ: 2025-08-10

-- فهارس محسنة لجدول transactions
-- فهرس مركب للبحث حسب العميل والتاريخ (مفيد للتقارير)
ALTER TABLE transactions ADD INDEX IF NOT EXISTS idx_client_date (client_id, transaction_date);

-- فهرس للبحث حسب الحالة والتاريخ
ALTER TABLE transactions ADD INDEX IF NOT EXISTS idx_status_date (status, transaction_date);

-- فهرس للمبالغ المستحقة
ALTER TABLE transactions ADD INDEX IF NOT EXISTS idx_balance_status (balance_rmb, status) WHERE balance_rmb > 0;

-- فهارس محسنة لجدول clients
-- فهرس للبحث بالاسم
ALTER TABLE clients ADD INDEX IF NOT EXISTS idx_client_name (client_name);

-- فهرس للأرصدة غير الصفرية
ALTER TABLE clients ADD INDEX IF NOT EXISTS idx_balance_nonzero (balance_rmb) WHERE balance_rmb != 0;

-- فهارس محسنة لجدول loadings
-- فهرس مركب للبحث المتقدم
ALTER TABLE loadings ADD INDEX IF NOT EXISTS idx_search_composite (
    client_code, 
    container_no, 
    shipping_date,
    status
);

-- فهرس للشحنات غير المكتملة
ALTER TABLE loadings ADD INDEX IF NOT EXISTS idx_pending_loadings (status, shipping_date) 
WHERE status IN ('pending', 'in_transit');

-- فهارس لجدول cashbox_movements
ALTER TABLE cashbox_movements ADD INDEX IF NOT EXISTS idx_cashbox_date (movement_date, category);
ALTER TABLE cashbox_movements ADD INDEX IF NOT EXISTS idx_cashbox_type (type, movement_date);

-- فهارس لجداول التدقيق
ALTER TABLE audit_log ADD INDEX IF NOT EXISTS idx_audit_date (created_at);
ALTER TABLE audit_log ADD INDEX IF NOT EXISTS idx_audit_user (user_id, created_at);
ALTER TABLE financial_audit_log ADD INDEX IF NOT EXISTS idx_financial_audit (created_at, action);

-- تحليل الجداول لتحديث الإحصائيات
ANALYZE TABLE transactions;
ANALYZE TABLE clients;
ANALYZE TABLE loadings;
ANALYZE TABLE cashbox_movements;
ANALYZE TABLE audit_log;
ANALYZE TABLE financial_audit_log;