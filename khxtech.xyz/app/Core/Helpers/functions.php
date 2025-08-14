<?php
/**
 * Global helper functions
 * PHP 8.3 compatibility fixes
 */

if (!function_exists('h')) {
    /**
     * Safe htmlspecialchars wrapper for PHP 8.3
     * Handles null values to prevent deprecation warnings
     */
    function h($string, $flags = ENT_QUOTES, $encoding = 'UTF-8', $double_encode = true) {
        // Convert null to empty string to avoid PHP 8.3 deprecation warning
        if ($string === null) {
            return '';
        }
        return htmlspecialchars($string, $flags, $encoding, $double_encode);
    }
}

if (!function_exists('e')) {
    /**
     * Alias for h() function - escape HTML
     */
    function e($string, $flags = ENT_QUOTES, $encoding = 'UTF-8', $double_encode = true) {
        return h($string, $flags, $encoding, $double_encode);
    }
}

if (!function_exists('safe_trim')) {
    /**
     * Safe trim wrapper for PHP 8.3
     */
    function safe_trim($string) {
        return $string === null ? '' : trim($string);
    }
}

if (!function_exists('safe_strlen')) {
    /**
     * Safe strlen wrapper for PHP 8.3
     */
    function safe_strlen($string) {
        return $string === null ? 0 : strlen($string);
    }
}

if (!function_exists('safe_strtolower')) {
    /**
     * Safe strtolower wrapper for PHP 8.3
     */
    function safe_strtolower($string) {
        return $string === null ? '' : strtolower($string);
    }
}

if (!function_exists('safe_strtoupper')) {
    /**
     * Safe strtoupper wrapper for PHP 8.3
     */
    function safe_strtoupper($string) {
        return $string === null ? '' : strtoupper($string);
    }
}

if (!function_exists('safe_echo')) {
    /**
     * Safe echo wrapper that prevents array to string conversion
     */
    function safe_echo($value) {
        if (is_array($value)) {
            return '[ARRAY]';
        }
        if (is_object($value)) {
            return '[OBJECT]';
        }
        if ($value === null) {
            return '';
        }
        return (string) $value;
    }
}