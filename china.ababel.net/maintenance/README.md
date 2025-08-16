# ملفات الصيانة

هذا المجلد يحتوي على أدوات الصيانة والفحص للنظام.

## الملفات المتاحة

### 1. test_system.php
فحص صحة النظام الشامل
```bash
php maintenance/test_system.php
```

### 2. fix_permissions.php
إصلاح صلاحيات الملفات والمجلدات
```bash
php maintenance/fix_permissions.php
```

### 3. backup.php
إنشاء نسخة احتياطية من قاعدة البيانات
```bash
php maintenance/backup.php
```

## المهام الدورية الموصى بها

### يومية:
- فحص السجلات: `tail -f storage/logs/*.log`
- مراجعة المعاملات الحديثة
- فحص أداء النظام

### أسبوعية:
- نسخ احتياطية: `php maintenance/backup.php`
- فحص صحة النظام: `php maintenance/test_system.php`
- مراجعة سجلات الأمان

### شهرية:
- تنظيف السجلات القديمة
- تحديث كلمات المرور
- مراجعة صلاحيات المستخدمين
- تحديث المكتبات: `composer update`

## استكشاف الأخطاء

### خطأ 500:
1. فحص سجلات الأخطاء
2. تشغيل `php maintenance/fix_permissions.php`
3. فحص ملف `.env`

### خطأ قاعدة البيانات:
1. تشغيل `php maintenance/test_system.php`
2. فحص اتصال MySQL
3. التحقق من صلاحيات المستخدم

### بطء في الأداء:
1. فحص الفهارس
2. مراجعة الاستعلامات
3. فحص المساحة المتاحة