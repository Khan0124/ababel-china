<?php
namespace App\Core\Middleware;

class BasicSecurityMiddleware
{
    private $securityHeaders = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];
    
    public function handle()
    {
        $this->setSecurityHeaders();
        $this->validateRequestMethod();
        $this->basicXSSCheck();
        header_remove('X-Powered-By');
        header_remove('Server');
    }
    
    private function setSecurityHeaders()
    {
        foreach ($this->securityHeaders as $header => $value) {
            header("$header: $value");
        }
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    private function validateRequestMethod()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        if (!in_array($method, $allowedMethods, true)) {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
    }
    
    private function basicXSSCheck()
    {
        $allInput = array_merge($_GET, $_POST);
        $obviousXSS = [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/<iframe/i',
            '/onload=/i',
            '/onerror=/i',
        ];
        foreach ($allInput as $key => $value) {
            if (is_string($value)) {
                foreach ($obviousXSS as $pattern) {
                    if (preg_match($pattern, $value)) {
                        error_log("XSS attempt blocked: $key = $value from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                        http_response_code(403);
                        echo 'Request blocked for security reasons.';
                        exit;
                    }
                }
            }
        }
    }
}