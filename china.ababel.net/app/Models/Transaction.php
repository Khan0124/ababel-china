<?php
namespace App\Models;

use App\Core\Model;

class Transaction extends Model
{
    protected $table = 'transactions';
    
    public function getWithDetails($id)
    {
        $sql = "
            SELECT t.*, 
                   c.name as client_name, 
                   c.client_code,
                   tt.name as transaction_type_name,
                   u.full_name as created_by_name
            FROM transactions t
            LEFT JOIN clients c ON t.client_id = c.id
            JOIN transaction_types tt ON t.transaction_type_id = tt.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.id = ?
        ";
        
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    public function getLatest($limit = 10)
    {
        $sql = "
            SELECT t.*, 
                   c.name as client_name,
                   tt.name as transaction_type_name
            FROM transactions t
            LEFT JOIN clients c ON t.client_id = c.id
            JOIN transaction_types tt ON t.transaction_type_id = tt.id
            ORDER BY t.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->query($sql, [$limit]);
        return $stmt->fetchAll();
    }
    
    public function generateTransactionNo()
    {
        $year = date('Y');
        $sql = "SELECT MAX(CAST(SUBSTRING(transaction_no, 6) AS UNSIGNED)) as max_no 
                FROM transactions 
                WHERE transaction_no LIKE ?";
        
        $stmt = $this->db->query($sql, ["TRX-$year-%"]);
        $result = $stmt->fetch();
        
        $nextNo = ($result['max_no'] ?? 0) + 1;
        return sprintf("TRX-%s-%06d", $year, $nextNo);
    }
    
    public function createWithCashbox($transactionData, $cashboxData = null)
    {
        $conn = $this->db->getConnection();
        
        try {
            $conn->beginTransaction();
            
            // Create transaction
            $transactionId = $this->create($transactionData);
            
            // Create cashbox movement if needed
            if ($cashboxData && !empty($cashboxData)) {
                $cashbox = new Cashbox();
                $cashboxData['transaction_id'] = $transactionId;
                $cashbox->create($cashboxData);
            }
            
            // Update client balance
            if (isset($transactionData['client_id'])) {
                $this->updateClientBalance($transactionData['client_id']);
            }
            
            $conn->commit();
            return $transactionId;
            
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
    
    private function updateClientBalance($clientId)
    {
        $sql = "
            UPDATE clients c
            SET 
                balance_rmb = (
                    SELECT COALESCE(SUM(balance_rmb), 0) 
                    FROM transactions 
                    WHERE client_id = c.id AND status = 'approved'
                ),
                balance_usd = (
                    SELECT COALESCE(SUM(balance_usd), 0) 
                    FROM transactions 
                    WHERE client_id = c.id AND status = 'approved'
                )
            WHERE c.id = ?
        ";
        
        $this->db->query($sql, [$clientId]);
    }
    /**
 * Get transactions by loading ID
 */
public function getByLoadingId($loadingId)
{
    $sql = "SELECT t.*, 
            tt.name_en as transaction_type_name,
            c.client_code,
            c.name as client_name,
            c.name_ar as client_name_ar
            FROM {$this->table} t
            LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
            LEFT JOIN clients c ON t.client_id = c.id
            WHERE t.loading_id = ?
            ORDER BY t.transaction_date DESC, t.id DESC";
    
    $stmt = $this->db->query($sql, [$loadingId]);
    return $stmt->fetchAll();
}

/**
 * Get transactions with filters including claim number
 */
public function getWithFilters($filters = [])
{
    $sql = "SELECT t.*, 
            tt.name_en as transaction_type_name,
            c.client_code,
            c.name as client_name,
            c.name_ar as client_name_ar,
            l.claim_number,
            l.container_no
            FROM {$this->table} t
            LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN loadings l ON t.loading_id = l.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND t.transaction_date >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND t.transaction_date <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['client_id'])) {
        $sql .= " AND t.client_id = ?";
        $params[] = $filters['client_id'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND t.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['loading_id'])) {
        $sql .= " AND t.loading_id = ?";
        $params[] = $filters['loading_id'];
    }
    
    // Add support for AED currency in balance calculation
    $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";
    
    $stmt = $this->db->query($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Calculate total balances by currency for a client
 */
public function getClientBalancesByCurrency($clientId)
{
    $sql = "SELECT 
            SUM(balance_rmb) as total_balance_rmb,
            SUM(balance_usd) as total_balance_usd,
            SUM(balance_sdg) as total_balance_sdg,
            SUM(balance_aed) as total_balance_aed
            FROM {$this->table}
            WHERE client_id = ? AND status = 'approved'";
    
    $stmt = $this->db->query($sql, [$clientId]);
    return $stmt->fetch();
}

/**
 * Create transaction with multi-currency support
 */
public function createWithMultiCurrency($data)
{
    // Ensure all currency fields are set
    $currencyFields = ['payment_rmb', 'payment_usd', 'payment_sdg', 'payment_aed', 
                      'balance_rmb', 'balance_usd', 'balance_sdg', 'balance_aed'];
    
    foreach ($currencyFields as $field) {
        if (!isset($data[$field])) {
            $data[$field] = 0.00;
        }
    }
    
    return $this->create($data);
}

/**
 * Process partial payment in any currency
 */
public function processPartialPayment($transactionId, $paymentCurrency, $paymentAmount, $bankName, $userId)
{
    $db = $this->db;
    
    try {
        $db->beginTransaction();
        
        // Get original transaction
        $originalTransaction = $this->find($transactionId);
        if (!$originalTransaction) {
            throw new \Exception('Transaction not found');
        }
        
        // Get exchange rates
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'exchange_rate_%'");
        $rates = [];
        while ($row = $stmt->fetch()) {
            $rates[$row['setting_key']] = floatval($row['setting_value']);
        }
        
        // Calculate payment in all currencies
        $payments = [
            'payment_rmb' => 0,
            'payment_usd' => 0,
            'payment_sdg' => 0,
            'payment_aed' => 0
        ];
        
        switch ($paymentCurrency) {
            case 'RMB':
                $payments['payment_rmb'] = $paymentAmount;
                break;
            case 'USD':
                $payments['payment_rmb'] = $paymentAmount * ($rates['exchange_rate_usd_rmb'] ?? 7.2);
                $payments['payment_usd'] = $paymentAmount;
                break;
            case 'SDG':
                $payments['payment_rmb'] = $paymentAmount * ($rates['exchange_rate_sdg_rmb'] ?? 0.012);
                $payments['payment_sdg'] = $paymentAmount;
                break;
            case 'AED':
                $payments['payment_rmb'] = $paymentAmount * ($rates['exchange_rate_aed_rmb'] ?? 1.96);
                $payments['payment_aed'] = $paymentAmount;
                break;
        }
        
        // Generate payment transaction number
        $paymentNo = 'PAY-' . date('Ymd-His');
        
        // Create payment transaction
        $paymentData = array_merge($payments, [
            'transaction_no' => $paymentNo,
            'client_id' => $originalTransaction['client_id'],
            'transaction_type_id' => 2, // Assuming 2 is payment type
            'transaction_date' => date('Y-m-d'),
            'description' => 'Partial payment for #' . $originalTransaction['transaction_no'],
            'bank_name' => $bankName,
            'loading_id' => $originalTransaction['loading_id'],
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => date('Y-m-d H:i:s')
        ]);
        
        $paymentId = $this->create($paymentData);
        
        // Create cashbox movement
        $cashboxData = array_merge($payments, [
            'transaction_id' => $paymentId,
            'movement_date' => date('Y-m-d'),
            'movement_type' => 'in',
            'category' => 'payment_received',
            'description' => 'Payment for #' . $originalTransaction['transaction_no'],
            'bank_name' => $bankName,
            'created_by' => $userId
        ]);
        
        // Change payment field names to amount for cashbox
        foreach (['rmb', 'usd', 'sdg', 'aed'] as $currency) {
            $cashboxData['amount_' . $currency] = $cashboxData['payment_' . $currency];
            unset($cashboxData['payment_' . $currency]);
        }
        
        $cashboxModel = new \App\Models\Cashbox();
        $cashboxModel->create($cashboxData);
        
        $db->commit();
        
        return ['success' => true, 'payment_id' => $paymentId];
        
    } catch (\Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
}