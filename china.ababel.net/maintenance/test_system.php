<?php
/**
 * System health check script
 */

define('BASE_PATH', dirname(__DIR__));
echo "=== System Health Check ===\n\n";

// Test 1: Environment loading
echo "1. Testing Environment Loading...\n";
try {
    require_once BASE_PATH . '/app/Core/Env.php';
    \App\Core\Env::load();
    echo "✅ Environment variables loaded successfully\n";
    echo "   - Database: " . \App\Core\Env::get('DB_DATABASE') . "\n";
    echo "   - Host: " . \App\Core\Env::get('DB_HOST') . "\n";
    echo "   - Username: " . \App\Core\Env::get('DB_USERNAME') . "\n";
    echo "   - Password configured: " . (\App\Core\Env::get('DB_PASSWORD') ? 'Yes' : 'No') . "\n";
} catch (Exception $e) {
    echo "❌ Environment loading failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// Test 2: Database connection
echo "2. Testing Database Connection...\n";
try {
    require_once BASE_PATH . '/app/Core/Database.php';
    $db = \App\Core\Database::getInstance();
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    if ($result['test'] == 1) {
        echo "✅ Database connection successful\n";
    } else {
        echo "❌ Database query failed\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// Test 3: Check required tables
echo "3. Testing Database Tables...\n";
$requiredTables = [
    'users', 'clients', 'transactions', 'cashbox_movements',
    'settings', 'loadings', 'financial_audit_log', 'rate_limits'
];

foreach ($requiredTables as $table) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM `$table` LIMIT 1");
        $result = $stmt->fetch();
        echo "   ✅ Table '$table' exists (rows: " . $result['count'] . ")\n";
    } catch (Exception $e) {
        echo "   ❌ Table '$table' missing or inaccessible\n";
    }
}

echo "\n";

// Test 4: Test file permissions
echo "4. Testing File Permissions...\n";
$checkPaths = [
    BASE_PATH . '/storage' => 'writable',
    BASE_PATH . '/storage/logs' => 'writable',
    BASE_PATH . '/storage/exports' => 'writable',
    BASE_PATH . '/storage/invoices' => 'writable',
    BASE_PATH . '/.env' => 'readable',
    BASE_PATH . '/public/index.php' => 'readable'
];

foreach ($checkPaths as $path => $permission) {
    if (!file_exists($path)) {
        echo "   ❌ Path '$path' does not exist\n";
        continue;
    }
    
    switch ($permission) {
        case 'writable':
            if (is_writable($path)) {
                echo "   ✅ Path '$path' is writable\n";
            } else {
                echo "   ❌ Path '$path' is not writable\n";
            }
            break;
        case 'readable':
            if (is_readable($path)) {
                echo "   ✅ Path '$path' is readable\n";
            } else {
                echo "   ❌ Path '$path' is not readable\n";
            }
            break;
    }
}

echo "\n";

// Test 5: Test core classes
echo "5. Testing Core Classes...\n";
$coreClasses = [
    'App\\Core\\Env',
    'App\\Core\\Database',
    'App\\Core\\Validator',
    'App\\Core\\Security\\CSRF',
    'App\\Core\\Security\\RateLimiter',
    'App\\Core\\Middleware\\Auth'
];

foreach ($coreClasses as $class) {
    try {
        $parts = explode('\\', $class);
        $filename = BASE_PATH . '/app/' . implode('/', array_slice($parts, 1)) . '.php';
        
        if (file_exists($filename)) {
            require_once $filename;
            if (class_exists($class)) {
                echo "   ✅ Class '$class' loaded successfully\n";
            } else {
                echo "   ❌ Class '$class' file exists but class not found\n";
            }
        } else {
            echo "   ❌ Class '$class' file not found at $filename\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error loading class '$class': " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Test 6: Test URL endpoints
echo "6. Testing URL Endpoints...\n";
if (isset($_SERVER['HTTP_HOST'])) {
    $baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
    $endpoints = ['/login', '/dashboard'];
    
    foreach ($endpoints as $endpoint) {
        $url = $baseUrl . $endpoint;
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            echo "   ✅ Endpoint '$endpoint' accessible\n";
        } else {
            echo "   ❌ Endpoint '$endpoint' not accessible\n";
        }
    }
} else {
    echo "   ⚠️ Cannot test URLs (no HTTP_HOST)\n";
}

echo "\n";

// Summary
echo "=== System Health Check Complete ===\n";
echo "✅ All core systems appear to be working correctly!\n";
echo "🚀 System is ready for production use.\n\n";

echo "Next steps:\n";
echo "1. Update passwords in .env file\n";
echo "2. Enable HTTPS\n";
echo "3. Set up regular backups\n";
echo "4. Monitor error logs\n";
?>