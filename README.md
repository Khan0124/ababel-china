# نظام محاسبة مكتب الصين - China Office Accounting System

## نظرة عامة | Overview

نظام محاسبة شامل لإدارة العمليات المالية والتجارية لمكتب الصين، يدعم العملات المتعددة ويوفر إدارة متكاملة للعملاء والمعاملات المالية والخزينة مع مزامنة مع النظام الرئيسي في بورتسودان.

A comprehensive accounting system for managing financial and commercial operations for the China Office, supporting multiple currencies and providing integrated management of clients, financial transactions, and cashbox operations with synchronization to the main system in Port Sudan.

## الميزات الرئيسية | Key Features

### 📊 إدارة العملاء | Client Management
- تسجيل وإدارة بيانات العملاء باللغتين العربية والإنجليزية
- تتبع أرصدة العملاء بالعملات المختلفة (RMB, USD, SDG, AED)
- كشوف حسابات تفصيلية للعملاء مع تحليل المخاطر
- نظام ترقيم تلقائي للعملاء

### 💰 إدارة المعاملات المالية | Financial Transactions
- إنشاء وإدارة المعاملات المالية المختلفة
- دعم العملات المتعددة (يوان صيني، دولار أمريكي، جنيه سوداني، درهم إماراتي)
- نظام الدفعات الجزئية المتقدم
- تتبع حالة المعاملات والموافقات
- ربط المعاملات بالشحنات والفواتير

### 🏦 إدارة الخزينة | Cashbox Management
- تتبع أرصدة الخزينة بالعملات المختلفة
- حركات الخزينة (إيداع/سحب) مع التدقيق
- تحويل العملات مع تتبع أسعار الصرف الحية
- تقارير يومية وشهرية للخزينة

### 🚢 إدارة الشحنات | Loading Management
- تسجيل وإدارة بيانات الشحنات
- إصدار بوليصة الشحن (Bill of Loading)
- تتبع حالة الشحنات ومواعيد التسليم
- مزامنة تلقائية مع النظام الرئيسي في بورتسودان

### 📈 التقارير والإحصائيات | Reports & Analytics
- تقارير يومية وشهرية شاملة
- تحليل الأداء المالي المتقدم
- كشوف حسابات العملاء التفصيلية
- تقارير الخزينة والسيولة
- إحصائيات المعاملات غير المدفوعة وتحليل المخاطر

### 🔄 مزامنة البيانات | Data Synchronization
- مزامنة تلقائية مع النظام الرئيسي في بورتسودان
- نظام webhook للتحديثات الفورية
- تتبع حالة المزامنة ومعالجة الأخطاء
- إعادة المحاولة التلقائية للعمليات الفاشلة

### 👥 إدارة المستخدمين | User Management
- نظام صلاحيات متدرج (Admin, Manager, Accountant, User)
- تتبع نشاط المستخدمين وسجل العمليات
- إدارة كلمات المرور والأمان المتقدم
- سجل التدقيق الشامل للعمليات

### 🔧 مراقبة النظام | System Monitoring
- مراقبة أداء النظام في الوقت الفعلي
- سجلات الأخطاء والتنبيهات التلقائية
- نسخ احتياطية تلقائية مجدولة
- تحسين قاعدة البيانات والفهرسة

### 💱 إدارة أسعار الصرف | Exchange Rate Management
- تحديث أسعار الصرف التلقائي والحقيقي
- حاسبة تحويل العملات المتقدمة
- تتبع تاريخ أسعار الصرف
- تقارير ربحية الصرف

## متطلبات النظام | System Requirements

### خادم الويب | Web Server
- **PHP**: 8.3+
- **MySQL/MariaDB**: 5.7+ / 10.3+
- **Nginx/Apache**: أي إصدار حديث
- **Extensions**: PDO, mbstring, JSON, curl

### المكتبات المطلوبة | Required Libraries
```json
{
    "phpmailer/phpmailer": "^6.8",
    "mpdf/mpdf": "^8.1",
    "phpoffice/phpspreadsheet": "^1.28"
}
```

### متطلبات الأداء | Performance Requirements
- **ذاكرة**: 512MB كحد أدنى، 1GB مستحسن
- **مساحة القرص**: 5GB كحد أدنى للتشغيل والنسخ الاحتياطية
- **اتصال الإنترنت**: مطلوب للمزامنة مع بورتسودان

