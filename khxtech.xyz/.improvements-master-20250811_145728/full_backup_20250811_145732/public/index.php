<?php
/**
 * Main Application Entry Point
 * 
 * @author System Improvement Update
 * @date 2025-01-10
 * @description Refactored to use centralized routing configuration from config/routes.php
 *              Improved security, error handling, and performance
 */

// Define base path first
define('BASE_PATH', dirname(__DIR__));

// Load environment variables FIRST
require_once BASE_PATH . '/app/Core/Env.php';
\App\Core\Env::load();

// Error reporting configuration - production safe
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', \App\Core\Env::get('APP_DEBUG', false) ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', BASE_PATH . '/logs/error.log');

// Initialize error monitoring system
require_once BASE_PATH . '/app/Core/ErrorMonitor.php';
new \App\Core\ErrorMonitor();

// Initialize Basic Security Middleware (Added 2025-01-10) - Lightweight version
require_once BASE_PATH . '/app/Core/Middleware/BasicSecurityMiddleware.php';
$basicSecurity = new \App\Core\Middleware\BasicSecurityMiddleware();
$basicSecurity->handle();

// Session configuration with security improvements (Added 2025-01-10)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
session_start();

// Regenerate session ID periodically for security (Added 2025-01-10)
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = BASE_PATH . '/app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load helper functions - IMPORTANT: Load this early!
$helpersFile = BASE_PATH . '/app/Core/helpers.php';
if (file_exists($helpersFile)) {
    require_once $helpersFile;
} else {
    // If helpers file doesn't exist, create minimal functions
    function __($key, $params = []) {
        return $key; // Return key as fallback
    }
    function lang() {
        return $_SESSION['lang'] ?? 'ar';
    }
    function isRTL() {
        return in_array(lang(), ['ar', 'fa', 'he', 'ur']);
    }
}

// Check if config files exist
$configFile = BASE_PATH . '/config/app.php';
if (!file_exists($configFile)) {
    die("Error: Configuration file not found at: $configFile");
}

// Load routes configuration (Modified 2025-01-10)
$routesFile = BASE_PATH . '/config/routes.php';
if (!file_exists($routesFile)) {
    die("Error: Routes configuration file not found at: $routesFile");
}
$routesConfig = require $routesFile;

// Check if language files exist
$langDir = BASE_PATH . '/lang';
if (!is_dir($langDir)) {
    mkdir($langDir, 0755, true);
}

// Load configuration
$config = require $configFile;

// Set timezone
date_default_timezone_set($config['timezone'] ?? 'Asia/Shanghai');

// Simple router
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Handle route parameter for clients (backward compatibility)
if (isset($_GET['route']) && $_GET['route'] === 'clients') {
    $requestUri = '/clients';
}

// Remove trailing slash
if ($requestUri !== '/' && substr($requestUri, -1) === '/') {
    $requestUri = rtrim($requestUri, '/');
}

// Get public routes from configuration (Modified 2025-01-10)
$publicRoutes = $routesConfig['public_routes'] ?? [];

// Authentication check
if (!in_array($requestUri, $publicRoutes) && !isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Check role-based permissions (Added 2025-01-10)
if (isset($_SESSION['user_id']) && isset($routesConfig['role_permissions'])) {
    $userRole = $_SESSION['user_role'] ?? 'user';
    
    foreach ($routesConfig['role_permissions'] as $requiredRole => $protectedRoutes) {
        foreach ($protectedRoutes as $protectedRoute) {
            // Check if current route matches protected route pattern
            $pattern = str_replace('*', '.*', $protectedRoute);
            if (preg_match('#^' . $pattern . '$#', $requestUri)) {
                // Check if user has required role
                if (!hasRole($userRole, $requiredRole)) {
                    http_response_code(403);
                    echo "<h1>403 - Access Denied</h1>";
                    echo "<p>You don't have permission to access this resource.</p>";
                    exit;
                }
                break;
            }
        }
    }
}

// Helper function to check role hierarchy (Added 2025-01-10)
function hasRole($userRole, $requiredRole) {
    $roleHierarchy = [
        'admin' => 4,
        'accountant' => 3,
        'manager' => 2,
        'user' => 1
    ];
    
    $userLevel = $roleHierarchy[$userRole] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
    
    return $userLevel >= $requiredLevel;
}

// Get routes from configuration (Modified 2025-01-10)
$routes = $routesConfig['routes'] ?? [];

// Simple route dispatcher
function dispatch($routes, $method, $uri) {
    if (!isset($routes[$method])) {
        http_response_code(405);
        echo "Method Not Allowed";
        return;
    }
    
    foreach ($routes[$method] as $route => $handler) {
        $pattern = preg_replace('/\{id\}/', '([0-9]+)', $route);
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches); // Remove full match
            
            list($controller, $action) = explode('@', $handler);
            $controllerClass = "App\\Controllers\\{$controller}";
            
            if (!class_exists($controllerClass)) {
                http_response_code(500);
                error_log("Controller not found: $controllerClass");
                echo "<h1>500 - Internal Server Error</h1>";
                echo "<p>Controller not found. Please contact system administrator.</p>";
                return;
            }
            
            $controllerInstance = new $controllerClass();
            
            if (!method_exists($controllerInstance, $action)) {
                http_response_code(500);
                error_log("Action not found: $action in $controllerClass");
                echo "<h1>500 - Internal Server Error</h1>";
                echo "<p>Action not found. Please contact system administrator.</p>";
                return;
            }
            
            // Execute controller action with performance monitoring (Added 2025-01-10)
            $startTime = microtime(true);
            call_user_func_array([$controllerInstance, $action], $matches);
            $executionTime = microtime(true) - $startTime;
            
            // Log slow requests (Added 2025-01-10)
            if ($executionTime > 1.0) { // Log requests taking more than 1 second
                error_log("Slow request: $uri took " . round($executionTime, 3) . " seconds");
            }
            
            return;
        }
    }
    
    http_response_code(404);
    $errorFile = BASE_PATH . '/app/Views/errors/404.php';
    if (file_exists($errorFile)) {
        include $errorFile;
    } else {
        echo "<h1>404 - Page Not Found</h1>";
        echo "<p>The requested page could not be found.</p>";
    }
}

// Dispatch the request
try {
    dispatch($routes, $requestMethod, $requestUri);
} catch (Exception $e) {
    http_response_code(500);
    // Log the error for debugging
    error_log("Application Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Display generic error message to user
    echo "<h1>500 Internal Server Error</h1>";
    echo "<p>An internal error occurred. Please try again later.</p>";
    
    // Send error notification if in production (Added 2025-01-10)
    if (\App\Core\Env::get('APP_ENV') === 'production') {
        // TODO: Implement error notification system
    }
}