<?php
/**
 * Advanced Security Middleware
 * 
 * @author System Improvement Update
 * @date 2025-01-10
 * @description Comprehensive security middleware for request filtering and protection
 */

namespace App\Core\Middleware;

use App\Core\Security\InputSanitizer;
use App\Core\Security\RateLimiter;

class SecurityMiddleware
{
    private $sanitizer;
    private $rateLimiter;
    private $securityHeaders = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net;",
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
    ];
    
    public function __construct()
    {
        $this->sanitizer = InputSanitizer::getInstance();
        $this->rateLimiter = new RateLimiter();
    }
    
    /**
     * Process incoming request
     */
    public function handle()
    {
        // Set security headers
        $this->setSecurityHeaders();
        
        // Check rate limiting
        $this->checkRateLimit();
        
        // Validate request method
        $this->validateRequestMethod();
        
        // Check for security threats
        $this->checkSecurityThreats();
        
        // Sanitize input data - Temporarily disabled to avoid data modification
        // $this->sanitizeInputData();
        
        // Check user agent
        $this->checkUserAgent();
        
        // Validate referrer for sensitive operations
        $this->validateReferrer();
        
        // Check for suspicious patterns
        $this->checkSuspiciousPatterns();
    }
    
    /**
     * Set security headers
     */
    private function setSecurityHeaders()
    {
        foreach ($this->securityHeaders as $header => $value) {
            header("$header: $value");
        }
        
        // Remove server information
        header_remove('X-Powered-By');
        header_remove('Server');
    }
    
    /**
     * Check rate limiting
     */
    private function checkRateLimit()
    {
        $ip = $this->getClientIP();
        $endpoint = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Different rate limits for different endpoints
        $limits = [
            '/login' => ['max' => 5, 'window' => 900], // 5 attempts per 15 minutes
            '/api/' => ['max' => 100, 'window' => 3600], // 100 requests per hour
            '/users/create' => ['max' => 10, 'window' => 3600], // 10 user creations per hour
            'default' => ['max' => 1000, 'window' => 3600] // 1000 requests per hour
        ];
        
        $limit = $limits['default'];
        foreach ($limits as $pattern => $patternLimit) {
            if ($pattern !== 'default' && strpos($endpoint, $pattern) !== false) {
                $limit = $patternLimit;
                break;
            }
        }
        
        if (!$this->rateLimiter->allow($ip, $endpoint, $limit['max'], $limit['window'] / 60)) {
            $this->blockRequest('Rate limit exceeded', 429);
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
            $this->blockRequest('Invalid request method', 405);
        }
    }
    
    /**
     * Check for security threats
     */
    private function checkSecurityThreats()
    {
        $allInput = array_merge($_GET, $_POST, $_COOKIE);
        
        // Filter out simple numeric values and safe data to avoid false positives
        $filteredInput = $this->filterSafeData($allInput);
        
        // Check for XSS attempts
        if ($this->sanitizer->detectXSS($filteredInput)) {
            $this->sanitizer->logSecurityIncident('xss_attempt', $filteredInput, 'high');
            $this->blockRequest('Security threat detected: XSS', 403);
        }
        
        // Check for SQL injection attempts - only on string values
        if ($this->sanitizer->detectSQLInjection($filteredInput)) {
            $this->sanitizer->logSecurityIncident('sql_injection_attempt', $filteredInput, 'critical');
            $this->blockRequest('Security threat detected: SQL Injection', 403);
        }
    }
    
    /**
     * Filter safe data to avoid false positives
     */
    private function filterSafeData($data)
    {
        $filtered = [];
        foreach ($data as $key => $value) {
            // Skip simple numeric values
            if (is_numeric($value) && strlen($value) < 10) {
                continue;
            }
            
            // Skip very short strings (likely safe)
            if (is_string($value) && strlen($value) < 3) {
                continue;
            }
            
            // Skip common safe values
            $safeValues = ['on', 'off', 'yes', 'no', 'true', 'false', 'active', 'inactive'];
            if (in_array(strtolower($value), $safeValues)) {
                continue;
            }
            
            $filtered[$key] = $value;
        }
        return $filtered;
    }
    
    /**
     * Sanitize input data
     */
    private function sanitizeInputData()
    {
        if (!empty($_POST)) {
            $_POST = $this->sanitizer->sanitizeInput($_POST);
        }
        
        if (!empty($_GET)) {
            $_GET = $this->sanitizer->sanitizeInput($_GET);
        }
        
        if (!empty($_COOKIE)) {
            foreach ($_COOKIE as $key => $value) {
                $_COOKIE[$key] = $this->sanitizer->sanitizeInput($value);
            }
        }
    }
    
    /**
     * Check user agent for suspicious patterns
     */
    private function checkUserAgent()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Suspicious user agent patterns
        $suspiciousPatterns = [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
            '/curl/i',
            '/wget/i',
            '/python/i',
            '/php/i',
            '/perl/i'
        ];
        
        // Allow legitimate bots (optional)
        $allowedBots = [
            'Googlebot',
            'Bingbot',
            'facebookexternalhit'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                // Check if it's an allowed bot
                $isAllowed = false;
                foreach ($allowedBots as $allowedBot) {
                    if (strpos($userAgent, $allowedBot) !== false) {
                        $isAllowed = true;
                        break;
                    }
                }
                
                if (!$isAllowed && !$this->isApiRequest()) {
                    $this->sanitizer->logSecurityIncident('suspicious_user_agent', $userAgent, 'medium');
                    $this->blockRequest('Suspicious user agent', 403);
                }
            }
        }
    }
    
    /**
     * Validate referrer for sensitive operations
     */
    private function validateReferrer()
    {
        $sensitiveEndpoints = [
            '/users/create',
            '/users/edit/',
            '/users/delete/',
            '/settings/save',
            '/api/sync/'
        ];
        
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Check if this is a sensitive operation
        foreach ($sensitiveEndpoints as $endpoint) {
            if (strpos($currentUri, $endpoint) !== false) {
                // For POST requests to sensitive endpoints, validate referrer
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    if (empty($referrer) || !$this->isValidReferrer($referrer, $host)) {
                        $this->sanitizer->logSecurityIncident('invalid_referrer', [
                            'endpoint' => $currentUri,
                            'referrer' => $referrer
                        ], 'high');
                        $this->blockRequest('Invalid referrer', 403);
                    }
                }
                break;
            }
        }
    }
    
    /**
     * Check for suspicious patterns in request
     */
    private function checkSuspiciousPatterns()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Suspicious URI patterns
        $suspiciousPatterns = [
            '/\.\.\//i',  // Directory traversal
            '/etc\/passwd/i',  // System file access
            '/proc\/self\/environ/i',  // Process environment
            '/cmd\.exe/i',  // Windows command execution
            '/bin\/sh/i',  // Shell execution
            '/union.*select/i',  // SQL injection
            '/<script/i',  // XSS attempts
            '/javascript:/i',  // JavaScript injection
            '/vbscript:/i',  // VBScript injection
            '/eval\(/i',  // Code evaluation
            '/base64_decode/i',  // Base64 decoding
            '/shell_exec/i',  // Shell execution
            '/system\(/i',  // System command execution
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $uri)) {
                $this->sanitizer->logSecurityIncident('suspicious_uri_pattern', $uri, 'high');
                $this->blockRequest('Suspicious request pattern', 403);
            }
        }
    }
    
    /**
     * Get real client IP address
     */
    private function getClientIP()
    {
        // Check for various headers that might contain the real IP
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_X_CLIENT_IP',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (load balancers)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Check if referrer is valid
     */
    private function isValidReferrer($referrer, $host)
    {
        $referrerHost = parse_url($referrer, PHP_URL_HOST);
        return $referrerHost === $host;
    }
    
    /**
     * Check if this is an API request
     */
    private function isApiRequest()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($uri, '/api/') !== false;
    }
    
    /**
     * Block request with appropriate response
     */
    private function blockRequest($reason, $statusCode = 403)
    {
        http_response_code($statusCode);
        
        // Log the blocked request
        error_log("BLOCKED REQUEST: $reason - IP: " . $this->getClientIP() . " - URI: " . ($_SERVER['REQUEST_URI'] ?? ''));
        
        // Return appropriate response based on request type
        if ($this->isApiRequest() || $this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'message' => 'Request blocked for security reasons',
                'code' => $statusCode
            ]);
        } else {
            echo "<html><head><title>Access Denied</title></head><body>";
            echo "<h1>Access Denied</h1>";
            echo "<p>Your request has been blocked for security reasons.</p>";
            echo "<p>If you believe this is an error, please contact the system administrator.</p>";
            echo "</body></html>";
        }
        
        exit;
    }
    
    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Whitelist IP addresses for certain operations
     */
    public function isWhitelistedIP($ip = null)
    {
        if ($ip === null) {
            $ip = $this->getClientIP();
        }
        
        $whitelist = [
            '127.0.0.1',
            '::1',
            // Add your trusted IP addresses here
        ];
        
        return in_array($ip, $whitelist);
    }
    
    /**
     * Check for brute force attempts
     */
    public function checkBruteForce($identifier, $action = 'login')
    {
        $maxAttempts = 5;
        $timeWindow = 15; // minutes
        
        $attempts = $this->getFailedAttempts($identifier, $action, $timeWindow);
        
        if ($attempts >= $maxAttempts) {
            $this->sanitizer->logSecurityIncident('brute_force_detected', [
                'identifier' => $identifier,
                'action' => $action,
                'attempts' => $attempts
            ], 'critical');
            
            $this->blockRequest('Too many failed attempts', 429);
        }
    }
    
    /**
     * Get failed attempts count
     */
    private function getFailedAttempts($identifier, $action, $timeWindow)
    {
        try {
            $db = \App\Core\Database::getInstance();
            $sql = "SELECT COUNT(*) FROM security_logs 
                    WHERE type = ? AND data LIKE ? AND timestamp > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
            
            $stmt = $db->query($sql, [
                'failed_' . $action,
                '%' . $identifier . '%',
                $timeWindow
            ]);
            
            return $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * Clear failed attempts after successful login
     */
    public function clearFailedAttempts($identifier, $action = 'login')
    {
        try {
            $db = \App\Core\Database::getInstance();
            $sql = "DELETE FROM security_logs 
                    WHERE type = ? AND data LIKE ? AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            
            $db->query($sql, [
                'failed_' . $action,
                '%' . $identifier . '%'
            ]);
        } catch (\Exception $e) {
            // Silent fail
        }
    }
}