## التثبيت والإعداد | Installation & Setup

### 1. تحميل المشروع | Download Project
```bash
git clone https://github.com/ababel/china-office-system.git
cd china-office-system
```

### 2. تثبيت المكتبات | Install Dependencies
```bash
composer install
```

### 3. إعداد قاعدة البيانات | Database Setup
```bash
# إنشاء قاعدة البيانات
mysql -u root -p -e "CREATE DATABASE china_ababel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# استيراد الهيكل الأساسي
mysql -u root -p china_ababel < china_ababel.sql

# تطبيق التحديثات الأمنية والأداء
mysql -u root -p china_ababel < migrations/2025_08_16_security_and_optimization.sql
```

### 4. إعداد متغيرات البيئة | Environment Configuration
```bash
# إنشاء ملف البيئة
cp .env.example .env

# تحرير الإعدادات
nano .env
```

**إعدادات مطلوبة في .env:**
```env
# إعدادات قاعدة البيانات
DB_HOST=localhost
DB_DATABASE=china_ababel
DB_USERNAME=your_username
DB_PASSWORD=your_secure_password

# إعدادات التطبيق
APP_NAME="China Office Accounting System"
APP_URL=https://china.ababel.net
APP_TIMEZONE=Asia/Shanghai
DEFAULT_CURRENCY=RMB

# إعدادات المزامنة مع بورتسودان
PORT_SUDAN_API_URL=https://ababel.net/app/api/china_sync.php
PORT_SUDAN_API_KEY=your_api_key
WEBHOOK_API_KEY=your_webhook_key
```

### 5. إعداد الصلاحيات | Set Permissions
```bash
chmod -R 755 storage/ cache/ logs/
chown -R www-data:www-data storage/ cache/ logs/
```

### 6. إنشاء مستخدم إداري | Create Admin User
```bash
php create-admin.php
```

### 7. إعداد النسخ الاحتياطية التلقائية | Setup Automatic Backups
```bash
# تثبيت مهام cron
cd maintenance/
chmod +x install_crontab.sh
./install_crontab.sh
```

## البنية المعمارية | System Architecture

### نموذج MVC | MVC Pattern
```
app/
├── Controllers/         # وحدات التحكم
│   ├── Api/            # واجهات برمجة التطبيقات
│   ├── AuthController.php
│   ├── ClientController.php
│   ├── TransactionController.php
│   ├── CashboxController.php
│   ├── LoadingController.php
│   ├── ReportController.php
│   └── SystemMonitorController.php
├── Core/               # النواة الأساسية
│   ├── Controller.php  # الوحدة الأساسية للتحكم
│   ├── Model.php      # النموذج الأساسي
│   ├── Router.php     # موجه الطلبات
│   ├── Database.php   # إدارة قاعدة البيانات
│   ├── Cache.php      # نظام التخزين المؤقت
│   └── Security/      # أمان النظام
├── Models/             # نماذج البيانات
│   ├── Client.php     # نموذج العملاء
│   ├── Transaction.php # نموذج المعاملات
│   ├── Cashbox.php    # نموذج الخزينة
│   └── Loading.php    # نموذج الشحنات
├── Services/           # الخدمات المساعدة
│   ├── SyncService.php     # خدمة المزامنة
│   ├── PaymentService.php  # خدمة المدفوعات
│   ├── ReportPdfService.php # خدمة التقارير
│   └── ExcelExportService.php # خدمة التصدير
└── Views/              # واجهات المستخدم
    ├── dashboard/      # لوحة التحكم
    ├── clients/       # إدارة العملاء
    ├── transactions/  # إدارة المعاملات
    ├── cashbox/       # إدارة الخزينة
    ├── reports/       # التقارير
    └── layouts/       # التخطيطات العامة
```

