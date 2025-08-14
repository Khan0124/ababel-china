#!/usr/bin/env php
<?php
// Simple migration runner
if (php_sapi_name() !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(1); }

define('BASE_PATH', dirname(__DIR__));

// Autoload
$vendorAutoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) { require_once $vendorAutoload; }

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) { return; }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) { require $file; }
});

require_once BASE_PATH . '/app/Core/helpers.php';

$db = \App\Core\Database::getInstance();
$pdo = $db->getConnection();
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (id INT AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(255) NOT NULL UNIQUE, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$dir = BASE_PATH . '/migrations/sql';
if (!is_dir($dir)) { echo "No migrations found.\n"; exit(0); }

$files = glob($dir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    $stmt = $db->query("SELECT 1 FROM migrations WHERE filename = ?", [$name]);
    if ($stmt->fetchColumn()) {
        echo "Skipped: $name (already applied)\n";
        continue;
    }
    $sql = file_get_contents($file);
    echo "Applying: $name ... ";
    try {
        // Split on semicolon for simple statements. Avoids DELIMITER complexity.
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $pdo->beginTransaction();
        foreach ($statements as $s) {
            if ($s === '' || str_starts_with($s, '--') || str_starts_with($s, '/*')) { continue; }
            $pdo->exec($s);
        }
        $pdo->commit();
        $db->query("INSERT INTO migrations (filename) VALUES (?)", [$name]);
        echo "OK\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "FAILED\n";
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}

echo "All migrations applied.\n";