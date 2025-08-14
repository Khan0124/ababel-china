<?php
/**
 * سكريبت اختبار شامل لتحديثات نظام China Ababel
 * 
 * الاستخدام:
 * php test_updates.php
 */

// تعيين منطقة زمنية
date_default_timezone_set('Asia/Shanghai');

// إعدادات
define('BASE_PATH', __DIR__);
require_once(__DIR__ . '/../app/Core/Database.php');

echo "========================================\n";
echo "اختبار تحديثات نظام China Ababel\n";
echo "التاريخ: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

$results = [];
$totalTests = 0;
$passedTests = 0;

// دالة مساعدة لطباعة النتائج
function printResult($test, $passed, $details = '') {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($passed) {
        $passedTests++;
        echo "✅ ";
    } else {
        echo "❌ ";
    }
    echo $test;
    if ($details) {
        echo " - $details";
    }
    echo "\n";
}

try {
    $db = \App\Core\Database::getInstance();
    echo "✅ الاتصال بقاعدة البيانات نجح\n\n";
} catch (\Exception $e) {
    die("❌ فشل الاتصال بقاعدة البيانات: " . $e->getMessage() . "\n");
}

// ========================================
// 1. اختبار قاعدة البيانات
// ========================================
echo "1. اختبار تحديثات قاعدة البيانات\n";
echo "-----------------------------------\n";

// اختبار حقول BOL في جدول loadings
try {
    $stmt = $db->query("DESCRIBE loadings");
    $columns = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
    
    $bolFields = ['bill_of_lading_status', 'bill_of_lading_date', 'bill_of_lading_file'];
    foreach ($bolFields as $field) {
        printResult("حقل $field في جدول loadings", in_array($field, $columns));
    }
} catch (\Exception $e) {
    printResult("فحص حقول BOL", false, $e->getMessage());
}

// اختبار حقول إضافية في transactions
try {
    $stmt = $db->query("DESCRIBE transactions");
    $columns = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
    
    $additionalFields = ['notes', 'attachment'];
    foreach ($additionalFields as $field) {
        printResult("حقل $field في جدول transactions", in_array($field, $columns));
    }
} catch (\Exception $e) {
    printResult("فحص حقول transactions", false, $e->getMessage());
}

// اختبار الجداول الجديدة
$newTables = ['notifications', 'activity_log'];
foreach ($newTables as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        printResult("جدول $table", $stmt->rowCount() > 0);
    } catch (\Exception $e) {
        printResult("جدول $table", false, $e->getMessage());
    }
}

// اختبار Stored Procedures
$procedures = ['approve_transaction', 'update_loading_bol', 'calculate_client_balance'];
foreach ($procedures as $proc) {
    try {
        $stmt = $db->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE() AND Name = '$proc'");
        printResult("Stored Procedure $proc", $stmt->rowCount() > 0);
    } catch (\Exception $e) {
        printResult("Stored Procedure $proc", false, $e->getMessage());
    }
}

// اختبار Triggers
$triggers = ['log_transaction_changes', 'log_loading_changes'];
foreach ($triggers as $trigger) {
    try {
        $stmt = $db->query("SHOW TRIGGERS WHERE `Trigger` = '$trigger'");
        printResult("Trigger $trigger", $stmt->rowCount() > 0);
    } catch (\Exception $e) {
        printResult("Trigger $trigger", false, $e->getMessage());
    }
}

// ========================================
// 2. اختبار الملفات
// ========================================
echo "\n2. اختبار ملفات PHP\n";
echo "-----------------------------------\n";

// اختبار وجود الملفات الأساسية
$requiredFiles = [
    'public/index.php' => 'ملف الدخول الرئيسي',
    'app/Controllers/LoadingController.php' => 'متحكم التحميلات',
    'app/Controllers/TransactionController.php' => 'متحكم المعاملات',
    'app/Models/Loading.php' => 'نموذج التحميلات',
    'app/Services/SyncService.php' => 'خدمة المزامنة',
    'app/Views/transactions/approve.php' => 'صفحة الموافقة على المعاملات'
];

foreach ($requiredFiles as $file => $description) {
    printResult($description, file_exists(BASE_PATH . '/' . $file));
}

// اختبار محتوى الملفات
// اختبار routing في index.php
if (file_exists(BASE_PATH . '/public/index.php')) {
    $content = file_get_contents(BASE_PATH . '/public/index.php');
    printResult("routing للموافقة على المعاملات", 
        strpos($content, 'TransactionController@showApprove') !== false &&
        strpos($content, 'TransactionController@approve') !== false
    );
}

// اختبار وجود دالة updateBolStatus في Loading Model
if (file_exists(BASE_PATH . '/app/Models/Loading.php')) {
    $content = file_get_contents(BASE_PATH . '/app/Models/Loading.php');
    printResult("دالة updateBolStatus في Loading Model", 
        strpos($content, 'function updateBolStatus') !== false
    );
}

// ========================================
// 3. اختبار مجلدات الرفع
// ========================================
echo "\n3. اختبار مجلدات الرفع\n";
echo "-----------------------------------\n";

