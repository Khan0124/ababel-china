<?php
namespace App\Core;

class ErrorHandler
{
    private static $isRegistered = false;
    
    public static function register()
    {
        if (self::$isRegistered) {
            return;
        }
        
        self::$isRegistered = true;
        
        // Set error handler
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    public static function handleError($severity, $message, $file, $line)
    {
        // Don't handle suppressed errors
        if (error_reporting() === 0) {
            return false;
        }
        
        // Convert error to exception
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    
    public static function handleException($exception)
    {
        // Log the error
        error_log(sprintf(
            "Exception: %s in %s on line %d\nStack trace:\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        ));
        
        // Only show error if not in production and headers not sent
        if (!headers_sent()) {
            // Check if we're in production or development
            if (getenv('APP_ENV') === 'development') {
                http_response_code(500);
                // Development - show detailed error
                echo '<h1>Exception: ' . htmlspecialchars($exception->getMessage()) . '</h1>';
                echo '<p>File: ' . htmlspecialchars($exception->getFile()) . '</p>';
                echo '<p>Line: ' . $exception->getLine() . '</p>';
                echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
            } else {
                // Production - just return, let the page render normally
                return;
            }
        }
        
        // Only exit in development mode
        if (getenv('APP_ENV') === 'development') {
            exit(1);
        }
    }
    
    public static function handleShutdown()
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }
}