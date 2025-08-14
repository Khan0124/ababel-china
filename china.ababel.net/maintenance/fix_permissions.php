<?php
/**
 * Fix file permissions and create missing directories
 */

define('BASE_PATH', dirname(__DIR__));

echo "Starting maintenance tasks...\n";

// Create missing directories
$directories = [
    BASE_PATH . '/storage',
    BASE_PATH . '/storage/logs',
    BASE_PATH . '/storage/exports',
    BASE_PATH . '/storage/invoices',
    BASE_PATH . '/storage/bol_files',
    BASE_PATH . '/logs',
    BASE_PATH . '/cache'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "Created directory: $dir\n";
        } else {
            echo "Failed to create directory: $dir\n";
        }
    } else {
        echo "Directory exists: $dir\n";
    }
    
    // Fix permissions
    if (is_dir($dir)) {
        chmod($dir, 0755);
    }
}

// Create .gitkeep files
$gitkeepFiles = [
    BASE_PATH . '/storage/logs/.gitkeep',
    BASE_PATH . '/storage/exports/.gitkeep',
    BASE_PATH . '/storage/invoices/.gitkeep',
    BASE_PATH . '/storage/bol_files/.gitkeep',
    BASE_PATH . '/logs/.gitkeep',
    BASE_PATH . '/cache/.gitkeep'
];

foreach ($gitkeepFiles as $file) {
    if (!file_exists($file)) {
        if (touch($file)) {
            echo "Created .gitkeep: $file\n";
        }
    }
}

// Set proper permissions for key files
$files = [
    BASE_PATH . '/.env' => 0600,
    BASE_PATH . '/public/index.php' => 0644,
    BASE_PATH . '/config' => 0755
];

foreach ($files as $file => $permission) {
    if (file_exists($file)) {
        chmod($file, $permission);
        echo "Set permissions for: $file ($permission)\n";
    }
}

echo "Maintenance tasks completed.\n";

// Test environment loading
try {
    require_once BASE_PATH . '/app/Core/Env.php';
    \App\Core\Env::load();
    echo "Environment variables loaded successfully.\n";
} catch (Exception $e) {
    echo "Error loading environment: " . $e->getMessage() . "\n";
}

// Test database connection
try {
    require_once BASE_PATH . '/app/Core/Database.php';
    $db = \App\Core\Database::getInstance();
    $stmt = $db->query("SELECT 1");
    echo "Database connection successful.\n";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}

echo "All checks completed.\n";
?>