<?php
/**
 * Automatic Daily Backup Script
 * Usage: php auto_backup.php
 * Cron: 0 2 * * * /usr/bin/php /www/wwwroot/khxtech.xyz/maintenance/auto_backup.php >> /www/wwwroot/khxtech.xyz/logs/backup.log 2>&1
 */

define('BASE_PATH', dirname(__DIR__));

// Load environment
require_once BASE_PATH . '/app/Core/Env.php';
\App\Core\Env::load();

// Configuration
$backupDir = BASE_PATH . '/storage/backups';
$maxBackups = 7; // Keep 7 days of backups
$logFile = BASE_PATH . '/logs/backup.log';

// Create directories
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

/**
 * Log function
 */
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry;
}

/**
 * Clean old backups
 */
function cleanOldBackups($backupDir, $maxBackups) {
    $files = glob($backupDir . '/backup_*.sql.gz');
    if (count($files) > $maxBackups) {
        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $filesToDelete = array_slice($files, 0, count($files) - $maxBackups);
        foreach ($filesToDelete as $file) {
            if (unlink($file)) {
                logMessage("Deleted old backup: " . basename($file));
            }
        }
    }
}

/**
 * Create database backup
 */
function createBackup() {
    global $backupDir;
    
    $dbHost = \App\Core\Env::get('DB_HOST');
    $dbName = \App\Core\Env::get('DB_DATABASE');
    $dbUser = \App\Core\Env::get('DB_USERNAME');
    $dbPass = \App\Core\Env::get('DB_PASSWORD');
    
    if (!$dbPass) {
        logMessage("ERROR: Database password not found in environment");
        return false;
    }
    
    // Generate backup filename
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . '/backup_' . $timestamp . '.sql';
    $gzFile = $backupFile . '.gz';
    
    // Create mysqldump command with error handling
    $command = sprintf(
        "mysqldump -h %s -u %s -p'%s' %s > %s 2>/dev/null",
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        $dbPass, // Don't escape this as it's already in quotes
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );
    
    logMessage("Starting backup for database: $dbName");
    
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        logMessage("ERROR: mysqldump failed with return code: $returnCode");
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }
        return false;
    }
    
    if (!file_exists($backupFile) || filesize($backupFile) == 0) {
        logMessage("ERROR: Backup file is empty or doesn't exist");
        return false;
    }
    
    $fileSize = filesize($backupFile);
    logMessage("Backup created: " . basename($backupFile) . " (" . number_format($fileSize / 1024, 2) . " KB)");
    
    // Compress backup
    $gzCommand = "gzip " . escapeshellarg($backupFile);
    exec($gzCommand, $gzOutput, $gzReturn);
    
    if ($gzReturn === 0 && file_exists($gzFile)) {
        $gzSize = filesize($gzFile);
        $compressionRatio = round(($fileSize - $gzSize) / $fileSize * 100, 1);
        logMessage("Backup compressed: " . basename($gzFile) . " (" . number_format($gzSize / 1024, 2) . " KB, $compressionRatio% compression)");
        return true;
    } else {
        logMessage("WARNING: Compression failed, keeping uncompressed backup");
        return true;
    }
}

// Main execution
try {
    logMessage("=== Starting automatic backup ===");
    
    // Check database connection first
    try {
        $config = require BASE_PATH . '/config/database.php';
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_TIMEOUT => 10]);
        logMessage("Database connection verified");
    } catch (Exception $e) {
        logMessage("ERROR: Database connection failed: " . $e->getMessage());
        exit(1);
    }
    
    // Create backup
    if (createBackup()) {
        logMessage("Backup completed successfully");
        
        // Clean old backups
        cleanOldBackups($backupDir, $maxBackups);
        
        logMessage("=== Backup process completed ===");
        exit(0);
    } else {
        logMessage("ERROR: Backup failed");
        exit(1);
    }
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    exit(1);
}