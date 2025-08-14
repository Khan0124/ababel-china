<?php
namespace App\Core;

class Cache
{
    private static $cacheDir = __DIR__ . '/../../cache/';
    private static $defaultExpiry = 3600; // 1 hour default
    
    public static function init()
    {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    public static function get($key)
    {
        self::init();
        $filename = self::$cacheDir . md5($key) . '.cache';
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($filename));
        
        if ($data['expiry'] < time()) {
            unlink($filename);
            return null;
        }
        
        return $data['value'];
    }
    
    public static function set($key, $value, $expiry = null)
    {
        self::init();
        $expiry = $expiry ?: self::$defaultExpiry;
        $filename = self::$cacheDir . md5($key) . '.cache';
        
        $data = [
            'value' => $value,
            'expiry' => time() + $expiry
        ];
        
        file_put_contents($filename, serialize($data));
        return true;
    }
    
    public static function delete($key)
    {
        self::init();
        $filename = self::$cacheDir . md5($key) . '.cache';
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return false;
    }
    
    public static function clear()
    {
        self::init();
        $files = glob(self::$cacheDir . '*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    public static function remember($key, $callback, $expiry = null)
    {
        $value = self::get($key);
        
        if ($value === null) {
            $value = $callback();
            self::set($key, $value, $expiry);
        }
        
        return $value;
    }
}