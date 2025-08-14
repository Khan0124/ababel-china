<?php
// app/Core/Language.php
namespace App\Core;

class Language
{
    private static $instance = null;
    private $currentLang = 'ar';
    private $translations = [];
    private $availableLanguages = ['ar' => 'العربية', 'en' => 'English'];
    
    private function __construct()
    {
        // Get language from session or default
        $this->currentLang = $_SESSION['lang'] ?? 'ar';
        $this->loadTranslations();
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function setLanguage($lang)
    {
        if (array_key_exists($lang, $this->availableLanguages)) {
            $this->currentLang = $lang;
            $_SESSION['lang'] = $lang;
            $this->loadTranslations();
        }
    }
    
    public function getCurrentLanguage()
    {
        return $this->currentLang;
    }
    
    public function getAvailableLanguages()
    {
        return $this->availableLanguages;
    }
    
    public function isRTL()
    {
        return in_array($this->currentLang, ['ar', 'fa', 'he', 'ur']);
    }
    
    private function loadTranslations()
    {
        $langFile = BASE_PATH . "/lang/{$this->currentLang}.php";
        if (file_exists($langFile)) {
            $this->translations = require $langFile;
        }
    }
    
    public function get($key, $params = [])
    {
        $keys = explode('.', $key);
        $value = $this->translations;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $key; // Return key if translation not found
            }
        }
        
        // Replace parameters
        if (!empty($params)) {
            foreach ($params as $param => $val) {
                $value = str_replace(':' . $param, $val, $value);
            }
        }
        
        return $value;
    }
}

// Helper functions - IMPORTANT: These are in the global namespace, not App\Core namespace

if (!function_exists('__')) {
    function __($key, $params = [])
    {
        return \App\Core\Language::getInstance()->get($key, $params);
    }
}

if (!function_exists('lang')) {
    function lang()
    {
        return \App\Core\Language::getInstance()->getCurrentLanguage();
    }
}

if (!function_exists('isRTL')) {
    function isRTL()
    {
        return \App\Core\Language::getInstance()->isRTL();
    }
}