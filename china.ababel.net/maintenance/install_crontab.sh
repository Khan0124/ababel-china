#!/bin/bash
# تنصيب مهام cron للنسخ الاحتياطي والصيانة

echo "Installing crontab jobs..."

# إنشاء ملف cron مؤقت
cat > /tmp/khxtech_cron << EOF
# النسخ الاحتياطي اليومي في الساعة 2:00 صباحاً
0 2 * * * /usr/bin/php /www/wwwroot/khxtech.xyz/maintenance/auto_backup.php >> /www/wwwroot/khxtech.xyz/logs/cron.log 2>&1

# تنظيف ملفات السجل القديمة كل أسبوع
0 3 * * 0 find /www/wwwroot/khxtech.xyz/logs -name "*.log" -mtime +30 -delete

# فحص الأمان الأسبوعي
0 4 * * 1 grep -r "eval\|exec\|system" /www/wwwroot/khxtech.xyz/app/ >> /www/wwwroot/khxtech.xyz/logs/security_scan.log 2>&1

# تحليل جداول قاعدة البيانات شهرياً
0 5 1 * * /usr/bin/mysql -u khan -p'Khan@70990100' khan -e "ANALYZE TABLE transactions, clients, loadings, cashbox_movements;" >> /www/wwwroot/khxtech.xyz/logs/db_maintenance.log 2>&1
EOF

# تنصيب cron jobs للمستخدم الحالي
crontab /tmp/khxtech_cron

# حذف الملف المؤقت
rm /tmp/khxtech_cron

echo "Crontab jobs installed successfully!"
echo "Active cron jobs:"
crontab -l