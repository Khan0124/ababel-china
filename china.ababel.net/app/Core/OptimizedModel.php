<?php
/**
 * Optimized Model with Query Caching and Performance Improvements
 */

namespace App\Core;

class OptimizedModel extends Model
{
    protected $queryCache = [];
    protected $cacheEnabled = true;
    protected $cacheTTL = 300; // 5 minutes default
    protected $indexHints = [];
    
    public function __construct()
    {
        parent::__construct();
        $this->setupOptimizations();
    }
    
    /**
     * Setup database optimizations
     */
    private function setupOptimizations()
    {
        // Setup common index hints for this table
        $this->indexHints = $this->getIndexHints();
        
        // Enable query cache if not in debug mode
        $this->cacheEnabled = !Env::get('APP_DEBUG', false);
    }
    
    /**
     * Get index hints for common queries
     */
    protected function getIndexHints()
    {
        return [
            'by_date' => 'USE INDEX (idx_created_at)',
            'by_status' => 'USE INDEX (idx_status)',
            'by_client' => 'USE INDEX (idx_client_id)'
        ];
    }
    
    /**
     * Optimized find with caching
     */
    public function find($id, $useCache = true)
    {
        $cacheKey = $this->table . '_find_' . $id;
        
        if ($useCache && $this->cacheEnabled) {
            $cached = $this->getFromCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        $stmt = $this->db->query($sql, [$id]);
        $result = $stmt->fetch();
        
        if ($useCache && $this->cacheEnabled && $result) {
            $this->setCache($cacheKey, $result, $this->cacheTTL);
        }
        
        return $result;
    }
    
    /**
     * Optimized all() with pagination and caching
     */
    public function all($conditions = [], $orderBy = 'id', $limit = null, $offset = 0, $useCache = true)
    {
        $cacheKey = $this->generateCacheKey('all', $conditions, $orderBy, $limit, $offset);
        
        if ($useCache && $this->cacheEnabled) {
            $cached = $this->getFromCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        // Add index hints for common conditions
        $indexHint = $this->getIndexHintForConditions($conditions);
        if ($indexHint) {
            $sql .= " {$indexHint}";
        }
        
        // Build WHERE clause
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $column => $value) {
                if (is_array($value)) {
                    $placeholders = str_repeat('?,', count($value) - 1) . '?';
                    $whereClauses[] = "{$column} IN ({$placeholders})";
                    $params = array_merge($params, $value);
                } else {
                    $whereClauses[] = "{$column} = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        // Add ORDER BY
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        // Add LIMIT and OFFSET
        if ($limit !== null) {
            $limit = intval($limit);
            $offset = intval($offset);
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetchAll();
        
        if ($useCache && $this->cacheEnabled) {
            $this->setCache($cacheKey, $result, $this->cacheTTL);
        }
        
        return $result;
    }
    
    /**
     * Get count with caching
     */
    public function count($conditions = [], $useCache = true)
    {
        $cacheKey = $this->generateCacheKey('count', $conditions);
        
        if ($useCache && $this->cacheEnabled) {
            $cached = $this->getFromCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];
        
        // Add index hints
        $indexHint = $this->getIndexHintForConditions($conditions);
        if ($indexHint) {
            $sql .= " {$indexHint}";
        }
        
        // Build WHERE clause
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $column => $value) {
                $whereClauses[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetch();
        $count = intval($result['count']);
        
        if ($useCache && $this->cacheEnabled) {
            $this->setCache($cacheKey, $count, $this->cacheTTL);
        }
        
        return $count;
    }
    
    /**
     * Batch insert for improved performance
     */
    public function batchInsert($data, $batchSize = 1000)
    {
        if (empty($data)) {
            return [];
        }
        
        $insertedIds = [];
        $batches = array_chunk($data, $batchSize);
        
        foreach ($batches as $batch) {
            $columns = array_keys($batch[0]);
            $placeholders = '(' . str_repeat('?,', count($columns) - 1) . '?)';
            $values = str_repeat($placeholders . ',', count($batch) - 1) . $placeholders;
            
            $sql = "INSERT INTO {$this->table} (`" . implode('`, `', $columns) . "`) VALUES " . $values;
            
            $params = [];
            foreach ($batch as $row) {
                foreach ($columns as $column) {
                    $params[] = $row[$column] ?? null;
                }
            }
            
            $stmt = $this->db->query($sql, $params);
            
            // Get inserted IDs (approximate)
            $firstId = $this->db->lastInsertId();
            for ($i = 0; $i < count($batch); $i++) {
                $insertedIds[] = $firstId + $i;
            }
        }
        
        // Clear cache after batch insert
        $this->clearTableCache();
        
        return $insertedIds;
    }
    
    /**
     * Optimized update with cache invalidation
     */
    public function update($id, $data)
    {
        $result = parent::update($id, $data);
        
        if ($result) {
            // Clear related cache entries
            $this->clearRelatedCache($id);
        }
        
        return $result;
    }
    
    /**
     * Optimized delete with cache invalidation
     */
    public function delete($id)
    {
        $result = parent::delete($id);
        
        if ($result) {
            // Clear related cache entries
            $this->clearRelatedCache($id);
        }
        
        return $result;
    }
    
    /**
     * Execute raw SQL with optional caching
     */
    public function rawQuery($sql, $params = [], $useCache = false, $cacheTTL = 300)
    {
        if ($useCache && $this->cacheEnabled) {
            $cacheKey = md5($sql . serialize($params));
            $cached = $this->getFromCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetchAll();
        
        if ($useCache && $this->cacheEnabled) {
            $this->setCache($cacheKey, $result, $cacheTTL);
        }
        
        return $result;
    }
    
    /**
     * Get index hint for conditions
     */
    private function getIndexHintForConditions($conditions)
    {
        if (empty($conditions)) {
            return null;
        }
        
        // Check for common indexed columns
        if (isset($conditions['created_at']) || isset($conditions['updated_at'])) {
            return $this->indexHints['by_date'] ?? null;
        }
        
        if (isset($conditions['status'])) {
            return $this->indexHints['by_status'] ?? null;
        }
        
        if (isset($conditions['client_id'])) {
            return $this->indexHints['by_client'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Generate cache key
     */
    private function generateCacheKey($operation, ...$params)
    {
        return $this->table . '_' . $operation . '_' . md5(serialize($params));
    }
    
    /**
     * Get from cache
     */
    private function getFromCache($key)
    {
        $cacheFile = $this->getCacheFilePath($key);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        
        if (!$cacheData || $cacheData['expires'] < time()) {
            unlink($cacheFile);
            return null;
        }
        
        return $cacheData['data'];
    }
    
    /**
     * Set cache
     */
    private function setCache($key, $data, $ttl)
    {
        $cacheFile = $this->getCacheFilePath($key);
        $cacheDir = dirname($cacheFile);
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheData = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        file_put_contents($cacheFile, json_encode($cacheData), LOCK_EX);
    }
    
    /**
     * Get cache file path
     */
    private function getCacheFilePath($key)
    {
        $cacheDir = BASE_PATH . '/storage/cache/models/' . $this->table;
        return $cacheDir . '/' . md5($key) . '.cache';
    }
    
    /**
     * Clear related cache entries
     */
    private function clearRelatedCache($id = null)
    {
        $cacheDir = BASE_PATH . '/storage/cache/models/' . $this->table;
        
        if (!is_dir($cacheDir)) {
            return;
        }
        
        $files = glob($cacheDir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Clear all cache for this table
     */
    private function clearTableCache()
    {
        $this->clearRelatedCache();
    }
    
    /**
     * Get query execution statistics
     */
    public function getQueryStats()
    {
        $stats = [
            'total_queries' => 0,
            'cached_hits' => 0,
            'cache_hit_ratio' => 0,
            'avg_query_time' => 0
        ];
        
        // In a real implementation, you'd track these metrics
        return $stats;
    }
    
    /**
     * Analyze table for optimization suggestions
     */
    public function analyzeTable()
    {
        $sql = "ANALYZE TABLE {$this->table}";
        $this->db->query($sql);
        
        // Get table stats
        $sql = "SHOW TABLE STATUS LIKE '{$this->table}'";
        $stmt = $this->db->query($sql);
        $tableStats = $stmt->fetch();
        
        // Check for missing indexes
        $sql = "SHOW INDEX FROM {$this->table}";
        $stmt = $this->db->query($sql);
        $indexes = $stmt->fetchAll();
        
        return [
            'table_stats' => $tableStats,
            'indexes' => $indexes,
            'suggestions' => $this->generateOptimizationSuggestions($tableStats, $indexes)
        ];
    }
    
    /**
     * Generate optimization suggestions
     */
    private function generateOptimizationSuggestions($tableStats, $indexes)
    {
        $suggestions = [];
        
        // Check table size
        if ($tableStats['Data_length'] > 100000000) { // 100MB
            $suggestions[] = 'Consider partitioning this large table';
        }
        
        // Check for missing primary key
        $hasPrimaryKey = false;
        foreach ($indexes as $index) {
            if ($index['Key_name'] === 'PRIMARY') {
                $hasPrimaryKey = true;
                break;
            }
        }
        
        if (!$hasPrimaryKey) {
            $suggestions[] = 'Add a primary key to this table';
        }
        
        // Check index cardinality
        foreach ($indexes as $index) {
            if ($index['Cardinality'] < 10 && $index['Key_name'] !== 'PRIMARY') {
                $suggestions[] = "Index '{$index['Key_name']}' has low cardinality and may not be effective";
            }
        }
        
        return $suggestions;
    }
}