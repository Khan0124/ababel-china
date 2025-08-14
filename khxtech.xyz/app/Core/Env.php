<?php
namespace App\Core;

class Env
{
    private static $loaded = false;
    private static $variables = [];
    
    /**
     * Load environment variables from .env file
     */
    public static function load($path = null)
    {
        if (self::$loaded) {
            return;
        }
        
        if ($path === null) {
            $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            $path = $basePath . '/.env';
        }
        
        if (!file_exists($path)) {
            // Try to copy from .env.example if .env doesn't exist
            $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            $examplePath = $basePath . '/.env.example';
            if (file_exists($examplePath)) {
                copy($examplePath, $path);
            } else {
                throw new \Exception('.env file not found at: ' . $path);
            }
        }
        
        if (!is_readable($path)) {
            throw new \Exception('.env file is not readable. Check file permissions: ' . $path);
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            throw new \Exception('Failed to read .env file: ' . $path);
        }
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                // Replace ${VAR} references (avoid recursion)
                $value = preg_replace_callback('/\$\{([^}]+)\}/', function($matches) {
                    $var = $matches[1];
                    // Check if variable exists in our current variables first
                    if (isset(self::$variables[$var])) {
                        return self::$variables[$var];
                    }
                    // Check environment
                    if (isset($_ENV[$var])) {
                        return $_ENV[$var];
                    }
                    // Check getenv
                    $envValue = getenv($var);
                    if ($envValue !== false) {
                        return $envValue;
                    }
                    // Return empty string if not found
                    return '';
                }, $value);
                
                self::$variables[$key] = $value;
                
                // Also set in $_ENV and putenv for compatibility
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable
     */
    public static function get($key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }
        
        // Check in order: self::$variables, $_ENV, getenv()
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }
        
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Check if environment variable exists
     */
    public static function has($key)
    {
        return self::get($key) !== null;
    }
    
    /**
     * Get all environment variables
     */
    public static function all()
    {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$variables;
    }
}