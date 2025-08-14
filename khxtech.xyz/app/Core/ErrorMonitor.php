<?php
namespace App\Core;

/**
 * Error Monitoring System
 * Tracks and alerts on critical errors
 */
class ErrorMonitor
{
    private $logFile;
    private $alertEmail;
    private $maxLogSize = 10485760; // 10MB
    private $errorRecovery;
    private $criticalErrorCount = 0;
    
    public function __construct()
    {
        $this->logFile = BASE_PATH . '/logs/error_monitor.log';
        $this->alertEmail = Env::get('ADMIN_EMAIL', 'admin@khxtech.xyz');
        $this->errorRecovery = ErrorRecovery::getInstance();
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Register error handlers
        $this->registerHandlers();
    }
    
    /**
     * Register error and exception handlers
     */
    private function registerHandlers()
    {
        // Set custom error handler
        set_error_handler([$this, 'handleError']);
        
        // Set custom exception handler
        set_exception_handler([$this, 'handleException']);
        
        // Register shutdown function for fatal errors
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError($severity, $message, $file, $line)
    {
        // Skip notices in production
        if ($severity === E_NOTICE && Env::get('APP_ENV') === 'production') {
            return true;
        }
        
        $errorData = [
            'type' => 'PHP_ERROR',
            'severity' => $this->getSeverityName($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI'
        ];
        
        $this->logError($errorData);
        
        // Alert on critical errors
        if ($severity === E_ERROR || $severity === E_USER_ERROR) {
            $this->sendAlert($errorData);
        }
        
        return true; // Don't execute PHP internal error handler
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handleException($exception)
    {
        $errorData = [
            'type' => 'EXCEPTION',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI'
        ];
        
        $this->logError($errorData);
        $this->sendAlert($errorData);
        
        // Display user-friendly error page
        if (Env::get('APP_ENV') === 'production') {
            http_response_code(500);
            echo "<h1>خطأ في النظام</h1><p>حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.</p>";
        }
    }
    
    /**
     * Handle fatal errors
     */
    public function handleShutdown()
    {
        $error = error_get_last();
        
        if ($error && $error['type'] === E_ERROR) {
            $errorData = [
                'type' => 'FATAL_ERROR',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $this->logError($errorData);
            $this->sendAlert($errorData);
        }
    }
    
    /**
     * Log error to file
     */
    private function logError($errorData)
    {
        $logEntry = json_encode($errorData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n" . str_repeat('-', 80) . "\n";
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Rotate log if too large
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxLogSize) {
            $this->rotateLog();
        }
    }
    
    /**
     * Rotate log files
     */
    private function rotateLog()
    {
        $timestamp = date('Y-m-d_H-i-s');
        $archivedLog = dirname($this->logFile) . '/error_monitor_' . $timestamp . '.log';
        
        if (rename($this->logFile, $archivedLog)) {
            // Compress archived log
            if (function_exists('gzopen')) {
                $this->compressFile($archivedLog);
            }
        }
    }
    
    /**
     * Compress log file
     */
    private function compressFile($file)
    {
        $gzFile = $file . '.gz';
        $input = fopen($file, 'rb');
        $output = gzopen($gzFile, 'wb');
        
        if ($input && $output) {
            while (!feof($input)) {
                gzwrite($output, fread($input, 8192));
            }
            fclose($input);
            gzclose($output);
            
            // Remove original file
            unlink($file);
        }
    }
    
    /**
     * Send alert email (placeholder)
     */
    private function sendAlert($errorData)
    {
        // For now, just log critical alerts
        $alertLog = BASE_PATH . '/logs/critical_alerts.log';
        $alertMessage = "[CRITICAL ALERT] " . date('Y-m-d H:i:s') . " - " . 
                       $errorData['type'] . ": " . $errorData['message'] . 
                       " in " . $errorData['file'] . ":" . $errorData['line'] . "\n";
        
        file_put_contents($alertLog, $alertMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get severity name
     */
    private function getSeverityName($severity)
    {
        $severityNames = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_DEPRECATED => 'E_DEPRECATED'
        ];
        
        return $severityNames[$severity] ?? 'UNKNOWN';
    }
    
    /**
     * Get error statistics
     */
    public static function getStats($days = 7)
    {
        $logFile = BASE_PATH . '/logs/error_monitor.log';
        
        if (!file_exists($logFile)) {
            return ['total' => 0, 'by_type' => [], 'recent' => []];
        }
        
        $stats = [
            'total' => 0,
            'by_type' => [],
            'recent' => []
        ];
        
        $content = file_get_contents($logFile);
        $entries = explode(str_repeat('-', 80), $content);
        
        $cutoffTime = time() - ($days * 24 * 3600);
        
        foreach ($entries as $entry) {
            if (trim($entry)) {
                $data = json_decode(trim($entry), true);
                if ($data && isset($data['timestamp'])) {
                    $timestamp = strtotime($data['timestamp']);
                    
                    if ($timestamp >= $cutoffTime) {
                        $stats['total']++;
                        
                        $type = $data['type'] ?? 'UNKNOWN';
                        $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
                        
                        if (count($stats['recent']) < 10) {
                            $stats['recent'][] = $data;
                        }
                    }
                }
            }
        }
        
        return $stats;
    }
}