$uploadDirs = [
    'public/uploads/bol' => 'مجلد رفع ملفات BOL',
    'public/uploads/transactions' => 'مجلد رفع مرفقات المعاملات'
];

foreach ($uploadDirs as $dir => $description) {
    $fullPath = BASE_PATH . '/' . $dir;
    $exists = is_dir($fullPath);
    $writable = $exists ? is_writable($fullPath) : false;
    
    printResult($description . " - موجود", $exists);
    if ($exists) {
        printResult($description . " - قابل للكتابة", $writable);
    }
}

// ========================================
// 4. اختبار ملفات اللغة
// ========================================
echo "\n4. اختبار ملفات اللغة\n";
echo "-----------------------------------\n";

$langFiles = [
    'ar' => BASE_PATH . '/lang/ar.php',
    'en' => BASE_PATH . '/lang/en.php'
];

$requiredMessages = [
    'messages.error_issuing_bol',
    'bol.bill_of_lading',
    'sync.sync_pending',
    'loadings.financial_details'
];

foreach ($langFiles as $lang => $file) {
    if (file_exists($file)) {
        $langData = include $file;
        printResult("ملف اللغة $lang موجود", true);
        
        foreach ($requiredMessages as $key) {
            $keys = explode('.', $key);
            $value = $langData;
            $found = true;
            
            foreach ($keys as $k) {
                if (isset($value[$k])) {
                    $value = $value[$k];
                } else {
                    $found = false;
                    break;
                }
            }
            
            printResult("  - مفتاح $key", $found);
        }
    } else {
        printResult("ملف اللغة $lang", false);
    }
}

// ========================================
// 5. اختبار الوظائف
// ========================================
echo "\n5. اختبار الوظائف\n";
echo "-----------------------------------\n";

// اختبار إنشاء معاملة
try {
    $db->beginTransaction();
    
    // إنشاء معاملة اختبارية
    $stmt = $db->query(
        "INSERT INTO transactions (transaction_no, client_id, transaction_type_id, 
         transaction_date, description, status, created_by) 
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            'TEST-' . time(),
            1, // assuming client ID 1 exists
            1, // assuming transaction type 1 exists
            date('Y-m-d'),
            'Test transaction for update verification',
            'pending',
            1 // assuming user ID 1 exists
        ]
    );
    
    $testTransactionId = $db->getConnection()->lastInsertId();
    printResult("إنشاء معاملة اختبارية", $testTransactionId > 0);
    
    // اختبار وجود إجراء الموافقة
    $stmt = $db->query("CALL approve_transaction(?, ?)", [$testTransactionId, 1]);
    $result = $stmt->fetch();
    printResult("تنفيذ إجراء approve_transaction", 
        isset($result['status']) && $result['status'] === 'SUCCESS'
    );
    
    $db->rollBack();
} catch (\Exception $e) {
    $db->rollBack();
    printResult("اختبار الوظائف", false, $e->getMessage());
}

// ========================================
// 6. اختبار إعدادات API
// ========================================
echo "\n6. اختبار إعدادات API\n";
echo "-----------------------------------\n";

$apiSettings = [
    'port_sudan_api_url' => 'رابط API',
    'port_sudan_api_key' => 'مفتاح API'
];

foreach ($apiSettings as $key => $description) {
    try {
        $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        $result = $stmt->fetch();
        printResult($description, !empty($result['setting_value']));
    } catch (\Exception $e) {
        printResult($description, false, $e->getMessage());
    }
}

// ========================================
// النتيجة النهائية
// ========================================
echo "\n========================================\n";
echo "النتيجة النهائية\n";
echo "========================================\n";
echo "عدد الاختبارات: $totalTests\n";
echo "نجح: $passedTests\n";
echo "فشل: " . ($totalTests - $passedTests) . "\n";
echo "نسبة النجاح: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";

if ($passedTests === $totalTests) {
    echo "✅ جميع الاختبارات نجحت! النظام جاهز للاستخدام.\n";
} else {
    echo "❌ بعض الاختبارات فشلت. يرجى مراجعة الأخطاء أعلاه.\n";
    echo "\nنصائح لحل المشاكل:\n";
    
    if ($totalTests - $passedTests > 10) {
        echo "- يبدو أن معظم التحديثات لم تُطبق. تأكد من تنفيذ سكريبت SQL.\n";
    }
    
    echo "- تحقق من صلاحيات المستخدم على قاعدة البيانات.\n";
    echo "- تأكد من نسخ جميع الملفات المحدثة.\n";
    echo "- راجع سجلات الأخطاء للمزيد من التفاصيل.\n";
}

// ========================================
// معلومات النظام
// ========================================
echo "\n========================================\n";
echo "معلومات النظام\n";
echo "========================================\n";
echo "PHP Version: " . phpversion() . "\n";
echo "MySQL Version: ";
try {
    $stmt = $db->query("SELECT VERSION() as version");
    $result = $stmt->fetch();
    echo $result['version'] . "\n";
} catch (\Exception $e) {
    echo "غير متاح\n";
}
echo "المسار: " . BASE_PATH . "\n";
echo "المستخدم الحالي: " . get_current_user() . "\n";

echo "\n========================================\n";
echo "انتهى الاختبار\n";
echo "========================================\n";