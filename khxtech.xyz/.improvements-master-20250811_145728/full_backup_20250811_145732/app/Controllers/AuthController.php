<?php
// app/Controllers/AuthController.php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\User;

class AuthController extends Controller
{
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            $userModel = new User();
            $user = $userModel->findByUsername($username);
            
            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                
                // Update last login
                $userModel->updateLastLogin($user['id']);
                
                // 2025-01-11: تسجيل عملية تسجيل الدخول في Activity Log
                $this->logActivity('login', $user['id'], 'تسجيل دخول: ' . $user['username']);
                
                // Redirect to dashboard
                $this->redirect('/dashboard');
            } else {
                $this->view('auth/login', [
                    'title' => 'تسجيل الدخول',
                    'error' => 'اسم المستخدم أو كلمة المرور غير صحيحة'
                ]);
            }
        } else {
            $this->view('auth/login', [
                'title' => 'تسجيل الدخول'
            ]);
        }
    }
    
    public function logout()
    {
        // 2025-01-11: تسجيل عملية تسجيل الخروج في Activity Log
        if (isset($_SESSION['user_id'])) {
            $this->logActivity('logout', $_SESSION['user_id'], 'تسجيل خروج: ' . ($_SESSION['user_name'] ?? 'Unknown'));
        }
        
        session_destroy();
        $this->redirect('/login');
    }
    
    public function profile()
    {
        $userModel = new User();
        $user = $userModel->find($_SESSION['user_id']);
        
        $this->view('auth/profile', [
            'title' => 'الملف الشخصي',
            'user' => $user
        ]);
    }
    
    /**
     * Log user activity
     * 2025-01-11: إضافة تسجيل الأنشطة لتسجيل الدخول والخروج
     */
    private function logActivity($action, $userId, $description, $tableName = null, $recordId = null)
    {
        try {
            $sql = "INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            // تحويل الوصف إلى JSON صالح
            $jsonData = json_encode(['description' => $description], JSON_UNESCAPED_UNICODE);
            
            $this->db->query($sql, [
                $userId,
                $action,
                $tableName ?? 'users',
                $recordId ?? $userId,
                $jsonData,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log activity in AuthController: " . $e->getMessage());
        }
    }
}