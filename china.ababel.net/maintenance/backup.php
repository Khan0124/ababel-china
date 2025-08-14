<?php
/**
 * Database backup script
 */

define('BASE_PATH', dirname(__DIR__));

// Load environment
require_once BASE_PATH . '/app/Core/Env.php';
\App\Core\Env::load();

$dbHost = \App\Core\Env::get('DB_HOST');
$dbName = \App\Core\Env::get('DB_DATABASE');
$dbUser = \App\Core\Env::get('DB_USERNAME');
$dbPass = \App\Core\Env::get('DB_PASSWORD');

// Create backup directory
$backupDir = BASE_PATH . '/storage/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Generate backup filename
$timestamp = date('Y-m-d_H-i-s');
$backupFile = $backupDir . '/backup_' . $timestamp . '.sql';

// Create mysqldump command
$command = "mysqldump -h $dbHost -u $dbUser -p'$dbPass' $dbName > $backupFile 2>/dev/null";

echo "Creating database backup...\n";
echo "File: $backupFile\n";

$result = exec($command, $output, $returnCode);

if ($returnCode === 0) {
    $fileSize = filesize($backupFile);
    echo "✅ Backup created successfully!\n";
    echo "Size: " . number_format($fileSize / 1024, 2) . " KB\n";
    
    // Compress backup
    $gzFile = $backupFile . '.gz';
    if (file_exists($backupFile)) {
        $gzCommand = "gzip $backupFile";
        exec($gzCommand, $gzOutput, $gzReturn);
        
        if ($gzReturn === 0 && file_exists($gzFile)) {
            $gzSize = filesize($gzFile);
            echo "✅ Backup compressed: " . basename($gzFile) . "\n";
            echo "Compressed size: " . number_format($gzSize / 1024, 2) . " KB\n";
            echo "Compression ratio: " . round(($fileSize - $gzSize) / $fileSize * 100, 1) . "%\n";
        }
    }
    
    // Clean old backups (keep last 7 days)
    cleanOldBackups($backupDir, 7);
    
} else {
    echo "❌ Backup failed!\n";
    echo "Error code: $returnCode\n";
    if (!empty($output)) {
        echo "Output: " . implode("\n", $output) . "\n";
    }
    exit(1);
}

function cleanOldBackups($backupDir, $daysToKeep) {
    $files = glob($backupDir . '/backup_*.sql.gz');
    $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
    $deletedCount = 0;
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            unlink($file);
            $deletedCount++;
        }
    }
    
    if ($deletedCount > 0) {
        echo "🗑️ Cleaned $deletedCount old backup(s)\n";
    }
}

echo "\n✅ Backup process completed!\n";
?>