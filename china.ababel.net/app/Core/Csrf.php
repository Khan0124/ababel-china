<?php
namespace App\Core;

class Csrf
{
    const TOKEN_NAME = 'csrf_token';
    const TOKEN_LIFETIME = 3600; // 1 hour
    
    /**
     * Generate a new CSRF token
     */
    public static function generate()
    {
        if (!isset($_SESSION[self::TOKEN_NAME]) || !isset($_SESSION[self::TOKEN_NAME . '_time'])) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(32));
            $_SESSION[self::TOKEN_NAME . '_time'] = time();
        }
        
        // Regenerate token if expired
        if (time() - $_SESSION[self::TOKEN_NAME . '_time'] > self::TOKEN_LIFETIME) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(32));
            $_SESSION[self::TOKEN_NAME . '_time'] = time();
        }
        
        return $_SESSION[self::TOKEN_NAME];
    }
    
    /**
     * Get current CSRF token
     */
    public static function getToken()
    {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return self::generate();
        }
        return $_SESSION[self::TOKEN_NAME];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validate($token = null)
    {
        if ($token === null) {
            $token = $_POST[self::TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        
        if (empty($token) || !isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }
        
        // Check token expiry
        if (isset($_SESSION[self::TOKEN_NAME . '_time'])) {
            if (time() - $_SESSION[self::TOKEN_NAME . '_time'] > self::TOKEN_LIFETIME) {
                return false;
            }
        }
        
        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }
    
    /**
     * Get CSRF token field HTML
     */
    public static function field()
    {
        $token = self::generate();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Get CSRF token meta tag HTML
     */
    public static function meta()
    {
        $token = self::generate();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
}