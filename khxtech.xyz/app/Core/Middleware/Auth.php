<?php
namespace App\Core\Middleware;

use App\Core\Security\RateLimiter;

class Auth
{
    /**
     * Check if user is authenticated
     */
    public static function check()
    {
        if (!isset($_SESSION['user_id'])) {
            self::redirectToLogin();
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $timeout = 3600; // 1 hour default
            if (time() - $_SESSION['last_activity'] > $timeout) {
                session_destroy();
                self::redirectToLogin('Session expired. Please login again.');
                return false;
            }
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Check user role permission
     */
    public static function checkRole($requiredRole)
    {
        if (!self::check()) {
            return false;
        }
        
        $userRole = $_SESSION['user_role'] ?? 'user';
        $roleHierarchy = [
            'admin' => 3,
            'accountant' => 2,
            'manager' => 1,
            'user' => 0
        ];
        
        $userLevel = $roleHierarchy[$userRole] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        
        if ($userLevel < $requiredLevel) {
            http_response_code(403);
            echo "Access denied. Insufficient permissions.";
            exit;
        }
        
        return true;
    }
    
    /**
     * Check multiple roles (OR logic)
     */
    public static function checkAnyRole($roles)
    {
        if (!self::check()) {
            return false;
        }
        
        $userRole = $_SESSION['user_role'] ?? 'user';
        
        if (in_array($userRole, $roles)) {
            return true;
        }
        
        http_response_code(403);
        echo "Access denied. Insufficient permissions.";
        exit;
    }
    
    /**
     * Check if user can access resource
     */
    public static function canAccess($resource, $action = 'view')
    {
        if (!self::check()) {
            return false;
        }
        
        $userRole = $_SESSION['user_role'] ?? 'user';
        $permissions = self::getRolePermissions($userRole);
        
        if (isset($permissions[$resource]) && 
            in_array($action, $permissions[$resource])) {
            return true;
        }
        
        http_response_code(403);
        echo "Access denied. You don't have permission to $action $resource.";
        exit;
    }
    
    /**
     * Rate limit check for authentication attempts
     */
    public static function checkRateLimit($identifier, $action = 'login')
    {
        $maxAttempts = 5;
        $decayMinutes = 15;
        
        if (!RateLimiter::allow($identifier, $action, $maxAttempts, $decayMinutes)) {
            $availableIn = RateLimiter::availableIn($identifier, $action);
            http_response_code(429);
            echo "Too many attempts. Try again in " . ceil($availableIn / 60) . " minutes.";
            exit;
        }
        
        return true;
    }
    
    /**
     * Clear rate limit on successful login
     */
    public static function clearRateLimit($identifier, $action = 'login')
    {
        RateLimiter::clear($identifier, $action);
    }
    
    /**
     * Get role permissions
     */
    private static function getRolePermissions($role)
    {
        $permissions = [
            'admin' => [
                'transactions' => ['view', 'create', 'edit', 'delete', 'approve'],
                'clients' => ['view', 'create', 'edit', 'delete'],
                'cashbox' => ['view', 'create', 'edit', 'delete'],
                'reports' => ['view', 'export'],
                'settings' => ['view', 'edit'],
                'users' => ['view', 'create', 'edit', 'delete'],
                'loadings' => ['view', 'create', 'edit', 'delete'],
                'exchange-rates' => ['view', 'create', 'edit', 'delete'],
                'currency-conversion' => ['view', 'create', 'edit']
            ],
            'accountant' => [
                'transactions' => ['view', 'create', 'edit', 'approve'],
                'clients' => ['view', 'create', 'edit'],
                'cashbox' => ['view', 'create', 'edit'],
                'reports' => ['view', 'export'],
                'loadings' => ['view', 'create', 'edit'],
                'exchange-rates' => ['view', 'create', 'edit'],
                'currency-conversion' => ['view', 'create', 'edit']
            ],
            'manager' => [
                'transactions' => ['view', 'create', 'edit'],
                'clients' => ['view', 'create', 'edit'],
                'cashbox' => ['view'],
                'reports' => ['view', 'export'],
                'loadings' => ['view', 'create', 'edit'],
                'exchange-rates' => ['view'],
                'currency-conversion' => ['view']
            ],
            'user' => [
                'transactions' => ['view'],
                'clients' => ['view'],
                'cashbox' => ['view'],
                'reports' => ['view'],
                'loadings' => ['view'],
                'exchange-rates' => ['view'],
                'currency-conversion' => ['view']
            ]
        ];
        
        return $permissions[$role] ?? $permissions['user'];
    }
    
    /**
     * Redirect to login page
     */
    private static function redirectToLogin($message = null)
    {
        $url = '/login';
        if ($message) {
            $url .= '?error=' . urlencode($message);
        }
        header("Location: $url");
        exit;
    }
    
    /**
     * Check if user owns resource
     */
    public static function ownsResource($resourceType, $resourceId)
    {
        if (!self::check()) {
            return false;
        }
        
        // For admin, always allow
        if ($_SESSION['user_role'] === 'admin') {
            return true;
        }
        
        // Check resource ownership based on type
        switch ($resourceType) {
            case 'transaction':
                return self::ownsTransaction($resourceId);
            case 'client':
                return self::ownsClient($resourceId);
            default:
                return false;
        }
    }
    
    /**
     * Check if user owns transaction
     */
    private static function ownsTransaction($transactionId)
    {
        try {
            $db = \App\Core\Database::getInstance();
            $sql = "SELECT created_by FROM transactions WHERE id = ?";
            $stmt = $db->query($sql, [$transactionId]);
            $transaction = $stmt->fetch();
            
            return $transaction && $transaction['created_by'] == $_SESSION['user_id'];
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if user owns client
     */
    private static function ownsClient($clientId)
    {
        try {
            $db = \App\Core\Database::getInstance();
            $sql = "SELECT created_by FROM clients WHERE id = ?";
            $stmt = $db->query($sql, [$clientId]);
            $client = $stmt->fetch();
            
            return $client && $client['created_by'] == $_SESSION['user_id'];
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Log access attempt
     */
    public static function logAccess($resource, $action, $success = true)
    {
        try {
            $db = \App\Core\Database::getInstance();
            $sql = "INSERT INTO access_log (user_id, resource, action, ip_address, user_agent, success, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $db->query($sql, [
                $_SESSION['user_id'] ?? 0,
                $resource,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $success ? 1 : 0
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
            error_log("Failed to log access: " . $e->getMessage());
        }
    }
}