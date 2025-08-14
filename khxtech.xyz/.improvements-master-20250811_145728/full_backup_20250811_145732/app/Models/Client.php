<?php
namespace App\Models;

use App\Core\Model;

class Client extends Model
{
    protected $table = 'clients';
    
    /**
     * Count clients with filters
     */
    public function countWithFilters($status = 'active', $search = '')
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        if ($search) {
            $sql .= " AND (name LIKE ? OR name_ar LIKE ? OR client_code LIKE ? OR phone LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    /**
     * Get all clients with transaction count - paginated
     */
    public function allWithTransactionCountPaginated($filters = [])
    {
        $sql = "SELECT c.*, 
                COALESCE(c.balance_rmb, 0) as balance_rmb,
                COALESCE(c.balance_usd, 0) as balance_usd,
                COALESCE(c.balance_sdg, 0) as balance_sdg,
                COALESCE(c.balance_aed, 0) as balance_aed,
                COUNT(DISTINCT t.id) as transaction_count
                FROM {$this->table} c
                LEFT JOIN transactions t ON c.id = t.client_id
                WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['search']) && $filters['search']) {
            $sql .= " AND (c.name LIKE ? OR c.name_ar LIKE ? OR c.client_code LIKE ? OR c.phone LIKE ?)";
            $searchParam = "%{$filters['search']}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $sql .= " GROUP BY c.id ORDER BY c.created_at DESC";
        
        if (isset($filters['limit'])) {
            if (isset($filters['offset'])) {
                $sql .= " LIMIT " . (int)$filters['offset'] . ", " . (int)$filters['limit'];
            } else {
                $sql .= " LIMIT " . (int)$filters['limit'];
            }
        }
        
        $stmt = $this->db->query($sql, $params, true, 300); // Use cache
        return $stmt->fetchAll();
    }
    
public function getWithBalance($limit = 10)
{
    $sql = "SELECT id, client_code, name, name_ar, phone, email, address, credit_limit, status, created_at, updated_at,
            COALESCE(balance_rmb, 0) as balance_rmb,
            COALESCE(balance_usd, 0) as balance_usd,
            COALESCE(balance_sdg, 0) as balance_sdg,
            COALESCE(balance_aed, 0) as balance_aed
            FROM {$this->table}
            WHERE status = 'active'
            ORDER BY (COALESCE(balance_rmb, 0) + (COALESCE(balance_usd, 0) * 7.2)) DESC
            LIMIT ?";
    
    $stmt = $this->db->query($sql, [$limit]);
    return $stmt->fetchAll();
}

/**
 * Update client balance
 */
public function updateBalance($clientId, $balanceRmb = null, $balanceUsd = null, $balanceSdg = null, $balanceAed = null)
{
    $updates = [];
    $params = [];
    
    if ($balanceRmb !== null) {
        $updates[] = "balance_rmb = ?";
        $params[] = $balanceRmb;
    }
    
    if ($balanceUsd !== null) {
        $updates[] = "balance_usd = ?";
        $params[] = $balanceUsd;
    }
    
    if ($balanceSdg !== null) {
        $updates[] = "balance_sdg = ?";
        $params[] = $balanceSdg;
    }
    
    if ($balanceAed !== null) {
        $updates[] = "balance_aed = ?";
        $params[] = $balanceAed;
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $params[] = $clientId;
    $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";
    
    return $this->db->query($sql, $params)->rowCount() > 0;
}
    public function find($id)
    {
        $sql = "SELECT id, client_code, name, name_ar, phone, email, address, credit_limit, status, created_at, updated_at,
                COALESCE(balance_rmb, 0) as balance_rmb,
                COALESCE(balance_usd, 0) as balance_usd,
                COALESCE(balance_sdg, 0) as balance_sdg,
                COALESCE(balance_aed, 0) as balance_aed
                FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }

    public function findByCode($code)
{
    $sql = "SELECT id, client_code, name, name_ar, phone, email, address, credit_limit, status, created_at, updated_at,
            COALESCE(balance_rmb, 0) as balance_rmb,
            COALESCE(balance_usd, 0) as balance_usd,
            COALESCE(balance_sdg, 0) as balance_sdg,
            COALESCE(balance_aed, 0) as balance_aed
            FROM {$this->table} WHERE client_code = ? AND status = 'active'";
    $stmt = $this->db->query($sql, [$code]);
    return $stmt->fetch();
}
    
    public function getStatement($clientId, $startDate = null, $endDate = null)
    {
        $sql = "
            SELECT t.*, tt.name as transaction_type_name
            FROM transactions t
            JOIN transaction_types tt ON t.transaction_type_id = tt.id
            WHERE t.client_id = ? AND t.status = 'approved'
        ";
        
        $params = [$clientId];
        
        if ($startDate) {
            $sql .= " AND t.transaction_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND t.transaction_date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY t.transaction_date, t.id";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    /**
 * Get all clients with transaction count
 */
public function allWithTransactionCount($conditions = [], $orderBy = null, $limit = null)
{
    $sql = "SELECT c.id, c.client_code, c.name, c.name_ar, c.phone, c.email, c.address, c.credit_limit, c.status, c.created_at, c.updated_at,
            COALESCE(COUNT(t.id), 0) as transaction_count,
            COALESCE(c.balance_rmb, 0) as balance_rmb,
            COALESCE(c.balance_usd, 0) as balance_usd,
            COALESCE(c.balance_sdg, 0) as balance_sdg,
            COALESCE(c.balance_aed, 0) as balance_aed
            FROM {$this->table} c
            LEFT JOIN transactions t ON c.id = t.client_id";
    
    $params = [];
    
    if (!empty($conditions)) {
        $whereClause = [];
        foreach ($conditions as $field => $value) {
            // Handle table prefix for conditions
            $tablePrefix = strpos($field, '.') !== false ? '' : 'c.';
            $whereClause[] = "$tablePrefix$field = ?";
            $params[] = $value;
        }
        $sql .= " WHERE " . implode(" AND ", $whereClause);
    }
    
    $sql .= " GROUP BY c.id";
    
    if ($orderBy) {
        $sql .= " ORDER BY $orderBy";
    } else {
        $sql .= " ORDER BY c.name";
    }
    
    if ($limit) {
        $sql .= " LIMIT $limit";
    }
    
    $stmt = $this->db->query($sql, $params);
    return $stmt->fetchAll();
}
}