<?php
// app/Core/Controller.php
namespace App\Core;

abstract class Controller
{
    protected function view($view, $data = [])
    {
        extract($data);
        
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        
        if (!file_exists($viewFile)) {
            die("View not found: $view");
        }
        
        require $viewFile;
    }
    
    protected function redirect($url)
    {
        header("Location: $url");
        exit;
    }
    
    protected function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    protected function isPost()
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    protected function getPost($key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }
    
    protected function getGet($key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }
    
    protected function hasPermission($requiredRole)
    {
        $roleHierarchy = [
            'viewer' => 1,
            'accountant' => 2,
            'admin' => 3
        ];
        
        $userRole = $_SESSION['user_role'] ?? 'viewer';
        
        return $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
    }
    public function __construct()
{
    // Base constructor
}

}