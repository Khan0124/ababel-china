<?php
namespace App\Models;

use App\Core\Model;

/**
 * Cashbox Currency Conversion System
 * Handles currency exchanges that affect cashbox balances
 */
class CashboxCurrencyConverter extends Model
{
    protected $table = 'cashbox_movements';
    private $exchangeRateManager;
    
    public function __construct()
    {
        parent::__construct();
        $this->exchangeRateManager = new ExchangeRateManager();
    }
    
    /**
     * Convert currency amounts within cashbox
     */
    public function convertCashboxCurrency($fromCurrency, $toCurrency, $amount, $description = null)
    {
        // Validate inputs
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than 0'];
        }
        
        if ($fromCurrency === $toCurrency) {
            return ['success' => false, 'error' => 'Cannot convert to same currency'];
        }
        
        // Check if cashbox has sufficient balance
        $cashboxBalance = $this->getCashboxBalance($fromCurrency);
        if ($cashboxBalance < $amount) {
            return [
                'success' => false, 
                'error' => "Insufficient balance in {$fromCurrency}. Available: " . number_format($cashboxBalance, 2)
            ];
        }
        
        // Get current exchange rate
        $rateData = $this->exchangeRateManager->getCurrentRate($fromCurrency, $toCurrency);
        $convertedAmount = $amount * $rateData['rate'];
        
        // Begin transaction
        $this->db->beginTransaction();
        