### هيكل المشروع | Project Structure
```
├── config/              # ملفات الإعداد
│   ├── app.php         # إعدادات التطبيق
│   ├── database.php    # إعدادات قاعدة البيانات
│   └── routes.php      # إعدادات المسارات
├── migrations/          # ترحيل قاعدة البيانات
│   ├── sql/           # ملفات SQL الأساسية
│   └── *.sql          # تحديثات قاعدة البيانات
├── public/              # المجلد العام
│   ├── assets/        # الموارد (CSS, JS, Images)
│   └── index.php      # نقطة الدخول الرئيسية
├── storage/             # مجلد التخزين
│   ├── logs/          # سجلات النظام
│   ├── exports/       # الملفات المصدرة
│   ├── backups/       # النسخ الاحتياطية
│   └── cache/         # ملفات التخزين المؤقت
├── maintenance/         # أدوات الصيانة
│   ├── backup.php     # النسخ الاحتياطي
│   ├── test_system.php # فحص النظام
│   └── auto_backup.php # النسخ التلقائي
└── vendor/              # مكتبات Composer
```

## قاعدة البيانات | Database Schema

### الجداول الرئيسية | Main Tables

#### جدول العملاء | Clients Table
```sql
clients (
    id, name, name_ar, client_code, phone, email, address,
    balance_rmb, balance_usd, balance_sdg, balance_aed,
    credit_limit, risk_level, status, created_at, updated_at
)
```

#### جدول المعاملات | Transactions Table
```sql
transactions (
    id, transaction_no, client_id, transaction_type_id,
    amount_rmb, amount_usd, amount_sdg, amount_aed,
    payment_rmb, payment_usd, payment_sdg, payment_aed,
    description, bank_name, loading_id, status,
    created_by, approved_by, transaction_date, created_at
)
```

#### جدول حركات الخزينة | Cashbox Movements Table
```sql
cashbox_movements (
    id, transaction_id, movement_type, category,
    amount_rmb, amount_usd, amount_sdg, amount_aed,
    description, movement_date, created_by, created_at
)
```

#### جدول الشحنات | Loadings Table
```sql
loadings (
    id, loading_no, client_id, container_no, seal_no,
    goods_description, quantity, weight, cbm,
    loading_date, eta_date, status, office,
    sync_status, sync_attempts, bol_issued
)
```

### الفهارس والأداء | Indexes & Performance
- فهارس محسنة للبحث السريع
- فهارس مركبة للاستعلامات المعقدة
- تحسين استعلامات التقارير
- فهرسة حقول التاريخ والحالة

## الأمان | Security Features

### الحماية المتقدمة | Advanced Protection

#### 🔐 المصادقة والترخيص | Authentication & Authorization
- نظام صلاحيات متدرج (Admin, Manager, Accountant, User)
- تشفير كلمات المرور بـ bcrypt
- جلسات آمنة مع انتهاء صلاحية
- تتبع محاولات تسجيل الدخول الفاشلة

#### 🛡️ حماية CSRF | CSRF Protection
- رموز CSRF لجميع النماذج
- تحقق تلقائي من الطلبات
- انتهاء صلاحية الرموز المؤقتة

#### ⚡ تحديد معدل الطلبات | Rate Limiting
- حماية من الهجمات المكثفة
- حدود مختلفة حسب نوع العملية
- حظر مؤقت للعناوين المشبوهة

#### 🔍 تنقية المدخلات | Input Sanitization
- فلترة جميع المدخلات
- حماية من هجمات SQL Injection
- تنظيف البيانات قبل العرض
- التحقق من صحة البيانات

#### 📊 سجل التدقيق | Audit Trail
- تتبع جميع العمليات المالية
- سجل تفصيلي لنشاط المستخدمين
- تسجيل التغييرات الحساسة
- إنذارات الأمان

### أفضل الممارسات الأمنية | Security Best Practices

#### للمطورين | For Developers
- استخدام متغيرات البيئة للبيانات الحساسة
- تشفير جميع الاتصالات (HTTPS)
- تحديث المكتبات بانتظام
- مراجعة الكود للثغرات الأمنية

#### للمديرين | For Administrators
- تغيير كلمات المرور الافتراضية
- مراجعة سجلات الأمان دورياً
- تفعيل النسخ الاحتياطية التلقائية
- تحديث النظام بانتظام

#### للمستخدمين | For Users
- استخدام كلمات مرور قوية
- تسجيل الخروج عند انتهاء العمل
- عدم مشاركة بيانات تسجيل الدخول
- الإبلاغ عن النشاط المشبوه

## واجهة برمجة التطبيقات | API Documentation

### مصادقة API | API Authentication
```http
POST /api/auth
Content-Type: application/json

{
  "username": "your_username",
  "password": "your_password"
}
```

