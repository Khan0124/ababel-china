<?php
namespace App\Core\Middleware;

class Auth
{
    public static function check(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            // For API requests, return 401 JSON
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
            $isApi = str_starts_with($uri, '/api/') || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
            if ($isApi) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            header('Location: /login');
            exit;
        }
    }

    public static function checkRole(string $requiredRole): void
    {
        self::check();
        $userRole = $_SESSION['user_role'] ?? 'user';
        $roleHierarchy = [
            'user' => 0,
            'manager' => 1,
            'accountant' => 2,
            'admin' => 3,
        ];
        $userLevel = $roleHierarchy[$userRole] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        if ($userLevel < $requiredLevel) {
            http_response_code(403);
            echo 'Access denied. Insufficient permissions.';
            exit;
        }
    }

    public static function checkAnyRole(array $roles): void
    {
        self::check();
        $userRole = $_SESSION['user_role'] ?? 'user';
        if (!in_array($userRole, $roles, true)) {
            http_response_code(403);
            echo 'Access denied. Insufficient permissions.';
            exit;
        }
    }
}