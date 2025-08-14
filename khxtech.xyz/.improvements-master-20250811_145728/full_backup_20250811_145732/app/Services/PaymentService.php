<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Transaction;
use App\Models\Client;
use App\Models\Cashbox;
use Exception;

class PaymentService
{
    private $db;
    private $transactionModel;
    private $clientModel;
    private $cashboxModel;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->transactionModel = new Transaction();
        $this->clientModel = new Client();
        $this->cashboxModel = new Cashbox();
    }
    
    /**
     * Process a payment with complete error handling and rollback
     */
    public function processPayment($paymentData)
    {
        $this->validatePaymentData($paymentData);
        
        $conn = $this->db->getConnection();
        
        try {
            $conn->beginTransaction();
            
            // Lock the client record to prevent concurrent modifications
            $client = $this->lockClientRecord($paymentData['client_id']);
            
            if (!$client) {
                throw new Exception('Client not found or locked by another process');
            }
            
            // Validate payment amounts don't exceed limits
            $this->validatePaymentAmounts($paymentData, $client);
            
            // Create payment transaction
            $transactionId = $this->createPaymentTransaction($paymentData);
            
            // Update client balances
            $this->updateClientBalances($client, $paymentData);
            
            // Create cashbox entry
            $this->createCashboxEntry($transactionId, $paymentData, $client);
            
            // Log the payment in audit trail
            $this->logPaymentAudit($transactionId, $paymentData);
            
            // Send notifications if configured
            $this->sendPaymentNotifications($transactionId, $client, $paymentData);
            
            $conn->commit();
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'Payment processed successfully'
            ];
            
        } catch (Exception $e) {
            $conn->rollBack();
            
            // Log the error
            $this->logPaymentError($e, $paymentData);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Process partial payment
     */
    public function processPartialPayment($originalTransactionId, $paymentData)
    {
        $conn = $this->db->getConnection();
        
        try {
            $conn->beginTransaction();
            
            // Get and lock original transaction
            $originalTransaction = $this->lockTransactionRecord($originalTransactionId);
            
            if (!$originalTransaction) {
                throw new Exception('Original transaction not found');
            }
            
            if ($originalTransaction['status'] !== 'approved') {
                throw new Exception('Cannot make partial payment on unapproved transaction');
            }
            
            // Validate partial payment amounts
            $this->validatePartialPaymentAmounts($originalTransaction, $paymentData);
            
            // Create partial payment transaction
            $paymentId = $this->createPartialPaymentTransaction($originalTransaction, $paymentData);
            
            // Update original transaction balances
            $this->updateTransactionBalances($originalTransactionId, $paymentData);
            
            // Update client balances
            $client = $this->clientModel->find($originalTransaction['client_id']);
            $this->updateClientBalances($client, $paymentData);
            
            // Create cashbox entry
            $this->createCashboxEntry($paymentId, $paymentData, $client);
            
            // Log in audit trail
            $this->logPartialPaymentAudit($paymentId, $originalTransactionId, $paymentData);
            
            $conn->commit();
            
            return [
                'success' => true,
                'payment_id' => $paymentId,
                'message' => 'Partial payment processed successfully'
            ];
            
        } catch (Exception $e) {
            $conn->rollBack();
            
            $this->logPaymentError($e, $paymentData);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process refund
     */
    public function processRefund($transactionId, $refundData)
    {
        $conn = $this->db->getConnection();
        
        try {
            $conn->beginTransaction();
            
            // Get original transaction
            $transaction = $this->transactionModel->find($transactionId);
            
            if (!$transaction) {
                throw new Exception('Transaction not found');
            }
            
            if ($transaction['status'] !== 'approved') {
                throw new Exception('Cannot refund unapproved transaction');
            }
            
            // Validate refund amounts
            $this->validateRefundAmounts($transaction, $refundData);
            
            // Create refund transaction
            $refundId = $this->createRefundTransaction($transaction, $refundData);
            
            // Update client balances (reverse the payment)
            $client = $this->clientModel->find($transaction['client_id']);
            $this->reverseClientBalances($client, $refundData);
            
            // Create cashbox entry for refund
            $this->createRefundCashboxEntry($refundId, $refundData, $client);
            
            // Update original transaction status if fully refunded
            if ($this->isFullyRefunded($transaction, $refundData)) {
                $this->transactionModel->update($transactionId, ['status' => 'refunded']);
            }
            
            // Log refund in audit trail
            $this->logRefundAudit($refundId, $transactionId, $refundData);
            
            $conn->commit();
            
            return [
                'success' => true,
                'refund_id' => $refundId,
                'message' => 'Refund processed successfully'
            ];
            
        } catch (Exception $e) {
            $conn->rollBack();
            
            $this->logPaymentError($e, $refundData);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate payment data
     */
    private function validatePaymentData(&$data)
    {
        $required = ['client_id', 'transaction_date'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Required field '$field' is missing");
            }
        }
        
        // Ensure all payment amounts are numeric and non-negative
        $amountFields = ['payment_rmb', 'payment_usd', 'payment_sdg', 'payment_aed'];
        
        foreach ($amountFields as $field) {
            if (!isset($data[$field])) {
                $data[$field] = 0;
            }
            
            if (!is_numeric($data[$field]) || $data[$field] < 0) {
                throw new Exception("Invalid amount for $field");
            }
            
            $data[$field] = floatval($data[$field]);
        }
        
        // At least one payment amount must be greater than 0
        $totalPayment = array_sum(array_map(function($field) use ($data) {
            return $data[$field];
        }, $amountFields));
        
        if ($totalPayment <= 0) {
            throw new Exception('Total payment amount must be greater than zero');
        }
    }
    
    /**
     * Lock client record for update
     */
    private function lockClientRecord($clientId)
    {
        $sql = "SELECT * FROM clients WHERE id = ? FOR UPDATE";
        $stmt = $this->db->query($sql, [$clientId]);
        return $stmt->fetch();
    }
    
    /**
     * Lock transaction record for update
     */
    private function lockTransactionRecord($transactionId)
    {
        $sql = "SELECT * FROM transactions WHERE id = ? FOR UPDATE";
        $stmt = $this->db->query($sql, [$transactionId]);
        return $stmt->fetch();
    }
    
    /**
     * Validate payment amounts don't exceed client balance
     */
    private function validatePaymentAmounts($paymentData, $client)
    {
        // This depends on your business logic
        // You might want to check if payment exceeds outstanding balance
        return true;
    }
    
    /**
     * Validate partial payment amounts
     */
    private function validatePartialPaymentAmounts($originalTransaction, $paymentData)
    {
        if ($paymentData['payment_rmb'] > $originalTransaction['balance_rmb']) {
            throw new Exception('RMB payment exceeds outstanding balance');
        }
        
        if ($paymentData['payment_usd'] > $originalTransaction['balance_usd']) {
            throw new Exception('USD payment exceeds outstanding balance');
        }
        
        if ($paymentData['payment_sdg'] > $originalTransaction['balance_sdg']) {
            throw new Exception('SDG payment exceeds outstanding balance');
        }
        
        if ($paymentData['payment_aed'] > $originalTransaction['balance_aed']) {
            throw new Exception('AED payment exceeds outstanding balance');
        }
    }
    
    /**
     * Create payment transaction
     */
    private function createPaymentTransaction($data)
    {
        $transactionData = [
            'transaction_no' => $this->generatePaymentNumber(),
            'client_id' => $data['client_id'],
            'transaction_type_id' => 3, // PAYMENT_RECEIVED
            'transaction_date' => $data['transaction_date'],
            'description' => $data['description'] ?? 'Payment received',
            'bank_name' => $data['bank_name'] ?? null,
            'payment_rmb' => $data['payment_rmb'],
            'payment_usd' => $data['payment_usd'],
            'payment_sdg' => $data['payment_sdg'],
            'payment_aed' => $data['payment_aed'],
            'balance_rmb' => 0,
            'balance_usd' => 0,
            'balance_sdg' => 0,
            'balance_aed' => 0,
            'status' => 'approved',
            'created_by' => $data['user_id'] ?? $_SESSION['user_id'] ?? null,
            'approved_by' => $data['user_id'] ?? $_SESSION['user_id'] ?? null,
            'approved_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->transactionModel->create($transactionData);
    }
    
    /**
     * Generate unique payment number
     */
    private function generatePaymentNumber()
    {
        return 'PAY-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }
    
    /**
     * Update client balances
     */
    private function updateClientBalances($client, $paymentData)
    {
        $newBalances = [
            'balance_rmb' => $client['balance_rmb'] - $paymentData['payment_rmb'],
            'balance_usd' => $client['balance_usd'] - $paymentData['payment_usd'],
            'balance_sdg' => $client['balance_sdg'] - $paymentData['payment_sdg'],
            'balance_aed' => $client['balance_aed'] - $paymentData['payment_aed']
        ];
        
        $this->clientModel->update($client['id'], $newBalances);
    }
    
    /**
     * Create cashbox entry
     */
    private function createCashboxEntry($transactionId, $paymentData, $client)
    {
        $cashboxData = [
            'transaction_id' => $transactionId,
            'movement_date' => $paymentData['transaction_date'],
            'movement_type' => 'in',
            'category' => 'payment_received',
            'amount_rmb' => $paymentData['payment_rmb'],
            'amount_usd' => $paymentData['payment_usd'],
            'amount_sdg' => $paymentData['payment_sdg'],
            'amount_aed' => $paymentData['payment_aed'],
            'bank_name' => $paymentData['bank_name'] ?? null,
            'description' => 'Payment from ' . $client['name'],
            'created_by' => $paymentData['user_id'] ?? $_SESSION['user_id'] ?? null
        ];
        
        return $this->cashboxModel->create($cashboxData);
    }
    
    /**
     * Log payment in audit trail
     */
    private function logPaymentAudit($transactionId, $paymentData)
    {
        $sql = "INSERT INTO financial_audit_log 
                (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $values = [
            $paymentData['user_id'] ?? $_SESSION['user_id'] ?? 0,
            'PAYMENT',
            'transaction',
            $transactionId,
            json_encode($paymentData),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            'Payment processed: ' . $this->generatePaymentSummary($paymentData)
        ];
        
        $this->db->query($sql, $values);
    }
    
    /**
     * Generate payment summary for logging
     */
    private function generatePaymentSummary($paymentData)
    {
        $amounts = [];
        
        if ($paymentData['payment_rmb'] > 0) {
            $amounts[] = 'RMB ' . number_format($paymentData['payment_rmb'], 2);
        }
        if ($paymentData['payment_usd'] > 0) {
            $amounts[] = 'USD ' . number_format($paymentData['payment_usd'], 2);
        }
        if ($paymentData['payment_sdg'] > 0) {
            $amounts[] = 'SDG ' . number_format($paymentData['payment_sdg'], 2);
        }
        if ($paymentData['payment_aed'] > 0) {
            $amounts[] = 'AED ' . number_format($paymentData['payment_aed'], 2);
        }
        
        return implode(', ', $amounts);
    }
    
    /**
     * Log payment error
     */
    private function logPaymentError($exception, $data)
    {
        error_log('Payment Error: ' . $exception->getMessage() . ' | Data: ' . json_encode($data));
        
        // Also log to database if possible
        try {
            $sql = "INSERT INTO error_logs (error_type, message, context, created_at)
                    VALUES ('payment_error', ?, ?, NOW())";
            $this->db->query($sql, [$exception->getMessage(), json_encode($data)]);
        } catch (Exception $e) {
            // Silently fail if logging fails
        }
    }
    
    /**
     * Send payment notifications
     */
    private function sendPaymentNotifications($transactionId, $client, $paymentData)
    {
        // Implement email/SMS notifications as needed
        // This is a placeholder for notification logic
    }
}