**استجابة ناجحة | Successful Response:**
```json
{
  "success": true,
  "token": "jwt_token_here",
  "user": {
    "id": 1,
    "username": "admin",
    "role": "admin"
  }
}
```

### نقاط النهاية الرئيسية | Main Endpoints

#### 👥 إدارة العملاء | Clients API
```http
GET /api/clients              # قائمة العملاء
POST /api/clients            # إضافة عميل جديد
PUT /api/clients/{id}        # تحديث عميل
DELETE /api/clients/{id}     # حذف عميل
GET /clients/statement/{id}   # كشف حساب العميل
```

#### 💰 إدارة المعاملات | Transactions API
```http
GET /api/transactions              # قائمة المعاملات
POST /api/transactions            # إنشاء معاملة جديدة
GET /transactions/view/{id}        # تفاصيل المعاملة
POST /transactions/approve/{id}    # الموافقة على المعاملة
POST /transactions/partial-payment/{id}  # دفعة جزئية
```

#### 🏦 إدارة الخزينة | Cashbox API
```http
GET /cashbox                      # حالة الخزينة
POST /cashbox/movement           # حركة خزينة
GET /cashbox/history             # تاريخ الحركات
POST /cashbox/currency-conversion/execute  # تحويل عملة
```

#### 🔄 مزامنة البيانات | Sync API
```http
POST /api/sync/webhook           # استقبال webhook
GET /api/sync/status/{id}        # حالة المزامنة
POST /api/sync/retry/{id}        # إعادة محاولة المزامنة
POST /api/sync/all               # مزامنة شاملة
```

### معايير API | API Standards

#### رؤوس الطلبات | Request Headers
```http
Content-Type: application/json
Authorization: Bearer {jwt_token}
X-CSRF-Token: {csrf_token}
```

#### أكواد الاستجابة | Response Codes
- `200` - نجحت العملية
- `201` - تم الإنشاء بنجاح
- `400` - خطأ في البيانات المرسلة
- `401` - غير مخول
- `403` - ممنوع
- `404` - غير موجود
- `429` - تجاوز الحد المسموح
- `500` - خطأ خادم

#### تحديد معدل الطلبات | Rate Limiting
- `/api/sync/*`: 30 طلب/دقيقة
- `/api/auth`: 10 طلبات/دقيقة
- `/api/*`: 60 طلب/دقيقة

## التطوير والاختبار | Development & Testing

### بيئة التطوير | Development Environment

#### تشغيل الخادم المحلي | Local Server
```bash
# تشغيل خادم التطوير
php -S localhost:8000 -t public

# أو باستخدام nginx/apache مع التكوين المناسب
sudo systemctl start nginx
sudo systemctl start php8.3-fpm
```

#### تكوين بيئة التطوير | Development Configuration
```bash
# تفعيل وضع التطوير
echo "APP_DEBUG=true" >> .env
echo "APP_ENV=development" >> .env

# تفعيل سجلات مفصلة
echo "LOG_LEVEL=debug" >> .env
```

### الاختبارات | Testing

#### اختبار النظام | System Testing
```bash
# فحص حالة النظام
php maintenance/test_system.php

# اختبار الاتصال بقاعدة البيانات
php maintenance/test_database.php

# اختبار المزامنة
php maintenance/test_sync.php
```

#### اختبار الأداء | Performance Testing
```bash
# اختبار سرعة الاستجابة
curl -w "@curl-format.txt" -o /dev/null -s "http://localhost:8000"

# اختبار تحميل قاعدة البيانات
mysql china_ababel < maintenance/performance_test.sql
```

### أدوات التطوير | Development Tools

#### تتبع الأخطاء | Error Tracking
```bash
# مراقبة السجلات في الوقت الفعلي
tail -f logs/error.log

# فحص الأخطاء الحرجة
grep -i "critical\|fatal" logs/error.log
```

#### تحسين الأداء | Performance Optimization
```bash
# تنظيف التخزين المؤقت
php maintenance/clear_cache.php

# تحسين قاعدة البيانات
php maintenance/optimize_database.php

# ضغط الأصول
php maintenance/minify_assets.php
```

## الصيانة والمراقبة | Maintenance & Monitoring

### النسخ الاحتياطية | Backup System

#### النسخ التلقائية | Automated Backups
```bash
# إعداد النسخ الاحتياطية التلقائية (crontab)
0 2 * * * /path/to/project/maintenance/auto_backup_cron.sh

# فحص حالة النسخ الاحتياطية
php maintenance/backup_status.php
```

