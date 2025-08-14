<?php
// app/Core/helpers.php
// This file contains global helper functions

use App\Core\Language;

if (!function_exists('__')) {
    /**
     * Get translation for a key
     * @param string $key Translation key
     * @param array $params Parameters to replace
     * @return string
     */
    function __($key, $params = [])
    {
        return Language::getInstance()->get($key, $params);
    }
}

if (!function_exists('lang')) {
    /**
     * Get current language code
     * @return string
     */
    function lang()
    {
        return Language::getInstance()->getCurrentLanguage();
    }
}

if (!function_exists('isRTL')) {
    /**
     * Check if current language is RTL
     * @return bool
     */
    function isRTL()
    {
        return Language::getInstance()->isRTL();
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config($key, $default = null)
    {
        static $config = null;
        
        if ($config === null) {
            $config = require BASE_PATH . '/config/app.php';
        }
        
        return $config[$key] ?? $default;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     * @param string $path
     * @return string
     */
    function asset($path)
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL
     * @param string $path
     * @return string
     */
    function url($path = '')
    {
        $baseUrl = config('url', '');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}