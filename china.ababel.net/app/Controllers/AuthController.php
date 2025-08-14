<?php
// app/Controllers/AuthController.php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

class AuthController extends Controller
{
    public function login()
    {
        // Basic rate limiting (per IP) for login
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
        $attempt = $_SESSION['login_attempts'][$ip] ?? ['count' => 0, 'ts' => 0];
        $windowSeconds = 300; // 5 minutes
        $maxAttempts = 10;
        if (time() - $attempt['ts'] > $windowSeconds) {
            $attempt = ['count' => 0, 'ts' => time()];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Optional: email/username basic trim
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($attempt['count'] >= $maxAttempts) {
                $this->view('auth/login', [
                    'title' => 'تسجيل الدخول',
                    'error' => 'عدد محاولات تسجيل الدخول كبير. يرجى المحاولة لاحقاً.'
                ]);
                return;
            }

            $username = $username;
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
                
                // Reset attempts on success
                $_SESSION['login_attempts'][$ip] = ['count' => 0, 'ts' => time()];
                
                // Update last login
                $userModel->updateLastLogin($user['id']);
                
                // Activity log (best-effort)
                try {
                    $db = \App\Core\Database::getInstance();
                    $db->query("INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, ip_address, user_agent) VALUES (?, 'login', 'users', ?, ?, ?, ?)", [
                        $user['id'], $user['id'], json_encode(['description' => 'login'], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);
                } catch (\Throwable $e) {}
                
                // Redirect to dashboard
                $this->redirect('/dashboard');
            } else {
                $attempt['count'] += 1;
                $attempt['ts'] = time();
                $_SESSION['login_attempts'][$ip] = $attempt;
                
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
        // Activity log (best-effort)
        try {
            if (isset($_SESSION['user_id'])) {
                $db = \App\Core\Database::getInstance();
                $db->query("INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, ip_address, user_agent) VALUES (?, 'logout', 'users', ?, ?, ?, ?)", [
                    $_SESSION['user_id'], $_SESSION['user_id'], json_encode(['description' => 'logout'], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
            }
        } catch (\Throwable $e) {}
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