        try {
            // Record debit movement (deduct from source currency)
            $debitMovement = $this->recordCashboxMovement([
                'movement_type' => 'currency_exchange_out',
                'amount' => -$amount, // Negative for deduction
                'currency' => $fromCurrency,
                'description' => $description ?: "Currency exchange: {$fromCurrency} to {$toCurrency}",
                'reference_type' => 'currency_conversion',
                'exchange_rate' => $rateData['rate'],
                'related_currency' => $toCurrency,
                'related_amount' => $convertedAmount
            ]);
            
            if (!$debitMovement) {
                throw new \Exception('Failed to record debit movement');
            }
            
            // Record credit movement (add to target currency)
            $creditMovement = $this->recordCashboxMovement([
                'movement_type' => 'currency_exchange_in',
                'amount' => $convertedAmount, // Positive for addition
                'currency' => $toCurrency,
                'description' => $description ?: "Currency exchange: {$fromCurrency} to {$toCurrency}",
                'reference_type' => 'currency_conversion',
                'reference_id' => $debitMovement,
                'exchange_rate' => $rateData['rate'],
                'related_currency' => $fromCurrency,
                'related_amount' => $amount
            ]);
            
            if (!$creditMovement) {
                throw new \Exception('Failed to record credit movement');
            }
            
            // Update cashbox balances
            $this->updateCashboxBalance($fromCurrency, -$amount);
            $this->updateCashboxBalance($toCurrency, $convertedAmount);
            
            // Log the conversion for audit
            $this->logCurrencyConversion([
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'original_amount' => $amount,
                'converted_amount' => $convertedAmount,
                'exchange_rate' => $rateData['rate'],
                'debit_movement_id' => $debitMovement,
                'credit_movement_id' => $creditMovement,
                'description' => $description
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'conversion_data' => [
                    'from_currency' => $fromCurrency,
                    'to_currency' => $toCurrency,
                    'original_amount' => $amount,
                    'converted_amount' => $convertedAmount,
                    'exchange_rate' => $rateData['rate'],
                    'rate_last_updated' => $rateData['last_updated'],
                    'debit_movement_id' => $debitMovement,
                    'credit_movement_id' => $creditMovement,
                    'conversion_time' => date('Y-m-d H:i:s')
                ],
                'updated_balances' => [
                    $fromCurrency => $this->getCashboxBalance($fromCurrency),
                    $toCurrency => $this->getCashboxBalance($toCurrency)
                ]
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'error' => 'Currency conversion failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get current cashbox balance for a currency
     */
    public function getCashboxBalance($currency)
    {
        $columnMap = [
            'RMB' => 'amount_rmb',
            'USD' => 'amount_usd', 
            'SDG' => 'amount_sdg',
            'AED' => 'amount_aed'
        ];
        
        $column = $columnMap[$currency] ?? null;
        if (!$column) {
            return 0;
        }
        
        // Calculate balance from movements like the main Cashbox model does
        $sql = "
            SELECT 
                SUM(CASE WHEN movement_type = 'in' THEN {$column} ELSE -{$column} END) as balance
            FROM cashbox_movements
        ";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        
        return floatval($result['balance'] ?? 0);
    }
    
    /**
     * Get all cashbox balances
     */
    public function getAllCashboxBalances()
    {
        // Calculate balances from movements like the main Cashbox model does
        $sql = "
            SELECT 
                SUM(CASE WHEN movement_type = 'in' THEN amount_rmb ELSE -amount_rmb END) as balance_rmb,
                SUM(CASE WHEN movement_type = 'in' THEN amount_usd ELSE -amount_usd END) as balance_usd,
                SUM(CASE WHEN movement_type = 'in' THEN amount_sdg ELSE -amount_sdg END) as balance_sdg,
                SUM(CASE WHEN movement_type = 'in' THEN amount_aed ELSE -amount_aed END) as balance_aed
            FROM cashbox_movements
        ";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        
        return [
            'RMB' => floatval($result['balance_rmb'] ?? 0),
            'USD' => floatval($result['balance_usd'] ?? 0),
            'SDG' => floatval($result['balance_sdg'] ?? 0),
            'AED' => floatval($result['balance_aed'] ?? 0)
        ];
    }
    
    /**
     * Record cashbox movement
     */
    private function recordCashboxMovement($data)
    {
        // Map currency to correct column
        $amountColumns = [
            'RMB' => 'amount_rmb',
            'USD' => 'amount_usd',
            'SDG' => 'amount_sdg',
            'AED' => 'amount_aed'
        ];
        
        $column = $amountColumns[$data['currency']] ?? null;
        if (!$column) {
            throw new \Exception("Unsupported currency: {$data['currency']}");
        }
        
        // Prepare amount values for all currencies (only set the relevant one)
        $amounts = [
            'amount_rmb' => 0,
            'amount_usd' => 0,
            'amount_sdg' => 0,
            'amount_aed' => 0
        ];
        
        // Set the amount for the specific currency
        $amounts[$column] = abs($data['amount']); // Use absolute value, type determines sign
        
        // Determine movement type based on amount sign
        $movementType = $data['amount'] < 0 ? 'out' : 'in';
        
        $sql = "
            INSERT INTO cashbox_movements (
                movement_date, movement_type, category, 
                amount_rmb, amount_usd, amount_sdg, amount_aed,
                description, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $params = [
            date('Y-m-d'),
            $movementType,
            'currency_exchange',
            $amounts['amount_rmb'],
            $amounts['amount_usd'],
            $amounts['amount_sdg'],
            $amounts['amount_aed'],
            $data['description'],
            $_SESSION['user_id'] ?? 1,
            date('Y-m-d H:i:s')
        ];
        
        $stmt = $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }
    
    /**
     * Update cashbox balance
     * Note: Since balances are calculated from movements, this method is now deprecated
     * The balance updates automatically when movements are recorded
     */
    private function updateCashboxBalance($currency, $amount)
    {
        // Balance is automatically updated through movements
        // This method is kept for backward compatibility but does nothing
        return true;
    }
    
    /**
     * Log currency conversion for audit
     */
    private function logCurrencyConversion($data)
    {
        $sql = "
            INSERT INTO cashbox_currency_conversions (
                from_currency, to_currency, original_amount, converted_amount, 
                exchange_rate, debit_movement_id, credit_movement_id, 
                description, converted_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        return $this->db->query($sql, [
            $data['from_currency'],
            $data['to_currency'],
            $data['original_amount'],
            $data['converted_amount'],
            $data['exchange_rate'],
            $data['debit_movement_id'],
            $data['credit_movement_id'],
            $data['description'],
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get conversion history
     */
    public function getConversionHistory($limit = 50)
    {
        $sql = "
            SELECT 
                from_currency,
                to_currency,
                original_amount,
                converted_amount,
                exchange_rate,
                description,
                converted_at,
                debit_movement_id,
                credit_movement_id
            FROM cashbox_currency_conversions 
            ORDER BY converted_at DESC 
            LIMIT ?
        ";
        
        $limit = intval($limit);
        $sql = str_replace('?', $limit, $sql);
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Get conversion summary statistics
     */
    public function getConversionSummary($days = 30)
    {
        $sql = "
            SELECT 
                from_currency,
                to_currency,
                COUNT(*) as conversion_count,
                SUM(original_amount) as total_original,
                SUM(converted_amount) as total_converted,
                AVG(exchange_rate) as avg_rate,
                MAX(converted_at) as last_conversion
            FROM cashbox_currency_conversions
            WHERE converted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY from_currency, to_currency
            ORDER BY conversion_count DESC
        ";
        
        $stmt = $this->db->query($sql, [$days]);
        return $stmt->fetchAll();
    }
    
    /**
     * Validate conversion request
     */
    public function validateConversionRequest($fromCurrency, $toCurrency, $amount)
    {
        $errors = [];
        
        // Check supported currencies
        $supportedCurrencies = ['RMB', 'USD', 'SDG', 'AED'];
        if (!in_array($fromCurrency, $supportedCurrencies)) {
            $errors[] = "Unsupported source currency: {$fromCurrency}";
        }
        
        if (!in_array($toCurrency, $supportedCurrencies)) {
            $errors[] = "Unsupported target currency: {$toCurrency}";
        }
        
        // Check amount
        if (!is_numeric($amount) || $amount <= 0) {
            $errors[] = "Amount must be a positive number";
        }
        
        // Check same currency
        if ($fromCurrency === $toCurrency) {
            $errors[] = "Source and target currencies must be different";
        }
        
        // Check cashbox balance
        if (empty($errors)) {
            $balance = $this->getCashboxBalance($fromCurrency);
            if ($balance < $amount) {
                $errors[] = "Insufficient balance in {$fromCurrency}. Available: " . number_format($balance, 2);
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Get exchange rate preview for conversion
     */
    public function getConversionPreview($fromCurrency, $toCurrency, $amount)
    {
        $validation = $this->validateConversionRequest($fromCurrency, $toCurrency, $amount);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }
        
        $rateData = $this->exchangeRateManager->getCurrentRate($fromCurrency, $toCurrency);
        $convertedAmount = $amount * $rateData['rate'];
        
        $currentBalances = $this->getAllCashboxBalances();
        $newBalances = $currentBalances;
        $newBalances[$fromCurrency] -= $amount;
        $newBalances[$toCurrency] += $convertedAmount;
        
        return [
            'success' => true,
            'preview' => [
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'original_amount' => $amount,
                'converted_amount' => $convertedAmount,
                'exchange_rate' => $rateData['rate'],
                'rate_last_updated' => $rateData['last_updated'],
                'rate_status' => $rateData['status'],
                'current_balances' => $currentBalances,
                'new_balances' => $newBalances,
                'balance_changes' => [
                    $fromCurrency => -$amount,
                    $toCurrency => $convertedAmount
                ]
            ]
        ];
    }
}