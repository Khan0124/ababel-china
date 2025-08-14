<?php
/**
 * Advanced Input Sanitizer
 * 
 * @author System Improvement Update
 * @date 2025-01-10
 * @description Comprehensive input sanitization and validation for security
 */

namespace App\Core\Security;

class InputSanitizer
{
    private static $instance = null;
    
    // XSS patterns to detect
    private $xssPatterns = [
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
        '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
        '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/mi',
        '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/mi',
        '/<form\b[^<]*(?:(?!<\/form>)<[^<]*)*<\/form>/mi',
        '/javascript:/i',
        '/vbscript:/i',
        '/data:(?!image\/)/i',
        '/on\w+\s*=/i'
    ];
    
    // SQL injection patterns - Conservative approach to avoid false positives
    private $sqlPatterns = [
        '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC)\s+(.*\s+)?(FROM|INTO|SET|WHERE|VALUES))/i',
        '/(\b(SCRIPT|JAVASCRIPT|VBSCRIPT|ONLOAD|ONERROR|ONCLICK)\b)/i',
        '/(\'[^\']*(\;|--|\/\*)[^\']*\')/i',
        '/(\";|\";|\"--|\"\/\*)/i',
        '/(UNION\s+SELECT)/i'
    ];
    
    // File upload dangerous extensions
    private $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'asp', 'aspx', 
        'jsp', 'jspx', 'cfm', 'cfc', 'pl', 'sh', 'bat', 'cmd',
        'exe', 'com', 'scr', 'vbs', 'js', 'jar'
    ];
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Sanitize all input data
     */
    public function sanitizeInput($data, $type = 'mixed')
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitizedKey = $this->sanitizeKey($key);
                $sanitized[$sanitizedKey] = $this->sanitizeInput($value, $type);
            }
            return $sanitized;
        }
        
        if (!is_string($data)) {
            return $data;
        }
        
        // Basic sanitization
        $data = trim($data);
        
        // Type-specific sanitization
        switch ($type) {
            case 'html':
                return $this->sanitizeHtml($data);
            case 'sql':
                return $this->sanitizeSql($data);
            case 'email':
                return $this->sanitizeEmail($data);
            case 'url':
                return $this->sanitizeUrl($data);
            case 'filename':
                return $this->sanitizeFilename($data);
            case 'numeric':
                return $this->sanitizeNumeric($data);
            case 'alpha':
                return $this->sanitizeAlpha($data);
            case 'alphanumeric':
                return $this->sanitizeAlphanumeric($data);
            default:
                return $this->sanitizeGeneral($data);
        }
    }
    
    /**
     * Sanitize HTML content
     */
    private function sanitizeHtml($data)
    {
        // Remove XSS patterns
        foreach ($this->xssPatterns as $pattern) {
            $data = preg_replace($pattern, '', $data);
        }
        
        // Convert special characters
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $data;
    }
    
    /**
     * Sanitize for SQL (though prepared statements should be used)
     */
    private function sanitizeSql($data)
    {
        // Escape special characters
        $data = addslashes($data);
        
        // Remove potentially dangerous SQL patterns
        foreach ($this->sqlPatterns as $pattern) {
            $data = preg_replace($pattern, '', $data);
        }
        
        return $data;
    }
    
    /**
     * Sanitize email address
     */
    private function sanitizeEmail($data)
    {
        return filter_var($data, FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Sanitize URL
     */
    private function sanitizeUrl($data)
    {
        $data = filter_var($data, FILTER_SANITIZE_URL);
        
        // Check for dangerous protocols
        if (preg_match('/^(javascript|data|vbscript):/i', $data)) {
            return '';
        }
        
        return $data;
    }
    
    /**
     * Sanitize filename
     */
    private function sanitizeFilename($data)
    {
        // Remove path separators
        $data = str_replace(['/', '\\', '..', ':'], '', $data);
        
        // Remove special characters
        $data = preg_replace('/[^a-zA-Z0-9_.-]/', '', $data);
        
        // Check for dangerous extensions
        $extension = strtolower(pathinfo($data, PATHINFO_EXTENSION));
        if (in_array($extension, $this->dangerousExtensions)) {
            $data = str_replace('.' . $extension, '.txt', $data);
        }
        
        return $data;
    }
    
    /**
     * Sanitize numeric input
     */
    private function sanitizeNumeric($data)
    {
        return preg_replace('/[^0-9.]/', '', $data);
    }
    
    /**
     * Sanitize alphabetic input
     */
    private function sanitizeAlpha($data)
    {
        return preg_replace('/[^a-zA-Z\s]/', '', $data);
    }
    
    /**
     * Sanitize alphanumeric input
     */
    private function sanitizeAlphanumeric($data)
    {
        return preg_replace('/[^a-zA-Z0-9\s]/', '', $data);
    }
    
    /**
     * General sanitization
     */
    private function sanitizeGeneral($data)
    {
        // Remove null bytes
        $data = str_replace(chr(0), '', $data);
        
        // Remove XSS patterns
        foreach ($this->xssPatterns as $pattern) {
            $data = preg_replace($pattern, '', $data);
        }
        
        return $data;
    }
    
    /**
     * Sanitize array keys
     */
    private function sanitizeKey($key)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $key);
    }
    
    /**
     * Validate input data
     */
    public function validateInput($data, $rules)
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $fieldRules = explode('|', $rule);
            
            foreach ($fieldRules as $fieldRule) {
                $ruleParts = explode(':', $fieldRule, 2);
                $ruleName = $ruleParts[0];
                $ruleValue = $ruleParts[1] ?? null;
                
                switch ($ruleName) {
                    case 'required':
                        if (empty($value)) {
                            $errors[$field][] = "Field $field is required";
                        }
                        break;
                        
                    case 'email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = "Field $field must be a valid email";
                        }
                        break;
                        
                    case 'numeric':
                        if (!empty($value) && !is_numeric($value)) {
                            $errors[$field][] = "Field $field must be numeric";
                        }
                        break;
                        
                    case 'min':
                        if (!empty($value) && strlen($value) < $ruleValue) {
                            $errors[$field][] = "Field $field must be at least $ruleValue characters";
                        }
                        break;
                        
                    case 'max':
                        if (!empty($value) && strlen($value) > $ruleValue) {
                            $errors[$field][] = "Field $field must not exceed $ruleValue characters";
                        }
                        break;
                        
                    case 'url':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$field][] = "Field $field must be a valid URL";
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Detect potential XSS attempts
     */
    public function detectXSS($data)
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                if ($this->detectXSS($value)) {
                    return true;
                }
            }
            return false;
        }
        
        if (!is_string($data)) {
            return false;
        }
        
        foreach ($this->xssPatterns as $pattern) {
            if (preg_match($pattern, $data)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect potential SQL injection attempts
     */
    public function detectSQLInjection($data)
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                if ($this->detectSQLInjection($value)) {
                    return true;
                }
            }
            return false;
        }
        
        if (!is_string($data)) {
            return false;
        }
        
        foreach ($this->sqlPatterns as $pattern) {
            if (preg_match($pattern, $data)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log security incidents
     */
    public function logSecurityIncident($type, $data, $severity = 'medium')
    {
        $incident = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'severity' => $severity,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'data' => is_array($data) ? json_encode($data) : $data,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? ''
        ];
        
        error_log("SECURITY INCIDENT: " . json_encode($incident));
        
        // Store in database if available
        try {
            $db = \App\Core\Database::getInstance();
            $sql = "INSERT INTO security_logs 
                    (timestamp, type, severity, ip_address, user_agent, user_id, data, url, method)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $db->query($sql, [
                $incident['timestamp'],
                $incident['type'],
                $incident['severity'],
                $incident['ip_address'],
                $incident['user_agent'],
                $incident['user_id'],
                $incident['data'],
                $incident['url'],
                $incident['method']
            ]);
        } catch (\Exception $e) {
            // Silent fail for security logging
        }
    }
    
    /**
     * Sanitize uploaded file
     */
    public function sanitizeFileUpload($file)
    {
        if (!is_array($file) || !isset($file['name'])) {
            return false;
        }
        
        // Sanitize filename
        $file['name'] = $this->sanitizeFilename($file['name']);
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($extension, $this->dangerousExtensions)) {
            $this->logSecurityIncident('dangerous_file_upload', $file, 'high');
            return false;
        }
        
        // Check MIME type
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'text/csv', 'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        if (!in_array($file['type'], $allowedMimes)) {
            $this->logSecurityIncident('invalid_mime_type', $file, 'medium');
            return false;
        }
        
        return $file;
    }
    
    /**
     * Clean user input for display
     */
    public function cleanForDisplay($data)
    {
        if (is_array($data)) {
            foreach ($data as &$value) {
                $value = $this->cleanForDisplay($value);
            }
            return $data;
        }
        
        if (!is_string($data)) {
            return $data;
        }
        
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}