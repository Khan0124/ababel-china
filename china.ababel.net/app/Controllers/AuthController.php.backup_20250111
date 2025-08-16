<?php
// app/Controllers/AuthController.php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

class AuthController extends Controller
{
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
}