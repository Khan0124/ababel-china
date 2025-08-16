<?php
namespace App\Core;

class Validator
{
    private $data = [];
    private $errors = [];
    private $rules = [];
    
    /**
     * Constructor
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }
    
    /**
     * Validate data against rules
     */
    public function validate($rules)
    {
        $this->rules = $rules;
        $this->errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;
            $rulesArray = explode('|', $fieldRules);
            
            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply validation rule
     */
    private function applyRule($field, $value, $rule)
    {
        $params = [];
        
        // Check if rule has parameters
        if (strpos($rule, ':') !== false) {
            list($rule, $paramString) = explode(':', $rule, 2);
            $params = explode(',', $paramString);
        }
        
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, "The $field field is required.");
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The $field field must be a valid email address.");
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "The $field field must be numeric.");
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "The $field field must be an integer.");
                }
                break;
                
            case 'decimal':
                if (!empty($value) && !preg_match('/^\d+(\.\d+)?$/', $value)) {
                    $this->addError($field, "The $field field must be a decimal number.");
                }
                break;
                
            case 'min':
                $min = $params[0] ?? 0;
                if (!empty($value)) {
                    if (is_numeric($value) && $value < $min) {
                        $this->addError($field, "The $field field must be at least $min.");
                    } elseif (is_string($value) && strlen($value) < $min) {
                        $this->addError($field, "The $field field must be at least $min characters.");
                    }
                }
                break;
                
            case 'max':
                $max = $params[0] ?? PHP_INT_MAX;
                if (!empty($value)) {
                    if (is_numeric($value) && $value > $max) {
                        $this->addError($field, "The $field field must not exceed $max.");
                    } elseif (is_string($value) && strlen($value) > $max) {
                        $this->addError($field, "The $field field must not exceed $max characters.");
                    }
                }
                break;
                
            case 'in':
                if (!empty($value) && !in_array($value, $params)) {
                    $this->addError($field, "The $field field must be one of: " . implode(', ', $params));
                }
                break;
                
            case 'date':
                if (!empty($value)) {
                    $date = \DateTime::createFromFormat('Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value) {
                        $this->addError($field, "The $field field must be a valid date (YYYY-MM-DD).");
                    }
                }
                break;
                
            case 'alpha':
                if (!empty($value) && !preg_match('/^[a-zA-Z]+$/', $value)) {
                    $this->addError($field, "The $field field must contain only letters.");
                }
                break;
                
            case 'alphanumeric':
                if (!empty($value) && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                    $this->addError($field, "The $field field must contain only letters and numbers.");
                }
                break;
                
            case 'unique':
                // This would require database check
                // Implement based on your needs
                break;
                
            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if ($value !== ($this->data[$confirmField] ?? null)) {
                    $this->addError($field, "The $field confirmation does not match.");
                }
                break;
                
            case 'regex':
                $pattern = $params[0] ?? '';
                if (!empty($value) && !preg_match($pattern, $value)) {
                    $this->addError($field, "The $field field format is invalid.");
                }
                break;
        }
    }
    
    /**
     * Add error message
     */
    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get all errors
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /**
     * Get errors for specific field
     */
    public function getFieldErrors($field)
    {
        return $this->errors[$field] ?? [];
    }
    
    /**
     * Check if validation failed
     */
    public function fails()
    {
        return !empty($this->errors);
    }
    
    /**
     * Check if validation passed
     */
    public function passes()
    {
        return empty($this->errors);
    }
    
    /**
     * Get first error message
     */
    public function firstError()
    {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        return null;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitize($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        // Remove whitespace
        $data = trim($data);
        
        // Remove backslashes
        $data = stripslashes($data);
        
        // Convert special characters to HTML entities
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        
        return $data;
    }
    
    /**
     * Sanitize for database insertion
     */
    public static function sanitizeForDb($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeForDb'], $data);
        }
        
        // Remove whitespace
        $data = trim($data);
        
        // Remove backslashes
        $data = stripslashes($data);
        
        return $data;
    }
}