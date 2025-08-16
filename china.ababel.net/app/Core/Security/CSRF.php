<?php
namespace App\Core\Security;

use App\Core\Env;

class CSRF
{
    private static $tokenName;
    
    /**
     * Initialize CSRF protection
     */
    public static function init()
    {
        self::$tokenName = Env::get('CSRF_TOKEN_NAME', '_csrf_token');
        
        if (!isset($_SESSION[self::$tokenName])) {
            self::generateToken();
        }
    }
    
    /**
     * Generate new CSRF token
     */
    public static function generateToken()
    {
        $_SESSION[self::$tokenName] = bin2hex(random_bytes(32));
        return $_SESSION[self::$tokenName];
    }
    
    /**
     * Get current CSRF token
     */
    public static function getToken()
    {
        if (!isset($_SESSION[self::$tokenName])) {
            self::generateToken();
        }
        return $_SESSION[self::$tokenName];
    }
    
    /**
     * Get CSRF token field HTML
     */
    public static function field()
    {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . $token . '">';
    }
    
    /**
     * Verify CSRF token
     */
    public static function verify($token = null)
    {
        if ($token === null) {
            $token = $_POST[self::$tokenName] ?? $_GET[self::$tokenName] ?? null;
        }
        
        if (empty($token) || !isset($_SESSION[self::$tokenName])) {
            return false;
        }
        
        return hash_equals($_SESSION[self::$tokenName], $token);
    }
    
    /**
     * Check CSRF token and throw exception if invalid
     */
    public static function check()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || 
            $_SERVER['REQUEST_METHOD'] === 'PUT' || 
            $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            
            if (!self::verify()) {
                http_response_code(403);
                throw new \Exception('CSRF token validation failed');
            }
        }
    }
    
    /**
     * Regenerate token after successful form submission
     */
    public static function regenerate()
    {
        self::generateToken();
    }
    
    /**
     * Generate and return token (alias for generateToken for backward compatibility)
     */
    public static function generate()
    {
        return self::getToken();
    }
}