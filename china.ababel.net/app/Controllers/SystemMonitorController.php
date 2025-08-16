<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class SystemMonitorController extends Controller
{
    public function index()
    {
        $this->checkAuth();
        $this->checkRole('admin');
        
        // Get system statistics
        $stats = $this->getSystemStats();
        
        $this->view('system/monitor', [
            'title' => __('system_monitor'),
            'stats' => $stats
        ]);
    }
    
    public function logs()
    {
        $this->checkAuth();
        $this->checkRole('admin');
        
        $errorLog = BASE_PATH . '/logs/error.log';
        $logs = [];
        
        if (file_exists($errorLog)) {
            $logs = array_slice(file($errorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -100);
            $logs = array_reverse($logs);
        }
        
        $this->view('system/logs', [
            'title' => __('system_logs'),
            'logs' => $logs
        ]);
    }
    
    public function performance()
    {
        $this->checkAuth();
        $this->checkRole('admin');
        
        $db = Database::getInstance();
        
        // Get database performance metrics
        $dbStats = $db->query("SHOW STATUS LIKE 'Queries'")->fetch();
        $tableStats = $db->query("
            SELECT 
                table_name,
                table_rows,
                data_length,
                index_length
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ")->fetchAll();
        
        $this->view('system/performance', [
            'title' => __('performance_metrics'),
            'dbStats' => $dbStats,
            'tableStats' => $tableStats
        ]);
    }
    
    public function errors()
    {
        $this->checkAuth();
        $this->checkRole('admin');
        
        $errorLog = BASE_PATH . '/logs/error.log';
        $errors = [];
        
        if (file_exists($errorLog)) {
            $lines = file($errorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (preg_match('/\[(.*?)\].*?(PHP \w+):\s+(.*)/', $line, $matches)) {
                    $errors[] = [
                        'date' => $matches[1],
                        'type' => $matches[2],
                        'message' => $matches[3]
                    ];
                }
            }
            $errors = array_slice(array_reverse($errors), 0, 50);
        }
        
        $this->view('system/errors', [
            'title' => __('error_log'),
            'errors' => $errors
        ]);
    }
    
    public function backupStatus()
    {
        $this->checkAuth();
        $this->checkRole('admin');
        
        $backupDir = BASE_PATH . '/storage/backups';
        $backups = [];
        
        if (is_dir($backupDir)) {
            $files = scandir($backupDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $backupDir . '/' . $file;
                    $backups[] = [
                        'name' => $file,
                        'size' => filesize($filePath),
                        'date' => date('Y-m-d H:i:s', filemtime($filePath))
                    ];
                }
            }
        }
        
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        $this->view('system/backup-status', [
            'title' => __('backup_status'),
            'backups' => $backups
        ]);
    }
    
    public function clearCache()
    {
        $this->checkAuth();
        $this->checkRole('admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/system/monitor');
            return;
        }
        
        $cacheDir = BASE_PATH . '/storage/cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        $_SESSION['success'] = __('cache_cleared');
        $this->redirect('/system/monitor');
    }
    
    public function runBackup()
    {
        $this->checkAuth();
        $this->checkRole('admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/system/monitor');
            return;
        }
        
        $backupScript = BASE_PATH . '/maintenance/auto_backup.php';
        if (file_exists($backupScript)) {
            $command = 'php ' . escapeshellarg($backupScript) . ' > /dev/null 2>&1 &';
            exec($command);
            $_SESSION['success'] = __('backup_started');
        } else {
            $_SESSION['error'] = __('backup_script_not_found');
        }
        
        $this->redirect('/system/backup-status');
    }
    
    public function optimizeDatabase()
    {
        $this->checkAuth();
        $this->checkRole('admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/system/monitor');
            return;
        }
        
        $db = Database::getInstance();
        $tables = $db->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $db->query("OPTIMIZE TABLE `$table`");
        }
        
        $_SESSION['success'] = __('database_optimized');
        $this->redirect('/system/performance');
    }
    
    private function getSystemStats()
    {
        $db = Database::getInstance();
        
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'database_size' => $this->getDatabaseSize(),
            'disk_usage' => $this->getDiskUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'active_users' => $this->getActiveUsers(),
            'total_transactions' => $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
            'total_clients' => $db->query("SELECT COUNT(*) FROM clients")->fetchColumn()
        ];
    }
    
    private function getDatabaseSize()
    {
        $db = Database::getInstance();
        $result = $db->query("
            SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ")->fetch();
        
        return round($result['size_mb'], 2) . ' MB';
    }
    
    private function getDiskUsage()
    {
        $total = disk_total_space(BASE_PATH);
        $free = disk_free_space(BASE_PATH);
        $used = $total - $free;
        $percentage = round(($used / $total) * 100, 2);
        
        return [
            'used' => $this->formatBytes($used),
            'total' => $this->formatBytes($total),
            'percentage' => $percentage
        ];
    }
    
    private function getMemoryUsage()
    {
        return [
            'current' => $this->formatBytes(memory_get_usage(true)),
            'peak' => $this->formatBytes(memory_get_peak_usage(true))
        ];
    }
    
    private function getActiveUsers()
    {
        $db = Database::getInstance();
        $thirtyMinutesAgo = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        
        return $db->query("
            SELECT COUNT(DISTINCT user_id) 
            FROM access_log 
            WHERE created_at > ?
        ", [$thirtyMinutesAgo])->fetchColumn() ?: 0;
    }
    
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    private function checkRole($requiredRole)
    {
        $userRole = $_SESSION['user_role'] ?? 'user';
        $roleHierarchy = [
            'admin' => 4,
            'accountant' => 3,
            'manager' => 2,
            'user' => 1
        ];
        
        $userLevel = $roleHierarchy[$userRole] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        
        if ($userLevel < $requiredLevel) {
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Access Denied']);
            exit;
        }
    }
}