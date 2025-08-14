<?php
namespace App\Core;

/**
 * Enhanced Logging System
 * PSR-3 compatible logger with different levels and contexts
 */
class Logger
{
    const DEBUG = 100;
    const INFO = 200;
    const NOTICE = 250;
    const WARNING = 300;
    const ERROR = 400;
    const CRITICAL = 500;
    const ALERT = 550;
    const EMERGENCY = 600;
    
    private $logDir;
    private $maxFileSize = 10485760; // 10MB
    private $maxFiles = 10;
    
    public function __construct()
    {
        $this->logDir = BASE_PATH . '/logs';
        
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    /**
     * System is unusable
     */
    public function emergency($message, array $context = [])
    {
        $this->log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Action must be taken immediately
     */
    public function alert($message, array $context = [])
    {
        $this->log(self::ALERT, $message, $context);
    }
    
    /**
     * Critical conditions
     */
    public function critical($message, array $context = [])
    {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Runtime errors
     */
    public function error($message, array $context = [])
    {
        $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Exceptional occurrences that are not errors
     */
    public function warning($message, array $context = [])
    {
        $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Normal but significant events
     */
    public function notice($message, array $context = [])
    {
        $this->log(self::NOTICE, $message, $context);
    }
    
    /**
     * Interesting events
     */
    public function info($message, array $context = [])
    {
        $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Detailed debug information
     */
    public function debug($message, array $context = [])
    {
        // Only log debug in development
        if (Env::get('APP_ENV') !== 'production') {
            $this->log(self::DEBUG, $message, $context);
        }
    }
    
    /**
     * Log with arbitrary level
     */
    public function log($level, $message, array $context = [])
    {
        $levelName = $this->getLevelName($level);
        $logFile = $this->getLogFile($levelName);
        
        // Build log entry
        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        // Add request context for web requests
        $requestContext = $this->getRequestContext();
        
        $logEntry = sprintf(
            "[%s] %s.%s: %s%s%s\n",
            $timestamp,
            $levelName,
            $requestContext['channel'],
            $message,
            $contextString,
            $requestContext['info']
        );
        
        // Write to file
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Rotate if needed
        $this->rotateIfNeeded($logFile);
        
        // Send to syslog for critical errors
        if ($level >= self::ERROR) {
            $this->sendToSyslog($level, $message, $context);
        }
    }
    
    /**
     * Log user activity
     */
    public function activity($action, $userId = null, array $details = [])
    {
        $context = [
            'user_id' => $userId ?? ($_SESSION['user_id'] ?? null),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'details' => $details
        ];
        
        $this->info("User Activity: $action", $context);
    }
    
    /**
     * Log security events
     */
    public function security($event, array $context = [])
    {
        $securityContext = array_merge($context, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
        
        $this->warning("Security Event: $event", $securityContext);
        
        // Also log to security-specific file
        $securityFile = $this->logDir . '/security.log';
        $logEntry = sprintf(
            "[%s] %s - IP: %s - User: %s - %s\n",
            date('Y-m-d H:i:s'),
            $event,
            $securityContext['ip'],
            $securityContext['user_id'] ?? 'anonymous',
            json_encode($securityContext, JSON_UNESCAPED_UNICODE)
        );
        
        file_put_contents($securityFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log database queries (for debugging)
     */
    public function query($sql, array $params = [], $duration = null)
    {
        if (Env::get('APP_ENV') !== 'production') {
            $context = [
                'params' => $params,
                'duration_ms' => $duration
            ];
            
            $this->debug("Database Query: $sql", $context);
        }
    }
    
    /**
     * Get log file path for level
     */
    private function getLogFile($levelName)
    {
        return $this->logDir . '/' . strtolower($levelName) . '.log';
    }
    
    /**
     * Get level name from level number
     */
    private function getLevelName($level)
    {
        $levels = [
            self::DEBUG => 'DEBUG',
            self::INFO => 'INFO',
            self::NOTICE => 'NOTICE',
            self::WARNING => 'WARNING',
            self::ERROR => 'ERROR',
            self::CRITICAL => 'CRITICAL',
            self::ALERT => 'ALERT',
            self::EMERGENCY => 'EMERGENCY'
        ];
        
        return $levels[$level] ?? 'UNKNOWN';
    }
    
    /**
     * Get request context
     */
    private function getRequestContext()
    {
        if (php_sapi_name() === 'cli') {
            return [
                'channel' => 'CLI',
                'info' => ''
            ];
        }
        
        $info = sprintf(
            ' [%s %s] [IP: %s]',
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        
        return [
            'channel' => 'WEB',
            'info' => $info
        ];
    }
    
    /**
     * Rotate log file if needed
     */
    private function rotateIfNeeded($logFile)
    {
        if (!file_exists($logFile) || filesize($logFile) < $this->maxFileSize) {
            return;
        }
        
        $pathInfo = pathinfo($logFile);
        $baseName = $pathInfo['filename'];
        $extension = $pathInfo['extension'];
        $directory = $pathInfo['dirname'];
        
        // Move existing rotated logs
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = "$directory/$baseName.{$i}.$extension";
            $newFile = "$directory/$baseName." . ($i + 1) . ".$extension";
            
            if (file_exists($oldFile)) {
                if ($i + 1 > $this->maxFiles) {
                    unlink($oldFile); // Delete oldest
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        // Rotate current log
        $rotatedFile = "$directory/$baseName.1.$extension";
        rename($logFile, $rotatedFile);
        
        // Compress rotated file
        if (function_exists('gzopen')) {
            $this->compressLogFile($rotatedFile);
        }
    }
    
    /**
     * Compress log file
     */
    private function compressLogFile($file)
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
            
            unlink($file); // Remove uncompressed file
        }
    }
    
    /**
     * Send to system log
     */
    private function sendToSyslog($level, $message, array $context = [])
    {
        $priority = LOG_INFO;
        
        if ($level >= self::EMERGENCY) $priority = LOG_EMERG;
        elseif ($level >= self::ALERT) $priority = LOG_ALERT;
        elseif ($level >= self::CRITICAL) $priority = LOG_CRIT;
        elseif ($level >= self::ERROR) $priority = LOG_ERR;
        elseif ($level >= self::WARNING) $priority = LOG_WARNING;
        
        $contextString = !empty($context) ? ' ' . json_encode($context) : '';
        syslog($priority, "KHXTech: $message$contextString");
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        static $instance = null;
        
        if ($instance === null) {
            $instance = new self();
        }
        
        return $instance;
    }
}