<?php
// app/Models/Loading.php
namespace App\Models;

use App\Core\Model;

class Loading extends Model
{
    protected $table = 'loadings';
    
    /**
     * Check if loading number exists within the fiscal year
     */
    public function isDuplicateLoadingNumber($loadingNo, $excludeId = null)
    {
        $currentDate = new \DateTime();
        $currentYear = (int)$currentDate->format('Y');
        $currentMonth = (int)$currentDate->format('n');
        
        // Determine fiscal year boundaries
        if ($currentMonth >= 3) {
            // March to December: fiscal year started this year March 1
            $fiscalYearStart = $currentYear . '-03-01';
            $fiscalYearEnd = ($currentYear + 1) . '-02-28';
            // Check for leap year
            if (checkdate(2, 29, $currentYear + 1)) {
                $fiscalYearEnd = ($currentYear + 1) . '-02-29';
            }
        } else {
            // January to February: fiscal year started last year March 1
            $fiscalYearStart = ($currentYear - 1) . '-03-01';
            $fiscalYearEnd = $currentYear . '-02-28';
            // Check for leap year
            if (checkdate(2, 29, $currentYear)) {
                $fiscalYearEnd = $currentYear . '-02-29';
            }
        }
        
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE loading_no = ? 
                AND shipping_date >= ? 
                AND shipping_date <= ?";
        
        $params = [$loadingNo, $fiscalYearStart, $fiscalYearEnd];
        
        // Exclude current record when updating
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    /**
     * Generate unique loading number for the fiscal year
     * Fiscal year starts March 1st
     */
    public function generateLoadingNumber()
    {
        $currentDate = new \DateTime();
        $currentYear = (int)$currentDate->format('Y');
        $currentMonth = (int)$currentDate->format('n');
        
        // Determine fiscal year
        if ($currentMonth >= 3) {
            // March to December: fiscal year is current year
            $fiscalYear = $currentYear . '-' . ($currentYear + 1);
        } else {
            // January to February: fiscal year is previous year
            $fiscalYear = ($currentYear - 1) . '-' . $currentYear;
        }
        
        // Start transaction to ensure atomicity
        $this->db->getConnection()->beginTransaction();
        
        try {
            // Check if fiscal year exists
            $stmt = $this->db->query(
                "SELECT last_loading_no FROM fiscal_year_sequences WHERE fiscal_year = ? FOR UPDATE",
                [$fiscalYear]
            );
            $sequence = $stmt->fetch();
            
            if (!$sequence) {
                // Create new fiscal year sequence
                $this->db->query(
                    "INSERT INTO fiscal_year_sequences (fiscal_year, last_loading_no) VALUES (?, 0)",
                    [$fiscalYear]
                );
                $nextNumber = 1;
            } else {
                $nextNumber = $sequence['last_loading_no'] + 1;
            }
            
            // Update the sequence
            $this->db->query(
                "UPDATE fiscal_year_sequences SET last_loading_no = ? WHERE fiscal_year = ?",
                [$nextNumber, $fiscalYear]
            );
            
            $this->db->getConnection()->commit();
            
            return $nextNumber;
            
        } catch (\Exception $e) {
            $this->db->getConnection()->rollback();
            throw $e;
        }
    }
    
    /**
     * Create new loading with database-verified structure
     */
    public function create($data)
    {
        // Dynamically check which columns exist in the loadings table
        $columns = $this->getTableColumns();
        
        // Build INSERT query based on available columns
        $insertColumns = [];
        $insertValues = [];
        $params = [];
        
        // Column mapping from form data to database columns
        $columnMapping = [
            'shipping_date' => $data['shipping_date'],
            'loading_no' => $data['loading_no'],
            'claim_number' => $data['claim_number'],
            'container_no' => $data['container_no'],
            'client_id' => $data['client_id'],
            'client_code' => $data['client_code'],
            'client_name' => $data['client_name'],
            'item_description' => $data['item_description'],
            'cartons_count' => $data['cartons_count'],
            'purchase_amount' => $data['purchase_amount'],
            'commission_amount' => $data['commission_amount'],
            'total_amount' => $data['total_amount'],
            'shipping_usd' => $data['shipping_usd'],
            'total_with_shipping' => $data['total_with_shipping'],
            'office' => $data['office'],
            'notes' => $data['notes'],
            'status' => $data['status'],
            'created_by' => $data['created_by'],
            'created_at' => 'NOW()',
            'updated_at' => 'NOW()'
        ];
        
        // Only include columns that exist in the table
        foreach ($columnMapping as $column => $value) {
            if (in_array($column, $columns)) {
                $insertColumns[] = $column;
                if ($column === 'created_at' || $column === 'updated_at') {
                    $insertValues[] = 'NOW()';
                } else {
                    $insertValues[] = '?';
                    $params[] = $value;
                }
            }
        }
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $insertColumns) . ") 
                VALUES (" . implode(', ', $insertValues) . ")";
        
        $stmt = $this->db->query($sql, $params);
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Update loading with database-verified structure
     */
    public function update($id, $data)
    {
        $columns = $this->getTableColumns();
        $fields = [];
        $params = [];
        
        // Column mapping for updates
        $allowedFields = [
            'shipping_date', 'loading_no', 'container_no', 'client_id', 
            'client_code', 'client_name', 'item_description', 'cartons_count',
            'purchase_amount', 'commission_amount', 'total_amount', 'shipping_usd',
            'total_with_shipping', 'office', 'notes', 'status', 'updated_by'
        ];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields) && in_array($field, $columns)) {
                $fields[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        // Add updated_at if column exists
        if (in_array('updated_at', $columns)) {
            $fields[] = "updated_at = NOW()";
        }
        
        $params[] = $id;
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Get table columns dynamically
     */
    private function getTableColumns()
    {
        static $columns = null;
        
        if ($columns === null) {
            try {
                $stmt = $this->db->query("DESCRIBE {$this->table}");
                $result = $stmt->fetchAll();
                $columns = array_column($result, 'Field');
            } catch (\Exception $e) {
                // Fallback to common columns if DESCRIBE fails
                $columns = [
                    'id', 'shipping_date', 'loading_no', 'claim_number', 'container_no',
                    'client_id', 'client_code', 'client_name', 'item_description',
                    'cartons_count', 'purchase_amount', 'commission_amount', 'total_amount',
                    'shipping_usd', 'total_with_shipping', 'office', 'notes', 'status',
                    'created_by', 'updated_by', 'created_at', 'updated_at'
                ];
            }
        }
        
        return $columns;
    }
    
    /**
     * Get loading with client details - Production version
     */
    public function getWithDetails($id)
    {
        // Check if users table exists for joins
        $userJoin = $this->tableExists('users') ? 
            "LEFT JOIN users u1 ON l.created_by = u1.id
             LEFT JOIN users u2 ON l.updated_by = u2.id" : "";
        
        $userFields = $this->tableExists('users') ?
            "u1.full_name as created_by_name,
             u2.full_name as updated_by_name," : "";
        
        $sql = "SELECT l.*, 
                c.name as client_name_db, 
                c.name_ar as client_name_ar,
                c.phone as client_phone,
                c.balance_rmb,
                {$userFields}
                CASE 
                    WHEN l.office = 'port_sudan' THEN 'Port Sudan'
                    WHEN l.office = 'uae' THEN 'UAE'
                    WHEN l.office = 'tanzania' THEN 'Tanzania'
                    WHEN l.office = 'egypt' THEN 'Egypt'
                    ELSE 'No Office'
                END as office_display
                FROM {$this->table} l
                LEFT JOIN clients c ON l.client_id = c.id
                {$userJoin}
                WHERE l.id = ?";
        
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get filtered loadings - Production version
     */
    public function getFiltered($filters = [])
    {
        $userJoin = $this->tableExists('users') ? 
            "LEFT JOIN users u ON l.created_by = u.id" : "";
        
        $userField = $this->tableExists('users') ?
            "u.full_name as created_by_name," : "";
        
        $sql = "SELECT l.*, 
                c.name as client_name_db, 
                c.name_ar as client_name_ar,
                {$userField}
                CASE 
                    WHEN l.office = 'port_sudan' THEN 'Port Sudan'
                    WHEN l.office = 'uae' THEN 'UAE'
                    WHEN l.office = 'tanzania' THEN 'Tanzania'
                    WHEN l.office = 'egypt' THEN 'Egypt'
                    ELSE 'No Office'
                END as office_display
                FROM {$this->table} l
                LEFT JOIN clients c ON l.client_id = c.id
                {$userJoin}
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND l.shipping_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND l.shipping_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['client_code'])) {
            $sql .= " AND l.client_code LIKE ?";
            $params[] = '%' . $filters['client_code'] . '%';
        }
        
        if (!empty($filters['container_no'])) {
            $sql .= " AND l.container_no LIKE ?";
            $params[] = '%' . $filters['container_no'] . '%';
        }
        
        if (!empty($filters['loading_no'])) {
            $sql .= " AND l.loading_no LIKE ?";
            $params[] = '%' . $filters['loading_no'] . '%';
        }
        
        if (!empty($filters['office'])) {
            $sql .= " AND l.office = ?";
            $params[] = $filters['office'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND l.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY l.shipping_date DESC, l.id DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName)
    {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE ?", [$tableName]);
            return $stmt->fetch() !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get loadings by office
     */
    public function getByOffice($office, $status = null)
    {
        $sql = "SELECT l.*, 
                c.name as client_name_db, 
                c.name_ar as client_name_ar
                FROM {$this->table} l
                LEFT JOIN clients c ON l.client_id = c.id
                WHERE l.office = ?";
        
        $params = [$office];
        
        if ($status) {
            $sql .= " AND l.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY l.shipping_date DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get statistics for dashboard
     */
    public function getStatistics($dateFrom = null, $dateTo = null)
    {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($dateFrom) {
            $whereClause .= " AND shipping_date >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereClause .= " AND shipping_date <= ?";
            $params[] = $dateTo;
        }
        
        $sql = "SELECT 
                COUNT(*) as total_loadings,
                SUM(cartons_count) as total_cartons,
                SUM(purchase_amount) as total_purchase,
                SUM(commission_amount) as total_commission,
                SUM(shipping_usd) as total_shipping_usd,
                SUM(total_with_shipping) as total_amount,
                COUNT(CASE WHEN office = 'port_sudan' THEN 1 END) as port_sudan_count,
                COUNT(CASE WHEN office = 'uae' THEN 1 END) as uae_count,
                COUNT(CASE WHEN office = 'tanzania' THEN 1 END) as tanzania_count,
                COUNT(CASE WHEN office = 'egypt' THEN 1 END) as egypt_count,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'shipped' THEN 1 END) as shipped_count,
                COUNT(CASE WHEN status = 'arrived' THEN 1 END) as arrived_count,
                COUNT(CASE WHEN status = 'cleared' THEN 1 END) as cleared_count
                FROM {$this->table} {$whereClause}";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Delete loading - Production version with proper cleanup
     */
    public function delete($id)
    {
        // Get loading details first
        $loading = $this->find($id);
        if (!$loading) {
            return false;
        }
        
        // Start transaction
        $this->db->getConnection()->beginTransaction();
        
        try {
            // Delete related records from existing tables only
            
            // Delete cashbox entries if table exists
            if ($this->tableExists('cashbox')) {
                $this->db->query("DELETE FROM cashbox WHERE type LIKE '%Loading: {$loading['container_no']}%'", []);
            }
            
            // Delete cashbox_movements if table exists  
            if ($this->tableExists('cashbox_movements')) {
                $this->db->query("DELETE FROM cashbox_movements WHERE reference_type = 'loading' AND reference_id = ?", [$id]);
            }
            
            // Delete sync logs if table exists
            if ($this->tableExists('api_sync_log')) {
                $this->db->query("DELETE FROM api_sync_log WHERE china_loading_id = ?", [$id]);
            }
            
            // Delete office notifications if table exists
            if ($this->tableExists('office_notifications')) {
                $this->db->query("DELETE FROM office_notifications WHERE reference_type = 'loading' AND reference_id = ?", [$id]);
            }
            
            // Delete the loading itself
            $result = parent::delete($id);
            
            $this->db->getConnection()->commit();
            return $result;
            
        } catch (\Exception $e) {
            $this->db->getConnection()->rollback();
            throw $e;
        }
    }
}