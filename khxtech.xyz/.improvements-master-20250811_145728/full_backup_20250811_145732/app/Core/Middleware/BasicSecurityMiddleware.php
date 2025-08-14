<?php
/**
 * Basic Security Middleware
 * 
 * @author System Improvement Update
 * @date 2025-01-10
 * @description Lightweight security middleware with essential protections only
 */

namespace App\Core\Middleware;

class BasicSecurityMiddleware
{
    private $securityHeaders = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];
    
    /**
     * Process incoming request with minimal security checks
     */
    public function handle()
    {
        // Set basic security headers
        $this->setSecurityHeaders();
        
        // Basic request method validation
        $this->validateRequestMethod();
        
        // Basic XSS protection for critical fields only
        $this->basicXSSCheck();
        
        // Remove server information
        header_remove('X-Powered-By');
        header_remove('Server');
    }
    
    /**
     * Set basic security headers
     */
    private function setSecurityHeaders()
    {
        foreach ($this->securityHeaders as $header => $value) {
            header("$header: $value");
        }
        
        // Only set HTTPS headers if we're on HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Validate request method
     */
    private function validateRequestMethod()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        
        if (!in_array($method, $allowedMethods)) {
            http_response_code(405);
            echo "Method Not Allowed";
            exit;
        }
    }
    
    /**
     * Basic XSS check for obvious attacks only
     */
    private function basicXSSCheck()
    {
        $allInput = array_merge($_GET, $_POST);
        
        $obviousXSS = [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/<iframe/i',
            '/onload=/i',
            '/onerror=/i'
        ];
        
        foreach ($allInput as $key => $value) {
            if (is_string($value)) {
                foreach ($obviousXSS as $pattern) {
                    if (preg_match($pattern, $value)) {
                        error_log("XSS attempt blocked: $key = $value from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                        http_response_code(403);
                        echo "Request blocked for security reasons.";
                        exit;
                    }
                }
            }
        }
    }
    
    /**
     * Log security incident
     */
    private function logIncident($type, $data)
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'data' => $data
        ];
        
        error_log("SECURITY: " . json_encode($logData));
    }
}