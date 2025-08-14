# China Office Accounting System

نظام محاسبي متكامل لإدارة المعاملات المالية والعملاء.

## المميزات

- ✅ إدارة العملاء والمعاملات المالية
- ✅ دعم عملات متعددة (RMB, USD, SDG, AED)
- ✅ نظام دفع متقدم مع معالجة أخطاء شاملة
- ✅ نظام فواتير وإيصالات
- ✅ سجل تدقيق للمعاملات المالية
- ✅ حماية CSRF وتنقية المدخلات
- ✅ Rate limiting للحماية من الهجمات
- ✅ نظام صلاحيات متعدد المستويات
- ✅ تقارير مالية شاملة
- ✅ API للتكامل مع أنظمة خارجية

## المتطلبات

- PHP 8.3+
- MySQL 5.7+ أو MariaDB 10.3+
- Composer
- Extensions: PDO, mbstring, json

## التثبيت

1. استنساخ المشروع:
```bash
git clone [repository-url]
cd khxtech.xyz
```

2. تثبيت المكتبات:
```bash
composer install
```

3. إعداد ملف البيئة:
```bash
cp .env.example .env
```

4. تحديث إعدادات قاعدة البيانات في `.env`:
```
DB_HOST=localhost
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. تنفيذ migrations:
```bash
php migrate.php
```

## البنية

```
├── app/
│   ├── Controllers/     # Controllers
│   ├── Core/            # Core classes
│   ├── Models/          # Models
│   ├── Services/        # Business logic services
│   └── Views/           # View templates
├── config/              # Configuration files
├── migrations/          # Database migrations
├── public/              # Public directory
│   ├── assets/          # CSS, JS, images
│   └── index.php        # Entry point
├── storage/             # Storage directory
│   ├── logs/            # Application logs
│   ├── exports/         # Exported files
│   └── invoices/        # Generated invoices
└── vendor/              # Composer dependencies
```

## الأمان

### التحسينات المنفذة:

1. **متغيرات البيئة**: جميع البيانات الحساسة في ملف `.env`
2. **حماية CSRF**: حماية تلقائية لجميع النماذج
3. **تنقية المدخلات**: Validator class للتحقق من البيانات
4. **Rate Limiting**: حماية من هجمات brute force
5. **سجل التدقيق**: تتبع جميع العمليات المالية
6. **التشفير**: استخدام password_hash لكلمات المرور

### أفضل الممارسات:

- تغيير كلمات المرور الافتراضية
- تفعيل HTTPS
- تحديث المكتبات بانتظام
- نسخ احتياطي دوري للبيانات
- مراجعة سجلات الأمان

## API

### المصادقة:
```
POST /api/auth
{
  "username": "user",
  "password": "pass"
}
```

### نقاط النهاية:
- `GET /api/transactions` - قائمة المعاملات
- `POST /api/transactions` - إنشاء معاملة
- `GET /api/clients` - قائمة العملاء
- `POST /api/payments` - معالجة دفعة

## التطوير

### تشغيل الخادم المحلي:
```bash
php -S localhost:8000 -t public
```

### تشغيل الاختبارات:
```bash
composer test
```

## الصيانة

### تنظيف السجلات:
```bash
php maintenance/clean-logs.php
```

### النسخ الاحتياطي:
```bash
php maintenance/backup.php
```

## المساهمة

1. Fork the project
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create Pull Request

## الترخيص

حقوق الطبع محفوظة © 2024

## الدعم

للمساعدة والدعم: support@khxtech.xyz