#### النسخ اليدوية | Manual Backups
```bash
# إنشاء نسخة احتياطية كاملة
php maintenance/backup.php

# نسخة احتياطية لقاعدة البيانات فقط
php maintenance/backup_database.php

# نسخة احتياطية للملفات فقط
php maintenance/backup_files.php
```

#### استعادة النسخ | Restore Backups
```bash
# استعادة من نسخة احتياطية
php maintenance/restore.php --file=backup_2025-08-16.tar.gz

# استعادة قاعدة البيانات فقط
mysql china_ababel < storage/backups/db_backup_2025-08-16.sql
```

### صيانة النظام | System Maintenance

#### تنظيف السجلات | Log Cleanup
```bash
# تنظيف السجلات القديمة
php maintenance/clean_logs.php

# أرشفة السجلات
php maintenance/archive_logs.php

# دوران السجلات
./maintenance/log_rotation.sh
```

#### تحسين قاعدة البيانات | Database Optimization
```bash
# تحسين الجداول
php maintenance/optimize_tables.php

# إعادة بناء الفهارس
php maintenance/rebuild_indexes.php

# تنظيف البيانات المؤقتة
php maintenance/cleanup_temp_data.php
```

#### مراقبة الأداء | Performance Monitoring
```bash
# مراقبة استخدام الذاكرة
php maintenance/memory_monitor.php

# مراقبة استخدام القرص
df -h | grep china_ababel

# مراقبة عمليات قاعدة البيانات
mysql -e "SHOW PROCESSLIST;"
```

### سجلات النظام | System Logs

#### أنواع السجلات | Log Types
- `logs/error.log` - سجل الأخطاء العام
- `logs/error_monitor.log` - مراقب الأخطاء
- `logs/critical_alerts.log` - التنبيهات الحرجة
- `logs/sync.log` - سجل المزامنة
- `logs/audit.log` - سجل التدقيق

#### مراقبة السجلات | Log Monitoring
```bash
# مراقبة الأخطاء الحديثة
tail -f logs/error.log

# البحث عن أخطاء محددة
grep -i "database" logs/error.log

# إحصائيات الأخطاء
php maintenance/error_statistics.php
```

## التطوير والمساهمة | Development & Contributing

### معايير الكود | Code Standards

#### PSR Standards
- **PSR-4**: التحميل التلقائي للكلاسات
- **PSR-12**: معايير كتابة الكود
- **PSR-3**: واجهة السجلات

#### معايير المشروع | Project Standards
```php
<?php
// مثال على تنسيق الكود المعتمد
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Client;

class ClientController extends Controller
{
    /**
     * عرض قائمة العملاء
     * Display clients list
     */
    public function index(): void
    {
        $clients = (new Client())->all();
        $this->view('clients/index', compact('clients'));
    }
}
```

### إضافة ميزات جديدة | Adding New Features

#### سير العمل | Workflow
1. **إنشاء فرع جديد** من `main`
   ```bash
   git checkout -b feature/new-feature-name
   ```

2. **تطوير الميزة** مع الاختبارات
   ```bash
   # إضافة الكود الجديد
   # كتابة الاختبارات
   php maintenance/test_feature.php
   ```

3. **تحديث الوثائق**
   ```bash
   # تحديث README.md
   # إضافة تعليقات للكود
   # تحديث ملفات المساعدة
   ```

4. **اختبار شامل**
   ```bash
   php maintenance/test_system.php
   php maintenance/test_security.php
   ```

5. **إرسال طلب دمج**
   ```bash
   git add .
   git commit -m "feat: Add new feature description"
   git push origin feature/new-feature-name
   ```

### إرشادات المساهمة | Contribution Guidelines

#### أنواع المساهمات | Types of Contributions
- 🐛 **إصلاح الأخطاء** (Bug Fixes)
- ✨ **ميزات جديدة** (New Features)
- 📚 **تحسين الوثائق** (Documentation)
- ⚡ **تحسين الأداء** (Performance)
- 🔒 **تحسينات أمنية** (Security)

#### معايير الكود | Code Guidelines
- استخدام تعليقات باللغتين العربية والإنجليزية
- اختبار جميع الوظائف الجديدة
- اتباع معايير الأمان
- توثيق API للوظائف الجديدة

