<?php
namespace App\Core\Security;

use App\Core\Database;
use App\Core\Env;

class RateLimiter
{
    private static $db;
    
    /**
     * Initialize rate limiter
     */
    private static function init()
    {
        if (!self::$db) {
            self::$db = Database::getInstance();
            self::createTableIfNotExists();
        }
    }
    
    /**
     * Create rate limit table if it doesn't exist
     */
    private static function createTableIfNotExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            action VARCHAR(100) NOT NULL,
            attempts INT DEFAULT 1,
            first_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            blocked_until TIMESTAMP NULL,
            INDEX idx_identifier_action (identifier, action),
            INDEX idx_blocked_until (blocked_until)
        )";
        
        self::$db->query($sql);
    }
    
    /**
     * Check if action is allowed
     */
    public static function allow($identifier, $action = 'default', $maxAttempts = null, $decayMinutes = null)
    {
        self::init();
        
        if ($maxAttempts === null) {
            $maxAttempts = Env::get('RATE_LIMIT_MAX_ATTEMPTS', 5);
        }
        
        if ($decayMinutes === null) {
            $decayMinutes = Env::get('RATE_LIMIT_DECAY_MINUTES', 1);
        }
        
        // Clean old entries
        self::clean($decayMinutes);
        
        // Check if currently blocked
        $sql = "SELECT * FROM rate_limits 
                WHERE identifier = ? AND action = ? 
                AND blocked_until IS NOT NULL AND blocked_until > NOW()";
        $stmt = self::$db->query($sql, [$identifier, $action]);
        $blocked = $stmt->fetch();
        
        if ($blocked) {
            return false;
        }
        
        // Get current attempts
        $sql = "SELECT * FROM rate_limits 
                WHERE identifier = ? AND action = ? 
                AND first_attempt_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $stmt = self::$db->query($sql, [$identifier, $action, $decayMinutes]);
        $record = $stmt->fetch();
        
        if (!$record) {
            // First attempt
            $sql = "INSERT INTO rate_limits (identifier, action, attempts) VALUES (?, ?, 1)";
            self::$db->query($sql, [$identifier, $action]);
            return true;
        }
        
        // Check if exceeded max attempts
        if ($record['attempts'] >= $maxAttempts) {
            // Block for increasing periods
            $blockMinutes = pow(2, floor($record['attempts'] / $maxAttempts)) * $decayMinutes;
            $sql = "UPDATE rate_limits 
                    SET attempts = attempts + 1, 
                        blocked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                    WHERE id = ?";
            self::$db->query($sql, [$blockMinutes, $record['id']]);
            return false;
        }
        
        // Increment attempts
        $sql = "UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?";
        self::$db->query($sql, [$record['id']]);
        
        return true;
    }
    
    /**
     * Clear attempts for identifier
     */
    public static function clear($identifier, $action = null)
    {
        self::init();
        
        if ($action) {
            $sql = "DELETE FROM rate_limits WHERE identifier = ? AND action = ?";
            self::$db->query($sql, [$identifier, $action]);
        } else {
            $sql = "DELETE FROM rate_limits WHERE identifier = ?";
            self::$db->query($sql, [$identifier]);
        }
    }
    
    /**
     * Clean old entries
     */
    private static function clean($decayMinutes)
    {
        $sql = "DELETE FROM rate_limits 
                WHERE first_attempt_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
                AND (blocked_until IS NULL OR blocked_until < NOW())";
        self::$db->query($sql, [$decayMinutes * 10]); // Keep for 10x decay time
    }
    
    /**
     * Get remaining attempts
     */
    public static function remaining($identifier, $action = 'default', $maxAttempts = null)
    {
        self::init();
        
        if ($maxAttempts === null) {
            $maxAttempts = Env::get('RATE_LIMIT_MAX_ATTEMPTS', 5);
        }
        
        $sql = "SELECT attempts FROM rate_limits 
                WHERE identifier = ? AND action = ? 
                AND first_attempt_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $stmt = self::$db->query($sql, [$identifier, $action, Env::get('RATE_LIMIT_DECAY_MINUTES', 1)]);
        $record = $stmt->fetch();
        
        if (!$record) {
            return $maxAttempts;
        }
        
        return max(0, $maxAttempts - $record['attempts']);
    }
    
    /**
     * Get time until unblocked (in seconds)
     */
    public static function availableIn($identifier, $action = 'default')
    {
        self::init();
        
        $sql = "SELECT UNIX_TIMESTAMP(blocked_until) - UNIX_TIMESTAMP(NOW()) as seconds_remaining
                FROM rate_limits 
                WHERE identifier = ? AND action = ? 
                AND blocked_until IS NOT NULL AND blocked_until > NOW()";
        $stmt = self::$db->query($sql, [$identifier, $action]);
        $record = $stmt->fetch();
        
        return $record ? $record['seconds_remaining'] : 0;
    }
}