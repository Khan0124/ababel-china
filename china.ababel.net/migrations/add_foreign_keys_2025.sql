-- إضافة Foreign Key Constraints
-- التاريخ: 2025-08-10

-- تنظيف البيانات التالفة أولاً (إن وجدت)
-- حذف المعاملات لعملاء غير موجودين
DELETE t FROM transactions t 
LEFT JOIN clients c ON t.client_id = c.id 
WHERE t.client_id IS NOT NULL AND c.id IS NULL;

-- حذف الشحنات لعملاء غير موجودين  
DELETE l FROM loadings l
LEFT JOIN clients c ON l.client_id = c.id
WHERE l.client_id IS NOT NULL AND c.id IS NULL;

-- إضافة Foreign Keys لجدول transactions
ALTER TABLE transactions 
ADD CONSTRAINT fk_transactions_client 
FOREIGN KEY (client_id) REFERENCES clients(id) 
ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE transactions
ADD CONSTRAINT fk_transactions_loading
FOREIGN KEY (loading_id) REFERENCES loadings(id)
ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE transactions
ADD CONSTRAINT fk_transactions_parent
FOREIGN KEY (parent_transaction_id) REFERENCES transactions(id)
ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE transactions
ADD CONSTRAINT fk_transactions_type
FOREIGN KEY (transaction_type_id) REFERENCES transaction_types(id)
ON DELETE RESTRICT ON UPDATE CASCADE;

-- إضافة Foreign Keys لجدول loadings
ALTER TABLE loadings
ADD CONSTRAINT fk_loadings_client
FOREIGN KEY (client_id) REFERENCES clients(id)
ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE loadings
ADD CONSTRAINT fk_loadings_created_by
FOREIGN KEY (created_by) REFERENCES users(id)
ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE loadings
ADD CONSTRAINT fk_loadings_bol_issued_by
FOREIGN KEY (bol_issued_by) REFERENCES users(id)
ON DELETE SET NULL ON UPDATE CASCADE;

-- إضافة Foreign Keys لجدول cashbox_movements
ALTER TABLE cashbox_movements
ADD CONSTRAINT fk_cashbox_user
FOREIGN KEY (user_id) REFERENCES users(id)
ON DELETE RESTRICT ON UPDATE CASCADE;

-- إضافة Foreign Keys لجداول التدقيق
ALTER TABLE audit_log
ADD CONSTRAINT fk_audit_user
FOREIGN KEY (user_id) REFERENCES users(id)
ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE financial_audit_log
ADD CONSTRAINT fk_financial_audit_user
FOREIGN KEY (user_id) REFERENCES users(id)
ON DELETE SET NULL ON UPDATE CASCADE;

-- إضافة Foreign Keys لجدول client_balances
ALTER TABLE client_balances
ADD CONSTRAINT fk_client_balances_client
FOREIGN KEY (client_id) REFERENCES clients(id)
ON DELETE CASCADE ON UPDATE CASCADE;