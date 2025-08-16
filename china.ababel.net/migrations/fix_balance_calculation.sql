-- تصحيح منطق حساب أرصدة العملاء
-- تطبيق النظام المحاسبي الاحترافي: الرصيد = المدفوعات - المبيعات

-- تحديث أرصدة العملاء بالـ RMB
UPDATE clients c
SET balance_rmb = (
    -- المدفوعات المستلمة
    SELECT COALESCE(SUM(CASE 
        WHEN tt.type = 'income' THEN t.payment_rmb
        ELSE 0 
    END), 0) 
    FROM transactions t 
    JOIN transaction_types tt ON t.transaction_type_id = tt.id
    WHERE t.client_id = c.id AND t.status = 'approved'
) - (
    -- المبيعات والمصاريف (المبالغ المستحقة على العميل)
    SELECT COALESCE(SUM(CASE 
        WHEN tt.type IN ('expense', 'transfer') THEN t.total_amount_rmb
        ELSE 0 
    END), 0)
    FROM transactions t 
    JOIN transaction_types tt ON t.transaction_type_id = tt.id
    WHERE t.client_id = c.id AND t.status = 'approved'
)
WHERE c.status = 'active';

-- تحديث أرصدة العملاء بالـ USD
UPDATE clients c
SET balance_usd = (
    -- المدفوعات المستلمة USD
    SELECT COALESCE(SUM(CASE 
        WHEN tt.type = 'income' THEN t.payment_usd
        ELSE 0 
    END), 0) 
    FROM transactions t 
    JOIN transaction_types tt ON t.transaction_type_id = tt.id
    WHERE t.client_id = c.id AND t.status = 'approved'
) - (
    -- المبيعات والمصاريف USD
    SELECT COALESCE(SUM(CASE 
        WHEN tt.type IN ('expense', 'transfer') THEN t.shipping_usd
        ELSE 0 
    END), 0)
    FROM transactions t 
    JOIN transaction_types tt ON t.transaction_type_id = tt.id
    WHERE t.client_id = c.id AND t.status = 'approved'
)
WHERE c.status = 'active';

-- تحديث أرصدة العملاء بالـ SDG
UPDATE clients c
SET balance_sdg = (
    -- المدفوعات المستلمة SDG
    SELECT COALESCE(SUM(CASE 
        WHEN tt.type = 'income' THEN t.payment_sdg
        ELSE 0 
    END), 0) 
    FROM transactions t 
    JOIN transaction_types tt ON t.transaction_type_id = tt.id
    WHERE t.client_id = c.id AND t.status = 'approved'
) - (
    -- المبيعات والمصاريف SDG
    SELECT COALESCE(SUM(CASE 
        WHEN tt.type IN ('expense', 'transfer') THEN COALESCE(t.balance_sdg, 0)
        ELSE 0 
    END), 0)
    FROM transactions t 
    JOIN transaction_types tt ON t.transaction_type_id = tt.id
    WHERE t.client_id = c.id AND t.status = 'approved'
)
WHERE c.status = 'active';

-- تحديث أرصدة العملاء بالـ AED
UPDATE clients c
SET balance_aed = (
    -- المدفوعات المستلمة AED
    SELECT COALESCE(SUM(CASE 
        WHEN tt.type = 'income' THEN t.payment_aed
        ELSE 0 
    END), 0) 
    FROM transactions t 
    JOIN transaction_types tt ON t.transaction_type_id = tt.id
    WHERE t.client_id = c.id AND t.status = 'approved'
) - (
    -- المبيعات والمصاريف AED
    SELECT COALESCE(SUM(CASE 
        WHEN tt.type IN ('expense', 'transfer') THEN COALESCE(t.balance_aed, 0)
        ELSE 0 
    END), 0)
    FROM transactions t 
    JOIN transaction_types tt ON t.transaction_type_id = tt.id
    WHERE t.client_id = c.id AND t.status = 'approved'
)
WHERE c.status = 'active';

-- عرض النتائج النهائية
SELECT 
    client_code,
    name,
    CONCAT('¥', FORMAT(balance_rmb, 2)) as 'RMB Balance',
    CONCAT('$', FORMAT(balance_usd, 2)) as 'USD Balance',
    CONCAT('SDG ', FORMAT(balance_sdg, 2)) as 'SDG Balance',
    CONCAT('AED ', FORMAT(balance_aed, 2)) as 'AED Balance',
    CASE 
        WHEN balance_rmb < 0 THEN 'دين على العميل'
        WHEN balance_rmb > 0 THEN 'رصيد فائض للعميل'
        ELSE 'رصيد صفر'
    END as 'Balance Status'
FROM clients 
WHERE status = 'active' 
ORDER BY balance_rmb DESC;