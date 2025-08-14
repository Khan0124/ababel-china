<?php
// public/index.php - Updated version with helpers loading
// Define base path first
define('BASE_PATH', dirname(__DIR__));

// Optionally load Composer autoloader for vendor packages
$vendorAutoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Error reporting configuration - production safe
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Use system default error log for now
ini_set('error_log', '/tmp/php_errors.log');

// Load helper functions early
$helpersFile = BASE_PATH . '/app/Core/helpers.php';
if (file_exists($helpersFile)) {
    require_once $helpersFile;
}

// Harden session cookies before starting session
if (function_exists('harden_session_cookies')) {
    harden_session_cookies();
}

session_start();

// Register global error/exception handler
require_once BASE_PATH . '/app/Core/ErrorHandler.php';
\App\Core\ErrorHandler::register();

// Send security headers
if (function_exists('send_security_headers')) {
    send_security_headers();
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

// Load helper functions fallback
if (!function_exists('__')) {
    function __($key, $params = []) { return $key; }
    function lang() { return $_SESSION['lang'] ?? 'ar'; }
    function isRTL() { return in_array(lang(), ['ar', 'fa', 'he', 'ur']); }
}

// Check if config files exist
$configFile = BASE_PATH . '/config/app.php';
if (!file_exists($configFile)) {
    die("Error: Configuration file not found at: $configFile");
}

// Ensure CSRF for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once BASE_PATH . '/app/Core/Csrf.php';
    if (!\App\Core\Csrf::validate()) {
        http_response_code(419);
        echo 'CSRF token mismatch';
        exit;
    }
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
$publicRoutes = ['/login', '/forgot-password', '/change-language'];

// Authentication check
$isApiRequest = str_starts_with($requestUri, '/api/');
if (!isset($_SESSION['user_id']) && !in_array($requestUri, $publicRoutes) && !$isApiRequest) {
    header('Location: /login');
    exit;
}

// Route mapping
$routes = [
    'GET' => [
        '/' => 'DashboardController@index',
        '/dashboard' => 'DashboardController@index',
        '/dashboard/enhanced' => 'EnhancedDashboardController@index',
        '/login' => 'AuthController@login',
        '/logout' => 'AuthController@logout',
        '/profile' => 'AuthController@profile',
        '/change-language' => 'LanguageController@change',
        '/api/sync/status/{id}' => 'Api\\SyncController@status',
        '/api/sync/statistics' => 'Api\\SyncController@statistics',
        '/api/sync/test' => 'Api\\SyncController@testConnection',
        
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
        '/transactions/approve/{id}' => 'TransactionController@showApprove',
        '/transactions/search-by-claim' => 'TransactionController@searchByClaim',
        
        // Cashbox
        '/cashbox' => 'CashboxController@index',
        '/cashbox/movement' => 'CashboxController@movement',
        '/cashbox/convert' => 'CashboxCurrencyController@index',
        '/cashbox/currency-conversion/preview' => 'CashboxCurrencyController@getConversionPreview',
        '/cashbox/currency-conversion/history' => 'CashboxCurrencyController@getConversionHistory',
        '/cashbox/currency-conversion/balances' => 'CashboxCurrencyController@getCashboxBalances',
        '/cashbox/currency-conversion/report' => 'CashboxCurrencyController@generateReport',
        
        // Exchange rates
        '/exchange-rates' => 'ExchangeRateController@index',
        '/exchange-rates/calculator' => 'ExchangeRateController@calculator',
        '/exchange-rates/get' => 'ExchangeRateController@getCurrentRate',
        '/exchange-rates/history' => 'ExchangeRateController@getRateHistory',
        '/exchange-rates/report' => 'ExchangeRateController@generateReport',
        
        // Reports
        '/reports/daily' => 'ReportController@daily',
        '/reports/monthly' => 'ReportController@monthly',
        '/reports/clients' => 'ReportController@clients',
        '/reports/cashbox' => 'ReportController@cashbox',
        // Exports
        '/export/daily' => 'ExportController@dailyReport',
        '/export/monthly' => 'ExportController@monthlyReport',
        '/export/cashbox' => 'ExportController@cashboxReport',
        '/export/client-statement' => 'ExportController@clientStatement',
        '/export/transactions' => 'ExportController@transactionsList',
        '/export/clients' => 'ExportController@clientsList',
        '/export/invoice' => 'ExportController@generateInvoice',
        '/export/receipt' => 'ExportController@generateReceipt',
        
        // Settings
        '/settings' => 'SettingsController@index',
        // Loadings
        '/loadings' => 'LoadingController@index',
        '/loadings/create' => 'LoadingController@create',
        '/loadings/edit/{id}' => 'LoadingController@edit',
        '/loadings/show/{id}' => 'LoadingController@show',
        '/loadings/export' => 'LoadingController@export',
        '/loadings/issue-bol/{id}' => 'LoadingController@issueBol',
        
        // Users
        '/users' => 'UserController@index',
        '/users/create' => 'UserController@create',
        '/users/edit/{id}' => 'UserController@edit',
        '/users/permissions/{id}' => 'UserController@permissions',
        '/users/activity/{id}' => 'UserController@activity',
        
        // Health
        '/health' => 'HealthController@check',
        
        // Exchange rate widget
        '/exchange-rates/widget' => 'ExchangeRateController@getWidgetData',
    ],
    'POST' => [
        '/login' => 'AuthController@login',
        '/clients/create' => 'ClientController@create',
        '/clients/edit/{id}' => 'ClientController@edit',
        '/clients/make-payment' => 'ClientController@makePayment',
        '/transactions/create' => 'TransactionController@create',
        '/transactions/approve/{id}' => 'TransactionController@approve',
        '/transactions/process-payment' => 'TransactionController@processPayment',
        '/transactions/partial-payment/{id}' => 'TransactionController@showPartialPayment',
        '/cashbox/movement' => 'CashboxController@movement',
        '/settings/save' => 'SettingsController@save',
        '/api/sync/retry/{id}' => 'Api\\SyncController@retry',
        '/api/sync/all' => 'Api\\SyncController@syncAll',
        '/api/sync/webhook' => 'Api\\SyncController@webhook',
        '/api/sync/loading/{id}' => 'Api\\SyncController@syncLoading',
        '/api/sync/bol/{id}' => 'Api\\SyncController@updateBol',
        // Exchange rates
        '/exchange-rates/update' => 'ExchangeRateController@updateRate',
        '/exchange-rates/auto-update' => 'ExchangeRateController@autoUpdate',
        // Cashbox conversions
        '/cashbox/convert' => 'CashboxCurrencyController@executeConversion',
        '/cashbox/currency-conversion/execute' => 'CashboxCurrencyController@executeConversion',
        
        // Users
        '/users/create' => 'UserController@create',
        '/users/edit/{id}' => 'UserController@update',
        '/users/delete/{id}' => 'UserController@delete',
        '/users/toggle-status/{id}' => 'UserController@toggleStatus',
        '/users/permissions/{id}/update' => 'UserController@updatePermissions',
        '/users/reset-password/{id}' => 'UserController@resetPassword',
        
        // Health
        '/health/maintenance' => 'HealthController@maintenance',
        
        // Transactions partial payment
        '/transactions/partial-payment/{id}' => 'TransactionController@processPartialPayment',
        
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