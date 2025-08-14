# إصلاح مشكلة nginx في khxtech.xyz

## المشكلة
nginx لا يوجه الطلبات بشكل صحيح إلى PHP، مما يؤدي إلى عدم عمل الروابط مثل `/clients`.

## الحل

### 1. تحديث تكوين nginx
أضف التكوين التالي إلى ملف nginx للموقع:

```nginx
server {
    listen 80;
    server_name khxtech.xyz;
    root /www/wwwroot/khxtech.xyz/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }
}
```

### 2. الحل المؤقت
تم إنشاء ملف `clients.php` يوجه إلى الراوتر الصحيح.

### 3. تحديث الراوتر
تم تحديث `index.php` لدعم معاملات URL عبر `?uri=`.

## اختبار الحل
```bash
curl http://localhost/index.php?uri=clients
```

## ملاحظات
- يجب تطبيق التكوين على خادم nginx
- قد تحتاج إعادة تشغيل nginx بعد التحديث
- التحقق من مسار PHP-FPM socket