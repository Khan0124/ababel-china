<?php
/**
 * Data Encryption Service
 * 
 * @author System Improvement Update
 * @date 2025-01-10
 * @description Advanced encryption service for sensitive data protection
 */

namespace App\Core\Security;

use App\Core\Env;

class Encryption
{
    private $cipher = 'AES-256-GCM';
    private $key;
    private $keyRotation = false;
    
    public function __construct()
    {
        // Get encryption key from environment or generate one
        $this->key = Env::get('ENCRYPTION_KEY');
        
        if (!$this->key) {
            // Generate a new key if not exists
            $this->key = $this->generateKey();
            error_log("WARNING: No encryption key found. Generated temporary key. Set ENCRYPTION_KEY in .env for production.");
        }
        
        // Decode base64 key
        $this->key = base64_decode($this->key);
        
        if (strlen($this->key) !== 32) {
            throw new \Exception('Invalid encryption key length. Must be 256 bits (32 bytes).');
        }
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt($data, $context = '')
    {
        if (empty($data)) {
            return null;
        }
        
        try {
            // Generate random IV
            $iv = random_bytes(12); // 96 bits for GCM
            
            // Additional authenticated data (context)
            $aad = $context . '|' . time();
            
            // Encrypt data
            $encrypted = openssl_encrypt(
                $data,
                $this->cipher,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $aad
            );
            
            if ($encrypted === false) {
                throw new \Exception('Encryption failed');
            }
            
            // Combine IV, tag, AAD length, AAD, and encrypted data
            $aadLength = pack('N', strlen($aad));
            $combined = $iv . $tag . $aadLength . $aad . $encrypted;
            
            return base64_encode($combined);
            
        } catch (\Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            throw new \Exception('Data encryption failed');
        }
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt($encryptedData, $context = '')
    {
        if (empty($encryptedData)) {
            return null;
        }
        
        try {
            $data = base64_decode($encryptedData);
            
            if ($data === false || strlen($data) < 32) {
                throw new \Exception('Invalid encrypted data format');
            }
            
            // Extract components
            $iv = substr($data, 0, 12);
            $tag = substr($data, 12, 16);
            $aadLength = unpack('N', substr($data, 28, 4))[1];
            $aad = substr($data, 32, $aadLength);
            $encrypted = substr($data, 32 + $aadLength);
            
            // Verify context if provided
            if ($context !== '') {
                $aadParts = explode('|', $aad);
                if (!isset($aadParts[0]) || $aadParts[0] !== $context) {
                    throw new \Exception('Invalid context for decryption');
                }
            }
            
            // Decrypt data
            $decrypted = openssl_decrypt(
                $encrypted,
                $this->cipher,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $aad
            );
            
            if ($decrypted === false) {
                throw new \Exception('Decryption failed');
            }
            
            return $decrypted;
            
        } catch (\Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            throw new \Exception('Data decryption failed');
        }
    }
    
    /**
     * Hash sensitive data for searching
     */
    public function hash($data, $salt = '')
    {
        if (empty($data)) {
            return null;
        }
        
        // Use HMAC for consistent hashing
        return hash_hmac('sha256', $data, $this->key . $salt);
    }
    
    /**
     * Generate secure hash for passwords
     */
    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }
    
    /**
     * Verify password hash
     */
    public function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate random encryption key
     */
    public function generateKey()
    {
        return base64_encode(random_bytes(32));
    }
    
    /**
     * Generate random token
     */
    public function generateToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Secure data wiping
     */
    public function secureWipe(&$data)
    {
        if (is_string($data)) {
            $length = strlen($data);
            // Overwrite with random data
            $data = random_bytes($length);
            // Overwrite with zeros
            $data = str_repeat("\0", $length);
            // Unset the variable
            unset($data);
        } elseif (is_array($data)) {
            foreach ($data as &$item) {
                $this->secureWipe($item);
            }
            $data = [];
        }
    }
    
    /**
     * Encrypt database field
     */
    public function encryptField($value, $tableName, $fieldName)
    {
        $context = $tableName . '.' . $fieldName;
        return $this->encrypt($value, $context);
    }
    
    /**
     * Decrypt database field
     */
    public function decryptField($encryptedValue, $tableName, $fieldName)
    {
        $context = $tableName . '.' . $fieldName;
        return $this->decrypt($encryptedValue, $context);
    }
    
    /**
     * Encrypt sensitive form data
     */
    public function encryptFormData($formData)
    {
        $sensitiveFields = [
            'password',
            'email',
            'phone',
            'address',
            'bank_account',
            'id_number',
            'passport',
            'social_security'
        ];
        
        $encrypted = [];
        
        foreach ($formData as $field => $value) {
            if (in_array($field, $sensitiveFields) && !empty($value)) {
                $encrypted[$field] = $this->encrypt($value, 'form.' . $field);
            } else {
                $encrypted[$field] = $value;
            }
        }
        
        return $encrypted;
    }
    
    /**
     * Decrypt sensitive form data
     */
    public function decryptFormData($encryptedData)
    {
        $sensitiveFields = [
            'email',
            'phone',
            'address',
            'bank_account',
            'id_number',
            'passport',
            'social_security'
        ];
        
        $decrypted = [];
        
        foreach ($encryptedData as $field => $value) {
            if (in_array($field, $sensitiveFields) && !empty($value)) {
                try {
                    $decrypted[$field] = $this->decrypt($value, 'form.' . $field);
                } catch (\Exception $e) {
                    // If decryption fails, field might not be encrypted
                    $decrypted[$field] = $value;
                }
            } else {
                $decrypted[$field] = $value;
            }
        }
        
        return $decrypted;
    }
    
    /**
     * Create encrypted backup of sensitive data
     */
    public function createEncryptedBackup($data, $backupId = null)
    {
        if (!$backupId) {
            $backupId = date('Ymd_His') . '_' . uniqid();
        }
        
        $context = 'backup.' . $backupId;
        return $this->encrypt(json_encode($data), $context);
    }
    
    /**
     * Restore from encrypted backup
     */
    public function restoreEncryptedBackup($encryptedData, $backupId)
    {
        $context = 'backup.' . $backupId;
        $decrypted = $this->decrypt($encryptedData, $context);
        
        return json_decode($decrypted, true);
    }
    
    /**
     * Generate secure API key
     */
    public function generateApiKey()
    {
        $timestamp = time();
        $randomData = random_bytes(16);
        $combined = $timestamp . $randomData;
        
        return 'ak_' . base64_encode($combined);
    }
    
    /**
     * Validate API key format
     */
    public function validateApiKeyFormat($apiKey)
    {
        return preg_match('/^ak_[A-Za-z0-9+\/=]+$/', $apiKey);
    }
}