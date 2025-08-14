<?php
// check-install.php - Upload this to your public directory and run it
echo "<h1>China Accounting System - Installation Checker</h1>";

// Check PHP version
echo "<h2>1. PHP Version</h2>";
echo "Current PHP Version: " . PHP_VERSION . "<br>";
echo (version_compare(PHP_VERSION, '8.3.0', '>=') ? "✅" : "❌") . " PHP 8.3 or higher<br>";

// Check required directories
echo "<h2>2. Directory Structure</h2>";
$dirs = [
    '/app',
    '/app/Controllers',
    '/app/Models',
    '/app/Views',
    '/app/Views/layouts',
    '/app/Views/auth',
    '/app/Views/errors',
    '/app/Core',
    '/config',
    '/lang',
    '/public',
    '/public/assets',
    '/public/assets/css',
    '/public/assets/js',
    '/storage',
    '/storage/logs',
    '/storage/exports'
];

$baseDir = dirname(__DIR__);
foreach ($dirs as $dir) {
    $fullPath = $baseDir . $dir;
    echo (is_dir($fullPath) ? "✅" : "❌") . " $dir " . (is_dir($fullPath) ? "exists" : "missing") . "<br>";
    if (!is_dir($fullPath)) {
        @mkdir($fullPath, 0755, true);
    }
}

// Check required files
echo "<h2>3. Required Files</h2>";
$files = [
    '/config/app.php',
    '/config/database.php',
    '/app/Core/Database.php',
    '/app/Core/Controller.php',
    '/app/Core/Model.php',
    '/app/Core/Language.php',
    '/app/Controllers/AuthController.php',
    '/app/Models/User.php',
    '/app/Views/auth/login.php',
    '/app/Views/layouts/header.php',
    '/app/Views/layouts/footer.php',
    '/app/Views/errors/404.php',
    '/lang/ar.php',
    '/lang/en.php'
];

foreach ($files as $file) {
    $fullPath = $baseDir . $file;
    echo (file_exists($fullPath) ? "✅" : "❌") . " $file " . (file_exists($fullPath) ? "exists" : "missing") . "<br>";
}

// Check permissions
echo "<h2>4. Directory Permissions</h2>";
$writableDirs = ['/storage', '/storage/logs', '/storage/exports'];
foreach ($writableDirs as $dir) {
    $fullPath = $baseDir . $dir;
    echo (is_writable($fullPath) ? "✅" : "❌") . " $dir " . (is_writable($fullPath) ? "writable" : "not writable") . "<br>";
}

// Check database connection
echo "<h2>5. Database Connection</h2>";
if (file_exists($baseDir . '/config/database.php')) {
    try {
        require_once $baseDir . '/app/Core/Database.php';
        $db = \App\Core\Database::getInstance();
        echo "✅ Database connection successful<br>";
        
        // Check tables
        $requiredTables = ['users', 'clients', 'transactions', 'transaction_types', 'cashbox_movements', 'settings'];
        $pdo = $db->getConnection();
        $stmt = $pdo->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h3>Database Tables:</h3>";
        foreach ($requiredTables as $table) {
            echo (in_array($table, $existingTables) ? "✅" : "❌") . " $table " . (in_array($table, $existingTables) ? "exists" : "missing") . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Database configuration file missing<br>";
}

// Check for common issues
echo "<h2>6. Common Issues Check</h2>";

// Check if session can be started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    echo "✅ Sessions are working<br>";
} else {
    echo "❌ Session already started or sessions not working<br>";
}

// Check if URL rewriting works
echo "✅ Make sure .htaccess is properly configured for URL rewriting<br>";

echo "<h2>7. Create Missing Files</h2>";
echo "<p>Creating any missing configuration files...</p>";

// Create app.php if missing
if (!file_exists($baseDir . '/config/app.php')) {
    $appConfig = "<?php
return [
    'name' => 'China Office Accounting System',
    'url' => 'https://china.ababel.net',
    'timezone' => 'Asia/Shanghai',
    'locale' => 'ar',
    'fallback_locale' => 'en',
    'currencies' => ['RMB', 'USD', 'SDG', 'AED'],
    'default_currency' => 'RMB',
];";
    file_put_contents($baseDir . '/config/app.php', $appConfig);
    echo "✅ Created config/app.php<br>";
}

// Create .htaccess if missing
if (!file_exists($baseDir . '/public/.htaccess')) {
    $htaccess = "RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]";
    file_put_contents($baseDir . '/public/.htaccess', $htaccess);
    echo "✅ Created public/.htaccess<br>";
}

echo "<hr>";
echo "<p><strong>Installation check complete!</strong></p>";
echo "<p>Fix any ❌ issues above and then try accessing the system again.</p>";
echo "<p><a href='/'>Go to Homepage</a> | <a href='/login'>Go to Login</a></p>";