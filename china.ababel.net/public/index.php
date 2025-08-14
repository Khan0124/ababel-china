<?php
// public/index.php - Updated version with helpers loading
// Define base path first
define('BASE_PATH', dirname(__DIR__));

// Error reporting configuration - production safe
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Use system default error log for now
ini_set('error_log', '/tmp/php_errors.log');

session_start();

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
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove trailing slash
if ($requestUri !== '/' && substr($requestUri, -1) === '/') {
    $requestUri = rtrim($requestUri, '/');
}

// Public routes (no authentication required)
$publicRoutes = ['/login', '/forgot-password', '/api/auth', '/change-language'];

// Authentication check
if (!in_array($requestUri, $publicRoutes) && !isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Route mapping
$routes = [
    'GET' => [
        '/' => 'DashboardController@index',
        '/dashboard' => 'DashboardController@index',
        '/login' => 'AuthController@login',
        '/logout' => 'AuthController@logout',
        '/profile' => 'AuthController@profile',
        '/change-language' => 'LanguageController@change',
        '/api/sync/status/{id}' => 'Api\SyncController@status',
        
        // Clients
        '/clients' => 'ClientController@index',
        '/clients/create' => 'ClientController@create',
        '/clients/edit/{id}' => 'ClientController@edit',
        '/clients/statement/{id}' => 'ClientController@statement',
        '/clients/delete/{id}' => 'ClientController@delete',
        
        // Transactions
        '/transactions' => 'TransactionController@index',
        '/transactions/create' => 'TransactionController@create',
        '/transactions/view/{id}' => 'TransactionController@show',
        '/transactions/approve/{id}' => 'TransactionController@showApprove', // ADD THIS LINE
        '/transactions/search-by-claim' => 'TransactionController@searchByClaim', // AJAX endpoint
        
        // Cashbox
        '/cashbox' => 'CashboxController@index',
        '/cashbox/movement' => 'CashboxController@movement',
        
        // Reports
        '/reports/daily' => 'ReportController@daily',
        '/reports/monthly' => 'ReportController@monthly',
        '/reports/clients' => 'ReportController@clients',
        '/reports/cashbox' => 'ReportController@cashbox',
        
        // Settings
        '/settings' => 'SettingsController@index',
        // Loadings
    '/loadings' => 'LoadingController@index',
    '/loadings/create' => 'LoadingController@create',
    '/loadings/edit/{id}' => 'LoadingController@edit',
    '/loadings/show/{id}' => 'LoadingController@show',  // Changed from /loadings/view/{id}
    '/loadings/export' => 'LoadingController@export',
    '/loadings/issue-bol/{id}' => 'LoadingController@issueBillOfLading',
    '/loadings/issue-bol/{id}' => 'LoadingController@issueBol',
    ],
    'POST' => [
        '/login' => 'AuthController@login',
        '/clients/create' => 'ClientController@create',
        '/clients/edit/{id}' => 'ClientController@edit',
        '/clients/make-payment' => 'ClientController@makePayment',
        '/transactions/create' => 'TransactionController@create',
        '/transactions/approve/{id}' => 'TransactionController@approve',
        '/transactions/process-payment' => 'TransactionController@processPayment', // AJAX endpoint
        '/cashbox/movement' => 'CashboxController@movement',
        '/settings/save' => 'SettingsController@save',
        '/api/sync/retry/{id}' => 'Api\SyncController@retry',
        '/api/sync/all' => 'Api\SyncController@syncAll',
        '/api/sync/webhook' => 'Api\SyncController@webhook',
        '/api/sync/loading/{id}' => 'Api\SyncController@syncLoading',
        '/api/sync/bol/{id}' => 'Api\SyncController@updateBol',
        // Loadings
    '/loadings/create' => 'LoadingController@create',
    '/loadings/edit/{id}' => 'LoadingController@edit',
    '/loadings/delete/{id}' => 'LoadingController@delete',
    '/loadings/update-status/{id}' => 'LoadingController@updateStatus',
    ]
];

// Simple route dispatcher
function dispatch($routes, $method, $uri) {
    if (!isset($routes[$method])) {
        http_response_code(405);
        echo "Method Not Allowed";
        return;
    }
    
    foreach ($routes[$method] as $route => $handler) {
        $pattern = preg_replace('/\{(\w+)\}/', '(\w+)', $route);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches); // Remove full match
            
            list($controller, $action) = explode('@', $handler);
            $controllerClass = "App\\Controllers\\{$controller}";
            
            if (!class_exists($controllerClass)) {
                http_response_code(500);
                echo "Controller not found: $controllerClass<br>";
                echo "Looking for file: " . BASE_PATH . "/app/Controllers/{$controller}.php";
                return;
            }
            
            $controllerInstance = new $controllerClass();
            
            if (!method_exists($controllerInstance, $action)) {
                http_response_code(500);
                echo "Action not found: $action in $controllerClass";
                return;
            }
            
            call_user_func_array([$controllerInstance, $action], $matches);
            return;
        }
    }
    
    http_response_code(404);
    $errorFile = BASE_PATH . '/app/Views/errors/404.php';
    if (file_exists($errorFile)) {
        include $errorFile;
    } else {
        echo "404 - Page Not Found";
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
}