<?php
/**
 * Error Recovery and Resilience System
 * Handles errors gracefully and provides fallback mechanisms
 */

namespace App\Core;

class ErrorRecovery
{
    private static $instance = null;
    private $fallbackData = [];
    private $retryAttempts = 3;
    private $cacheDir;
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
        $this->cacheDir = BASE_PATH . '/storage/cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Set default fallback data
        $this->fallbackData = [
            'clients' => [],
            'transactions' => [],
            'loadings' => [],
            'filters' => [
                'date_from' => '',
                'date_to' => '',
                'client_id' => '',
                'status' => ''
            ]
        ];
    }
    
    /**
     * Execute function with error recovery
     */
    public function executeWithRecovery(callable $function, $fallbackValue = null, $maxRetries = 3)
    {
        $attempts = 0;
        $lastError = null;
        
        while ($attempts < $maxRetries) {
            try {
                return $function();
            } catch (\Throwable $e) {
                $lastError = $e;
                $attempts++;
                
                // Log the attempt
                error_log("ErrorRecovery: Attempt $attempts failed - " . $e->getMessage());
                
                // Wait before retry (exponential backoff)
                if ($attempts < $maxRetries) {
                    usleep(pow(2, $attempts) * 100000); // 0.2s, 0.4s, 0.8s
                }
            }
        }
        
        // All attempts failed, return fallback
        error_log("ErrorRecovery: All attempts failed, using fallback. Last error: " . $lastError->getMessage());
        return $fallbackValue;
    }
    
    /**
     * Safe database query with fallback
     */
    public function safeDbQuery($model, $method, $params = [], $fallbackKey = null)
    {
        return $this->executeWithRecovery(function() use ($model, $method, $params) {
            return call_user_func_array([$model, $method], $params);
        }, $this->getFallbackData($fallbackKey));
    }
    
    /**
     * Get fallback data for a key
     */
    private function getFallbackData($key)
    {
        if ($key && isset($this->fallbackData[$key])) {
            return $this->fallbackData[$key];
        }
        return [];
    }
    
    /**
     * Cache successful results for fallback
     */
    public function cacheResult($key, $data, $ttl = 3600)
    {
        try {
            $cacheFile = $this->cacheDir . '/' . md5($key) . '.cache';
            $cacheData = [
                'data' => $data,
                'expires' => time() + $ttl,
                'created' => time()
            ];
            file_put_contents($cacheFile, serialize($cacheData), LOCK_EX);
        } catch (\Exception $e) {
            error_log("ErrorRecovery: Cache write failed - " . $e->getMessage());
        }
    }
    
    /**
     * Get cached result as fallback
     */
    public function getCachedResult($key)
    {
        try {
            $cacheFile = $this->cacheDir . '/' . md5($key) . '.cache';
            
            if (!file_exists($cacheFile)) {
                return null;
            }
            
            $cacheData = unserialize(file_get_contents($cacheFile));
            
            if (!$cacheData || $cacheData['expires'] < time()) {
                return null;
            }
            
            return $cacheData['data'];
        } catch (\Exception $e) {
            error_log("ErrorRecovery: Cache read failed - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Safe controller execution with error recovery
     */
    public function safeControllerExecution($controller, $method, $params = [])
    {
        return $this->executeWithRecovery(function() use ($controller, $method, $params) {
            return call_user_func_array([$controller, $method], $params);
        }, function() use ($controller, $method) {
            // Fallback: show error page with basic functionality
            $this->showErrorFallback($controller, $method);
        });
    }
    
    /**
     * Show error fallback page
     */
    private function showErrorFallback($controller, $method)
    {
        http_response_code(503);
        
        // Try to get cached data
        $cachedClients = $this->getCachedResult('clients') ?? [];
        $cachedTransactions = $this->getCachedResult('transactions') ?? [];
        
        // Basic error page with cached data
        $fallbackData = [
            'title' => 'System Recovery Mode',
            'error_message' => 'The system is experiencing technical difficulties. Limited functionality is available.',
            'clients' => $cachedClients,
            'transactions' => $cachedTransactions,
            'filters' => $this->fallbackData['filters']
        ];
        
        // Determine which fallback view to show
        $fallbackView = $this->determineFallbackView($controller, $method);
        
        try {
            require_once BASE_PATH . '/app/Views/error/fallback.php';
        } catch (\Exception $e) {
            // Ultimate fallback - basic HTML
            echo $this->getBasicErrorPage();
        }
    }
    
    /**
     * Determine appropriate fallback view
     */
    private function determineFallbackView($controller, $method)
    {
        $controllerName = get_class($controller);
        
        if (strpos($controllerName, 'Transaction') !== false) {
            return 'transactions/error_fallback';
        } elseif (strpos($controllerName, 'Client') !== false) {
            return 'clients/error_fallback';
        } elseif (strpos($controllerName, 'Dashboard') !== false) {
            return 'dashboard/error_fallback';
        }
        
        return 'error/generic_fallback';
    }
    
    /**
     * Get basic HTML error page as ultimate fallback
     */
    private function getBasicErrorPage()
    {
        return '<!DOCTYPE html>
<html>
<head>
    <title>System Recovery Mode</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-icon { font-size: 48px; color: #dc3545; text-align: center; margin-bottom: 20px; }
        h1 { color: #333; text-align: center; }
        .message { color: #666; line-height: 1.6; margin-bottom: 30px; }
        .actions { text-align: center; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .status { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">⚠️</div>
        <h1>System Recovery Mode</h1>
        <div class="message">
            The Ababel Logistics System is experiencing technical difficulties and is running in recovery mode. 
            Our technical team has been notified and is working to restore full functionality.
        </div>
        <div class="actions">
            <a href="/" class="btn">Return Home</a>
            <a href="/dashboard" class="btn">Try Dashboard</a>
        </div>
        <div class="status">
            <strong>Status:</strong> Limited functionality available<br>
            <strong>Time:</strong> ' . date('Y-m-d H:i:s') . '<br>
            <strong>Expected Resolution:</strong> Within 30 minutes
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Health check for system components
     */
    public function healthCheck()
    {
        $status = [
            'database' => false,
            'filesystem' => false,
            'cache' => false,
            'overall' => false
        ];
        
        // Test database
        try {
            $db = Database::getInstance();
            $stmt = $db->query("SELECT 1");
            $status['database'] = $stmt !== false;
        } catch (\Exception $e) {
            error_log("Health check: Database failed - " . $e->getMessage());
        }
        
        // Test filesystem
        try {
            $testFile = $this->cacheDir . '/health_test.tmp';
            file_put_contents($testFile, 'test');
            $status['filesystem'] = file_get_contents($testFile) === 'test';
            unlink($testFile);
        } catch (\Exception $e) {
            error_log("Health check: Filesystem failed - " . $e->getMessage());
        }
        
        // Test cache
        try {
            $this->cacheResult('health_test', ['test' => true], 60);
            $cached = $this->getCachedResult('health_test');
            $status['cache'] = isset($cached['test']) && $cached['test'] === true;
        } catch (\Exception $e) {
            error_log("Health check: Cache failed - " . $e->getMessage());
        }
        
        $status['overall'] = $status['database'] && $status['filesystem'] && $status['cache'];
        
        return $status;
    }
    
    /**
     * Emergency shutdown
     */
    public function emergencyShutdown($reason = 'System maintenance')
    {
        // Create maintenance mode file
        $maintenanceFile = BASE_PATH . '/.maintenance';
        file_put_contents($maintenanceFile, json_encode([
            'reason' => $reason,
            'timestamp' => time(),
            'estimated_duration' => 1800 // 30 minutes
        ]));
        
        // Show maintenance page
        http_response_code(503);
        echo $this->getMaintenancePage($reason);
        exit;
    }
    
    /**
     * Get maintenance page HTML
     */
    private function getMaintenancePage($reason)
    {
        return '<!DOCTYPE html>
<html>
<head>
    <title>System Maintenance</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="300">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); text-align: center; max-width: 500px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 20px; }
        .message { color: #666; line-height: 1.6; margin-bottom: 30px; }
        .progress { background: #f0f0f0; height: 6px; border-radius: 3px; overflow: hidden; margin: 20px 0; }
        .progress-bar { background: #007bff; height: 100%; width: 0%; animation: progress 30s linear infinite; }
        @keyframes progress { 0% { width: 0%; } 100% { width: 100%; } }
        .time { color: #999; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>System Maintenance</h1>
        <div class="message">
            The Ababel Logistics System is temporarily unavailable for maintenance.<br>
            <strong>Reason:</strong> ' . htmlspecialchars($reason) . '
        </div>
        <div class="progress">
            <div class="progress-bar"></div>
        </div>
        <div class="time">
            This page will refresh automatically every 5 minutes.<br>
            Started at: ' . date('Y-m-d H:i:s') . '
        </div>
    </div>
</body>
</html>';
    }
}