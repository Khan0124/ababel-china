<?php
/**
 * Routes Configuration File
 * 
 * @author System Improvement Update
 * @date 2025-01-10
 * @description Centralized routing configuration moved from public/index.php for better organization
 *              and following MVC best practices
 */

return [
    // Public routes (no authentication required)
    'public_routes' => [
        '/login',
        '/forgot-password',
        '/api/auth',
        '/change-language',
        '/api/sync/webhook' // Webhook endpoint for external systems
    ],
    
    // Rate limited routes (special handling for brute force protection)
    'rate_limited_routes' => [
        '/login' => ['max_attempts' => 5, 'decay_minutes' => 15],
        '/api/auth' => ['max_attempts' => 5, 'decay_minutes' => 15],
        '/forgot-password' => ['max_attempts' => 3, 'decay_minutes' => 30]
    ],
    
    // Main application routes
    'routes' => [
        'GET' => [
            // Dashboard & Authentication
            '/' => 'DashboardController@index',
            '/dashboard' => 'DashboardController@index',
            '/login' => 'AuthController@login',
            '/logout' => 'AuthController@logout',
            '/profile' => 'AuthController@profile',
            '/change-language' => 'LanguageController@change',
            
            // API Sync Status
            '/api/sync/status/{id}' => 'Api\SyncController@status',
            
            // Clients Management
            '/clients' => 'ClientController@index',
            '/clients/create' => 'ClientController@create',
            '/clients/edit/{id}' => 'ClientController@edit',
            '/clients/statement/{id}' => 'ClientController@statement',
            '/clients/delete/{id}' => 'ClientController@delete',
            '/clients/export' => 'ClientController@export',
            
            // Transactions Management
            '/transactions' => 'TransactionController@index',
            '/transactions/create' => 'TransactionController@create',
            '/transactions/view/{id}' => 'TransactionController@show',
            '/transactions/approve/{id}' => 'TransactionController@showApprove',
            '/transactions/partial-payment/{id}' => 'TransactionController@showPartialPayment',
            '/transactions/search-by-claim' => 'TransactionController@searchByClaim', // AJAX endpoint
            '/transactions/export' => 'TransactionController@export',
            
            // Cashbox Management
            '/cashbox' => 'CashboxController@index',
            '/cashbox/movement' => 'CashboxController@movement',
            '/cashbox/history' => 'CashboxController@history',
            '/cashbox/currency-conversion' => 'CashboxCurrencyController@index',
            '/cashbox/currency-conversion/preview' => 'CashboxCurrencyController@getConversionPreview',
            '/cashbox/currency-conversion/history' => 'CashboxCurrencyController@getConversionHistory',
            '/cashbox/currency-conversion/balances' => 'CashboxCurrencyController@getCashboxBalances',
            '/cashbox/currency-conversion/report' => 'CashboxCurrencyController@generateReport',
            
            // Reports Module
            '/reports/daily' => 'ReportController@daily',
            '/reports/monthly' => 'ReportController@monthly',
            '/reports/clients' => 'ReportController@clients',
            '/reports/cashbox' => 'ReportController@cashbox',
            '/reports/unpaid-transactions' => 'ReportController@unpaidTransactions',
            '/reports/payment-analysis' => 'ReportController@paymentAnalysis',
            '/reports/overdue-shipments' => 'ReportController@overdueShipments',
            '/reports/client-balances-summary' => 'ReportController@clientBalancesSummary',
            
            // Settings Module
            '/settings' => 'SettingsController@index',
            '/settings/backup' => 'SettingsController@backup',
            '/settings/export' => 'SettingsController@exportData',
            
            // Loadings Management
            '/loadings' => 'LoadingController@index',
            '/loadings/create' => 'LoadingController@create',
            '/loadings/edit/{id}' => 'LoadingController@edit',
            '/loadings/show/{id}' => 'LoadingController@show',
            '/loadings/export' => 'LoadingController@export',
            '/loadings/issue-bol/{id}' => 'LoadingController@issueBol',
            
            // User Management (NEW - Added 2025-01-10)
            '/users' => 'UserController@index',
            '/users/create' => 'UserController@create',
            '/users/edit/{id}' => 'UserController@edit',
            '/users/permissions/{id}' => 'UserController@permissions',
            '/users/activity/{id}' => 'UserController@activity',
            
            
            // System Monitoring (NEW - Added 2025-01-10)
            '/system/monitor' => 'SystemMonitorController@index',
            '/system/logs' => 'SystemMonitorController@logs',
            '/system/performance' => 'SystemMonitorController@performance',
            '/system/errors' => 'SystemMonitorController@errors',
            '/system/backup-status' => 'SystemMonitorController@backupStatus',
            
            // Advanced Reports (NEW - Added 2025-01-10)
            '/reports/analytics' => 'AdvancedReportController@analytics',
            '/reports/forecasting' => 'AdvancedReportController@forecasting',
            '/reports/charts' => 'AdvancedReportController@charts',
            '/reports/executive-dashboard' => 'AdvancedReportController@executiveDashboard',
            
            // Exchange Rate Management (NEW - Added 2025-01-13)
            '/exchange-rates' => 'ExchangeRateController@index',
            '/exchange-rates/calculator' => 'CalculatorController@index',
            '/exchange-rates/report' => 'ExchangeRateController@generateReport',
            '/exchange-rates/history' => 'ExchangeRateController@getRateHistory',
            '/exchange-rates/widget-data' => 'ExchangeRateController@getWidgetData',
            '/exchange-rates/current-rate' => 'ExchangeRateController@getCurrentRate',
        ],
        
        'POST' => [
            // Authentication
            '/login' => 'AuthController@login',
            '/logout' => 'AuthController@logout',
            '/forgot-password' => 'AuthController@forgotPassword',
            '/reset-password' => 'AuthController@resetPassword',
            
            // Clients Management
            '/clients/create' => 'ClientController@create',
            '/clients/edit/{id}' => 'ClientController@edit',
            '/clients/delete/{id}' => 'ClientController@delete',
            '/clients/make-payment' => 'ClientController@makePayment',
            
            // Transactions Management
            '/transactions/create' => 'TransactionController@create',
            '/transactions/approve/{id}' => 'TransactionController@approve',
            '/transactions/partial-payment/{id}' => 'TransactionController@processPartialPayment',
            '/transactions/process-payment' => 'TransactionController@processPayment', // AJAX endpoint
            '/transactions/delete/{id}' => 'TransactionController@delete',
            
            // Cashbox Management
            '/cashbox/movement' => 'CashboxController@movement',
            '/cashbox/reconcile' => 'CashboxController@reconcile',
            '/cashbox/currency-conversion/execute' => 'CashboxCurrencyController@executeConversion',
            
            // Settings Management
            '/settings/save' => 'SettingsController@save',
            '/settings/backup' => 'SettingsController@createBackup',
            '/settings/restore' => 'SettingsController@restoreBackup',
            
            // API Sync Operations
            '/api/sync/retry/{id}' => 'Api\SyncController@retry',
            '/api/sync/all' => 'Api\SyncController@syncAll',
            '/api/sync/webhook' => 'Api\SyncController@webhook',
            '/api/sync/loading/{id}' => 'Api\SyncController@syncLoading',
            '/api/sync/bol/{id}' => 'Api\SyncController@updateBol',
            
            // Loadings Management
            '/loadings/create' => 'LoadingController@create',
            '/loadings/edit/{id}' => 'LoadingController@edit',
            '/loadings/delete/{id}' => 'LoadingController@delete',
            '/loadings/update-status/{id}' => 'LoadingController@updateStatus',
            
            // User Management (NEW - Added 2025-01-10)
            '/users/create' => 'UserController@create',
            '/users/edit/{id}' => 'UserController@edit',
            '/users/update/{id}' => 'UserController@update',
            '/users/delete/{id}' => 'UserController@delete',
            '/users/toggle-status/{id}' => 'UserController@toggleStatus',
            '/users/update-permissions/{id}' => 'UserController@updatePermissions',
            '/users/reset-password/{id}' => 'UserController@resetPassword',
            
            
            // System Operations (NEW - Added 2025-01-10)
            '/system/clear-cache' => 'SystemMonitorController@clearCache',
            '/system/run-backup' => 'SystemMonitorController@runBackup',
            '/system/test-email' => 'SystemMonitorController@testEmail',
            '/system/optimize-database' => 'SystemMonitorController@optimizeDatabase',
            
            // Exchange Rate Management (NEW - Added 2025-01-13)
            '/exchange-rates/update-rate' => 'SimpleExchangeRateController@updateRate',
            '/exchange-rates/auto-update' => 'ExchangeRateController@autoUpdate',
            '/exchange-rates/convert' => 'CalculatorController@convert',
            '/exchange-rates/initialize-defaults' => 'ExchangeRateController@initializeDefaults',
        ],
        
        'PUT' => [
            // RESTful API endpoints for future use
            '/api/clients/{id}' => 'ClientController@update',
            '/api/transactions/{id}' => 'TransactionController@update',
            '/api/loadings/{id}' => 'LoadingController@update',
            '/api/users/{id}' => 'UserController@update',
        ],
        
        'DELETE' => [
            // RESTful API endpoints for future use
            '/api/clients/{id}' => 'ClientController@destroy',
            '/api/transactions/{id}' => 'TransactionController@destroy',
            '/api/loadings/{id}' => 'LoadingController@destroy',
            '/api/users/{id}' => 'UserController@destroy',
        ]
    ],
    
    // Role-based access control
    'role_permissions' => [
        // Routes that require admin role
        'admin' => [
            '/users',
            '/users/*',
            '/system/*',
            '/settings/backup',
            '/settings/restore',
            '/reports/executive-dashboard'
        ],
        
        // Routes that require accountant role or higher
        'accountant' => [
            '/transactions/approve/*',
            '/cashbox/reconcile',
            '/reports/analytics',
            '/reports/forecasting'
        ],
        
        // Routes that require manager role or higher
        'manager' => [
            '/clients/delete/*',
            '/transactions/delete/*',
            '/loadings/delete/*',
            '/reports/*'
        ]
    ],
    
    // API rate limits (requests per minute)
    'api_rate_limits' => [
        '/api/sync/*' => 30,
        '/api/auth' => 10,
        '/api/*' => 60
    ],
    
    // Cache settings for routes (in seconds)
    'route_cache' => [
        '/reports/monthly' => 3600, // 1 hour
        '/reports/analytics' => 1800, // 30 minutes
        '/reports/client-balances-summary' => 900, // 15 minutes
        '/dashboard' => 300, // 5 minutes
    ]
];