<?php
namespace App\Models;

use App\Core\Model;

class Client extends Model
{
    protected $table = 'clients';
    
public function getWithBalance($limit = 10)
{
    $sql = "SELECT *, 
            balance_rmb,
            balance_usd,
            balance_sdg
            FROM {$this->table}
            WHERE status = 'active'
            ORDER BY (balance_rmb + (balance_usd * 7.2)) DESC
            LIMIT ?";
    
    $stmt = $this->db->query($sql, [$limit]);
    return $stmt->fetchAll();
}

/**
 * Update client balance
 */
public function updateBalance($clientId, $balanceRmb = null, $balanceUsd = null, $balanceSdg = null)
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
    
    if (empty($updates)) {
        return false;
    }
    
    $params[] = $clientId;
    $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";
    
    return $this->db->query($sql, $params)->rowCount() > 0;
}
    public function findByCode($code)
{
    $sql = "SELECT * FROM {$this->table} WHERE client_code = ? AND status = 'active'";
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
    $sql = "SELECT c.*, 
            COALESCE(COUNT(t.id), 0) as transaction_count
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