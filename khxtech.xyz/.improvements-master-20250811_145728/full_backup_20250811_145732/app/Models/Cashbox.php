<?php
namespace App\Models;

use App\Core\Model;

class Cashbox extends Model
{
    protected $table = 'cashbox_movements';
    
    public function getCurrentBalance()
    {
        $sql = "
            SELECT 
                SUM(CASE WHEN movement_type = 'in' THEN amount_rmb ELSE -amount_rmb END) as balance_rmb,
                SUM(CASE WHEN movement_type = 'in' THEN amount_usd ELSE -amount_usd END) as balance_usd,
                SUM(CASE WHEN movement_type = 'in' THEN amount_sdg ELSE -amount_sdg END) as balance_sdg,
                SUM(CASE WHEN movement_type = 'in' THEN amount_aed ELSE -amount_aed END) as balance_aed
            FROM cashbox_movements
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
    
    public function getMovements($startDate = null, $endDate = null, $category = null)
    {
        $sql = "
            SELECT cm.*, 
                   t.transaction_no,
                   t.description as transaction_description,
                   c.name as client_name,
                   u.full_name as created_by_name
            FROM cashbox_movements cm
            LEFT JOIN transactions t ON cm.transaction_id = t.id
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN users u ON cm.created_by = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($startDate) {
            $sql .= " AND cm.movement_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND cm.movement_date <= ?";
            $params[] = $endDate;
        }
        
        if ($category) {
            $sql .= " AND cm.category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY cm.movement_date DESC, cm.id DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function getDailySummary($date)
    {
        $sql = "
            SELECT 
                movement_type,
                category,
                COUNT(*) as count,
                SUM(amount_rmb) as total_rmb,
                SUM(amount_usd) as total_usd,
                SUM(amount_sdg) as total_sdg
            FROM cashbox_movements
            WHERE movement_date = ?
            GROUP BY movement_type, category
        ";
        
        $stmt = $this->db->query($sql, [$date]);
        return $stmt->fetchAll();
    }
}