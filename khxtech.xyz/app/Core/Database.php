<?php
namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $connection;
    private $queryCache = [];
    private $cacheEnabled = true;
    
    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';
        
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            
            // Enhanced PDO options for better performance
            $enhancedOptions = array_merge($config['options'], [
                PDO::ATTR_PERSISTENT => true, // Connection pooling
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode='TRADITIONAL'",
            ]);
            
            $this->connection = new PDO($dsn, $config['username'], $config['password'], $enhancedOptions);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection()
    {
        return $this->connection;
    }
    
    public function query($sql, $params = [], $useCache = false, $cacheTime = 300)
    {
        // Generate cache key for SELECT queries
        if ($useCache && stripos(trim($sql), 'SELECT') === 0) {
            $cacheKey = md5($sql . serialize($params));
            
            // Check cache first
            if (isset($this->queryCache[$cacheKey])) {
                $cached = $this->queryCache[$cacheKey];
                if ($cached['expires'] > time()) {
                    return $cached['data'];
                }
                unset($this->queryCache[$cacheKey]);
            }
        }
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        
        // Cache the result for SELECT queries
        if ($useCache && isset($cacheKey)) {
            $this->queryCache[$cacheKey] = [
                'data' => $stmt,
                'expires' => time() + $cacheTime
            ];
        }
        
        return $stmt;
    }
    
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit()
    {
        return $this->connection->commit();
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback()
    {
        return $this->connection->rollBack();
    }
    
    /**
     * Check if in transaction
     */
    public function inTransaction()
    {
        return $this->connection->inTransaction();
    }
}