#### مراجعة الكود | Code Review
```bash
# فحص الكود قبل الإرسال
php maintenance/code_review.php

# فحص الأمان
php maintenance/security_scan.php

# فحص الأداء
php maintenance/performance_check.php
```

## استكشاف الأخطاء | Troubleshooting

### مشاكل شائعة | Common Issues

#### مشاكل المزامنة | Sync Issues
```bash
# فحص حالة المزامنة
tail -f logs/error.log | grep sync

# إعادة تشغيل خدمة المزامنة
php maintenance/restart_sync.php

# فحص الاتصال مع بورتسودان
curl -I https://ababel.net/app/api/china_sync.php
```

#### مشاكل الأداء | Performance Issues
```bash
# تنظيف التخزين المؤقت
rm -rf cache/*
php maintenance/clear_cache.php

# تحسين قاعدة البيانات
mysql china_ababel < migrations/optimize_database_indexes.sql

# فحص استخدام الذاكرة
php maintenance/memory_usage.php
```

#### مشاكل الصلاحيات | Permission Issues
```bash
# إعادة تعيين الصلاحيات
chmod -R 755 storage/ cache/ logs/
chown -R www-data:www-data storage/ cache/ logs/

# فحص صلاحيات قاعدة البيانات
mysql -u root -p -e "SHOW GRANTS FOR 'china_ababel'@'localhost';"
```

#### مشاكل تسجيل الدخول | Login Issues
```bash
# إعادة تعيين كلمة مرور المدير
php maintenance/reset_admin_password.php

# فحص جلسات المستخدمين
php maintenance/check_sessions.php

# تنظيف الجلسات المنتهية
php maintenance/cleanup_sessions.php
```

### رسائل الخطأ الشائعة | Common Error Messages

| الخطأ | السبب | الحل |
|-------|-------|------|
| "Database connection failed" | خطأ في الاتصال بقاعدة البيانات | فحص إعدادات .env |
| "CSRF token mismatch" | انتهاء صلاحية الرمز | إعادة تحميل الصفحة |
| "Permission denied" | صلاحيات خاطئة | فحص صلاحيات الملفات |
| "Sync failed" | مشكلة في المزامنة | فحص الاتصال والAPI |

## الدعم والصيانة | Support & Maintenance

### معلومات الاتصال | Contact Information
- **المطور**: فريق أبابيل التقني
- **البريد الإلكتروني**: support@ababel.net
- **الموقع الرسمي**: https://ababel.net
- **النظام الرئيسي**: https://ababel.net

### ساعات الدعم | Support Hours
- **دعم فني عاجل**: 24/7
- **دعم عام**: الأحد - الخميس، 8:00 ص - 5:00 م
- **صيانة مجدولة**: الجمعة، 2:00 ص - 6:00 ص

### تحديثات النظام | System Updates

#### جدولة التحديثات | Update Schedule
- **تحديثات أمنية**: فورية عند الحاجة
- **تحديثات الميزات**: شهرياً
- **تحديثات الصيانة**: أسبوعياً

#### إشعارات التحديث | Update Notifications
```bash
# فحص التحديثات المتاحة
php maintenance/check_updates.php

# تطبيق التحديثات
php maintenance/apply_updates.php
```

### خطة الطوارئ | Emergency Plan

#### في حالة الأعطال الحرجة | Critical Failures
1. **تفعيل وضع الصيانة**
   ```bash
   touch maintenance.flag
   ```

2. **استعادة آخر نسخة احتياطية**
   ```bash
   php maintenance/emergency_restore.php
   ```

3. **إشعار المستخدمين**
   ```bash
   php maintenance/notify_users.php "النظام تحت الصيانة"
   ```

4. **تطبيق الإصلاحات العاجلة**
   ```bash
   php maintenance/emergency_fix.php
   ```

---

## الترخيص | License

هذا النظام مطور خصيصاً لشركة أبابيل وفروعها. جميع الحقوق محفوظة.

This system is developed specifically for Ababel Company and its branches. All rights reserved.

**© 2025 شركة أبابيل - Ababel Company**

---

**آخر تحديث**: أغسطس 2025  
**إصدار النظام**: 2.1.0  
**حالة النظام**: إنتاج مستقر  
**البيئة**: مكتب الصين - الصين