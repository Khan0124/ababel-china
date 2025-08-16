<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Transaction;
use App\Models\Client;
use App\Models\Cashbox;

class TransactionController extends Controller
{
    private $transactionModel;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->transactionModel = new Transaction();
        $this->db = Database::getInstance();
    }
    
    public function index()
    {
        // Get filters from GET parameters
        $filters = [
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'client_id' => $_GET['client_id'] ?? '',
            'status' => $_GET['status'] ?? '',
            'claim_number' => $_GET['claim_number'] ?? ''
        ];
        
        // Get clients for filter dropdown
        $clientModel = new Client();
        $clients = $clientModel->all(['status' => 'active'], 'name') ?? [];
        
        // Build conditions array for legacy compatibility
        $conditions = [];
        if (!empty($filters['client_id'])) {
            $conditions['client_id'] = $filters['client_id'];
        }
        if (!empty($filters['status'])) {
            $conditions['status'] = $filters['status'];
        }
        
        // Use getWithFilters method if available, otherwise fallback to all()
        if (method_exists($this->transactionModel, 'getWithFilters')) {
            $transactions = $this->transactionModel->getWithFilters($filters);
        } else {
            $transactions = $this->transactionModel->all($conditions, 'transaction_date DESC', 50) ?? [];
        }
        
        $this->view('transactions/index', [
            'title' => __('transactions.title'),
            'transactions' => $transactions,
            'clients' => $clients,
            'filters' => $filters
        ]);
    }
    
    public function create()
    {
        $clientModel = new Client();
        $clients = $clientModel->all(['status' => 'active'], 'name');
        
        // Get banks list from settings
        $db = \App\Core\Database::getInstance();
        $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'banks_list'");
        $banksResult = $stmt->fetch();
        $banksList = $banksResult ? explode(',', $banksResult['setting_value']) : [];
        $banksList = array_map('trim', $banksList);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Check for duplicate transaction number in the same bank
            if (!empty($_POST['transaction_no']) && !empty($_POST['bank_name'])) {
                $checkSql = "SELECT COUNT(*) as count FROM transactions 
                            WHERE transaction_no = ? AND bank_name = ?";
                $stmt = $db->query($checkSql, [$_POST['transaction_no'], $_POST['bank_name']]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $this->view('transactions/create', [
                        'title' => __('transactions.add_new'),
                        'error' => __('validation.transaction_duplicate_bank'),
                        'clients' => $clients,
                        'banksList' => $banksList,
                        'data' => $_POST
                    ]);
                    return;
                }
            }
            
            $transactionData = [
                'transaction_no' => $_POST['transaction_no'] ?? $this->transactionModel->generateTransactionNo(),
                'client_id' => $_POST['client_id'],
                'transaction_type_id' => $_POST['transaction_type_id'],
                'transaction_date' => $_POST['transaction_date'],
                'description' => $_POST['description'],
                'invoice_no' => $_POST['invoice_no'] ?? null,
                'loading_no' => $_POST['loading_no'] ?? null,
                'bank_name' => $_POST['bank_name'] ?? null,
                'goods_amount_rmb' => $_POST['goods_amount_rmb'] ?? 0,
                'commission_rmb' => $_POST['commission_rmb'] ?? 0,
                'total_amount_rmb' => $_POST['total_amount_rmb'] ?? 0,
                'payment_rmb' => $_POST['payment_rmb'] ?? 0,
                'balance_rmb' => $_POST['balance_rmb'] ?? 0,
                'shipping_usd' => $_POST['shipping_usd'] ?? 0,
                'payment_usd' => $_POST['payment_usd'] ?? 0,
                'balance_usd' => $_POST['balance_usd'] ?? 0,
                'payment_sdg' => $_POST['payment_sdg'] ?? 0,
                'payment_aed' => $_POST['payment_aed'] ?? 0,
                'balance_sdg' => $_POST['balance_sdg'] ?? 0,
                'balance_aed' => $_POST['balance_aed'] ?? 0,
                'rate_usd_rmb' => $_POST['rate_usd_rmb'] ?? null,
                'rate_sdg_rmb' => $_POST['rate_sdg_rmb'] ?? null,
                'rate_aed_rmb' => $_POST['rate_aed_rmb'] ?? null,
                'created_by' => $_SESSION['user_id'],
                'status' => 'pending'
            ];
            
            // Prepare cashbox data if affects cashbox
            $cashboxData = null;
            if (isset($_POST['affects_cashbox']) && $_POST['affects_cashbox']) {
                $cashboxData = [
                    'movement_date' => $_POST['transaction_date'],
                    'movement_type' => $_POST['cashbox_type'], // in/out
                    'category' => $_POST['cashbox_category'],
                    'amount_rmb' => $_POST['cashbox_rmb'] ?? 0,
                    'amount_usd' => $_POST['cashbox_usd'] ?? 0,
                    'amount_sdg' => $_POST['cashbox_sdg'] ?? 0,
                    'amount_aed' => $_POST['cashbox_aed'] ?? 0,
                    'bank_name' => $_POST['bank_name'] ?? null,
                    'tt_number' => $_POST['tt_number'] ?? null,
                    'receipt_no' => $_POST['receipt_no'] ?? null,
                    'description' => $_POST['cashbox_description'] ?? null,
                    'created_by' => $_SESSION['user_id']
                ];
            }
            
            try {
                $transactionId = $this->transactionModel->createWithCashbox($transactionData, $cashboxData);
                
                // 2025-01-11: تسجيل إضافة المعاملة
                $this->logActivity('transaction_create', $_SESSION['user_id'], 'إضافة معاملة جديدة رقم: ' . $transactionData['transaction_no'], 'transactions', $transactionId);
                
                $this->redirect('/transactions?success=' . urlencode(__('transactions.transaction_created')));
            } catch (\Exception $e) {
                $this->view('transactions/create', [
                    'title' => __('transactions.add_new'),
                    'error' => __('messages.operation_failed') . ': ' . $e->getMessage(),
                    'clients' => $clients,
                    'banksList' => $banksList,
                    'data' => $transactionData
                ]);
                return;
            }
        }
        
        $this->view('transactions/create', [
            'title' => __('transactions.add_new'),
            'clients' => $clients,
            'banksList' => $banksList
        ]);
    }
    
    // Changed from 'view' to 'show' to avoid conflict with parent class
    public function show($id)
    {
        $transaction = $this->transactionModel->getWithDetails($id);
        
        if (!$transaction) {
            $this->redirect('/transactions?error=' . urlencode(__('messages.transaction_not_found')));
        }
        
        $this->view('transactions/view', [
            'title' => __('transactions.details'),
            'transaction' => $transaction
        ]);
    }
    
    /**
     * Show approval confirmation page (GET request)
     */
    public function showApprove($id)
    {
        // Check authorization
        if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'accountant') {
            $this->redirect('/transactions?error=' . urlencode(__('messages.unauthorized')));
        }
        
        $transaction = $this->transactionModel->getWithDetails($id);
        
        if (!$transaction) {
            $this->redirect('/transactions?error=' . urlencode(__('messages.transaction_not_found')));
        }
        
        // If transaction is already approved
        if ($transaction['status'] === 'approved') {
            $this->redirect('/transactions/view/' . $id . '?error=' . urlencode(__('messages.transaction_already_approved')));
        }
        
        $this->view('transactions/approve', [
            'title' => __('transactions.approve'),
            'transaction' => $transaction
        ]);
    }
    
    /**
     * Process transaction approval (POST request)
     */
    public function approve($id)
    {
        if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'accountant') {
            $this->redirect('/transactions?error=' . urlencode(__('messages.unauthorized')));
        }
        
        $db = \App\Core\Database::getInstance();
        
        try {
            $db->beginTransaction();
            
            // Get transaction details
            $transaction = $this->transactionModel->find($id);
            if (!$transaction) {
                throw new \Exception(__('messages.transaction_not_found'));
            }
            
            // Update transaction status
            $this->transactionModel->update($id, [
                'status' => 'approved',
                'approved_by' => $_SESSION['user_id'],
                'approved_at' => date('Y-m-d H:i:s')
            ]);
            
            // If this is a payment transaction, update client balance and cashbox
            if ($transaction['payment_rmb'] > 0 || $transaction['payment_usd'] > 0 || 
                $transaction['payment_sdg'] > 0 || $transaction['payment_aed'] > 0) {
                
                $clientModel = new Client();
                $client = $clientModel->find($transaction['client_id']);
                
                if ($client) {
                    // Get exchange rates for USD calculation
                    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'exchange_rate_%'");
                    $rates = $stmt->fetchAll();
                    $exchangeRates = [];
                    foreach ($rates as $rate) {
                        $exchangeRates[$rate['setting_key']] = floatval($rate['setting_value']);
                    }
                    
                    // Exchange rates
                    $usdToRmb = $exchangeRates['exchange_rate_usd_rmb'] ?? 7.20;
                    $sdgToRmb = $exchangeRates['exchange_rate_sdg_rmb'] ?? 0.012;
                    $aedToRmb = $exchangeRates['exchange_rate_aed_rmb'] ?? 2.00;
                    
                    // Calculate USD equivalent of all payments
                    $totalUsdEquivalent = $transaction['payment_usd'];
                    $totalUsdEquivalent += ($transaction['payment_rmb'] / $usdToRmb);
                    $totalUsdEquivalent += ($transaction['payment_sdg'] * $sdgToRmb / $usdToRmb);
                    $totalUsdEquivalent += ($transaction['payment_aed'] * $aedToRmb / $usdToRmb);
                    
                    // Update client balances
                    $newBalanceRmb = $client['balance_rmb'] - $transaction['payment_rmb'];
                    $newBalanceUsd = $client['balance_usd'] - $transaction['payment_usd'];
                    $newBalanceSdg = $client['balance_sdg'] - $transaction['payment_sdg'];
                    $newBalanceAed = $client['balance_aed'] - $transaction['payment_aed'];
                    
                    $clientModel->update($transaction['client_id'], [
                        'balance_rmb' => $newBalanceRmb,
                        'balance_usd' => $newBalanceUsd,
                        'balance_sdg' => $newBalanceSdg,
                        'balance_aed' => $newBalanceAed
                    ]);
                    
                    // Create cashbox movement if payment was made
                    $cashboxModel = new Cashbox();
                    $cashboxModel->create([
                        'transaction_id' => $id,
                        'movement_date' => date('Y-m-d'),
                        'movement_type' => 'in',
                        'category' => 'payment_received',
                        'amount_rmb' => $transaction['payment_rmb'],
                        'amount_usd' => $transaction['payment_usd'],
                        'amount_sdg' => $transaction['payment_sdg'],
                        'amount_aed' => $transaction['payment_aed'] ?? 0,
                        'bank_name' => $transaction['bank_name'],
                        'description' => __('transactions.payment_from_client') . ': ' . $client['name'] . 
                                       ' (USD Equivalent: $' . number_format($totalUsdEquivalent, 2) . ')',
                        'created_by' => $_SESSION['user_id']
                    ]);
                }
            }
            
            // 2025-01-11: تسجيل الموافقة على المعاملة
            $this->logActivity('transaction_approve', $_SESSION['user_id'], 'الموافقة على المعاملة رقم: ' . ($transaction['transaction_no'] ?? $id), 'transactions', $id);
            
            $db->commit();
            $this->redirect('/transactions/view/' . $id . '?success=' . urlencode(__('transactions.transaction_approved')));
            
        } catch (\Exception $e) {
            $db->rollback();
            $this->redirect('/transactions/view/' . $id . '?error=' . urlencode(__('messages.error_approving_transaction') . ': ' . $e->getMessage()));
        }
    }
    
    /**
     * Delete transaction (soft delete)
     */
    public function delete($id)
    {
        if ($_SESSION['user_role'] !== 'admin') {
            $this->redirect('/transactions?error=' . urlencode(__('messages.unauthorized')));
        }
        
        try {
            // Get transaction details before deletion for logging
            $transaction = $this->transactionModel->find($id);
            
            $this->transactionModel->update($id, [
                'status' => 'cancelled',
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => $_SESSION['user_id']
            ]);
            
            // 2025-01-11: تسجيل حذف المعاملة
            $this->logActivity('transaction_delete', $_SESSION['user_id'], 'حذف المعاملة رقم: ' . ($transaction['transaction_no'] ?? $id), 'transactions', $id);
            
            $this->redirect('/transactions?success=' . urlencode(__('transactions.transaction_cancelled')));
        } catch (\Exception $e) {
            $this->redirect('/transactions?error=' . urlencode(__('messages.error_deleting_transaction')));
        }
    }
    
    /**
     * Show partial payment form
     */
    public function showPartialPayment($id)
    {
        $transaction = $this->transactionModel->getWithDetails($id);
        
        if (!$transaction) {
            $this->redirect('/transactions?error=' . urlencode(__('messages.transaction_not_found')));
        }
        
        // Only allow partial payment for unpaid claims (expense transactions with balance)
        if ($transaction['transaction_type_id'] != 1 || // Not a goods purchase/claim
            ($transaction['balance_rmb'] <= 0 && $transaction['balance_usd'] <= 0 && 
             $transaction['balance_sdg'] <= 0 && $transaction['balance_aed'] <= 0)) {
            $this->redirect('/transactions/view/' . $id . '?error=' . urlencode(__('messages.no_balance_to_pay')));
        }
        
        // Get banks list
        $db = \App\Core\Database::getInstance();
        $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'banks_list'");
        $banksResult = $stmt->fetch();
        $banksList = $banksResult ? explode(',', $banksResult['setting_value']) : [];
        $banksList = array_map('trim', $banksList);
        
        $this->view('transactions/partial_payment', [
            'title' => __('transactions.partial_payment'),
            'transaction' => $transaction,
            'banksList' => $banksList
        ]);
    }
    
    /**
     * Process partial payment - FIXED VERSION
     */
    public function processPartialPayment($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/transactions/view/' . $id);
        }
        
        // CSRF Token validation (optional for now)
        if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token']) && 
            $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $this->redirect('/transactions/partial-payment/' . $id . '?error=' . urlencode('Invalid security token'));
            return;
        }
        
        // Debug logging
        error_log("Processing partial payment for transaction ID: $id");
        error_log("POST data: " . print_r($_POST, true));
        
        $db = \App\Core\Database::getInstance();
        
        try {
            $db->beginTransaction();
            
            // Get original transaction
            $originalTransaction = $this->transactionModel->find($id);
            if (!$originalTransaction) {
                throw new \Exception(__('messages.transaction_not_found'));
            }
            
            // Validate payment amounts
            $paymentRmb = floatval($_POST['payment_rmb'] ?? 0);
            $paymentUsd = floatval($_POST['payment_usd'] ?? 0);
            $paymentSdg = floatval($_POST['payment_sdg'] ?? 0);
            $paymentAed = floatval($_POST['payment_aed'] ?? 0);
            
            if ($paymentRmb <= 0 && $paymentUsd <= 0 && $paymentSdg <= 0 && $paymentAed <= 0) {
                throw new \Exception(__('messages.invalid_payment_amount'));
            }
            
            // Check if payment exceeds balance
            if ($paymentRmb > $originalTransaction['balance_rmb'] ||
                $paymentUsd > $originalTransaction['balance_usd'] ||
                $paymentSdg > $originalTransaction['balance_sdg'] ||
                $paymentAed > $originalTransaction['balance_aed']) {
                throw new \Exception(__('messages.payment_exceeds_balance'));
            }
            
            // Generate payment transaction number
            $paymentNo = 'PAY-' . date('Ymd-His');
            
            // Create payment transaction - FIXED to not use negative balances
            $paymentData = [
                'transaction_no' => $paymentNo,
                'client_id' => $originalTransaction['client_id'],
                'transaction_type_id' => 3, // PAYMENT_RECEIVED
                'transaction_date' => date('Y-m-d'),
                'description' => 'Payment for invoice #' . $originalTransaction['transaction_no'],
                'bank_name' => $_POST['bank_name'] ?? null,
                'loading_id' => $originalTransaction['loading_id'],
                'total_amount_rmb' => 0, // Payments don't have claims
                'payment_rmb' => $paymentRmb,
                'payment_usd' => $paymentUsd,
                'payment_sdg' => $paymentSdg,
                'payment_aed' => $paymentAed,
                'balance_rmb' => 0, // Payment transaction - no balance
                'balance_usd' => 0,
                'balance_sdg' => 0,
                'balance_aed' => 0,
                'status' => 'approved',
                'parent_transaction_id' => $id, // Link to original claim
                'created_by' => $_SESSION['user_id'],
                'approved_by' => $_SESSION['user_id'],
                'approved_at' => date('Y-m-d H:i:s')
            ];
            
            $paymentId = $this->transactionModel->create($paymentData);
            
            // Update original transaction balance
            $newBalanceRmb = $originalTransaction['balance_rmb'] - $paymentRmb;
            $newBalanceUsd = $originalTransaction['balance_usd'] - $paymentUsd;
            $newBalanceSdg = $originalTransaction['balance_sdg'] - $paymentSdg;
            $newBalanceAed = $originalTransaction['balance_aed'] - $paymentAed;
            
            // Update payment amounts and balances
            $this->transactionModel->update($id, [
                'payment_rmb' => $originalTransaction['payment_rmb'] + $paymentRmb,
                'payment_usd' => $originalTransaction['payment_usd'] + $paymentUsd,
                'payment_sdg' => $originalTransaction['payment_sdg'] + $paymentSdg,
                'payment_aed' => $originalTransaction['payment_aed'] + $paymentAed,
                'balance_rmb' => $newBalanceRmb,
                'balance_usd' => $newBalanceUsd,
                'balance_sdg' => $newBalanceSdg,
                'balance_aed' => $newBalanceAed,
                'status' => ($newBalanceRmb <= 0 && $newBalanceUsd <= 0 && 
                           $newBalanceSdg <= 0 && $newBalanceAed <= 0) ? 'approved' : 'pending'
            ]);
            
            // Create cashbox movement for each currency
            $cashboxModel = new Cashbox();
            $cashboxData = [
                'transaction_id' => $paymentId,
                'movement_date' => date('Y-m-d'),
                'movement_type' => 'in',
                'category' => 'payment_received',
                'amount_rmb' => $paymentRmb,
                'amount_usd' => $paymentUsd,
                'amount_sdg' => $paymentSdg,
                'amount_aed' => $paymentAed,
                'bank_name' => $_POST['bank_name'] ?? null,
                'description' => 'Payment received for invoice #' . $originalTransaction['transaction_no'],
                'created_by' => $_SESSION['user_id']
            ];
            
            $cashboxModel->create($cashboxData);
            
            // Update client balance from all transactions
            $this->updateClientBalanceFromTransactions($originalTransaction['client_id']);
            
            // 2025-01-11: تسجيل الدفعة الجزئية
            $paymentDesc = sprintf('دفعة جزئية للمعاملة %s: (%.2f RMB, %.2f USD, %.2f SDG, %.2f AED)', 
                $originalTransaction['transaction_no'], $paymentRmb, $paymentUsd, $paymentSdg, $paymentAed);
            $this->logActivity('transaction_partial_payment', $_SESSION['user_id'], $paymentDesc, 'transactions', $paymentId);
            
            $db->commit();
            
            $successMsg = __('messages.payment_processed_successfully');
            if ($newBalanceRmb <= 0 && $newBalanceUsd <= 0 && 
                $newBalanceSdg <= 0 && $newBalanceAed <= 0) {
                $successMsg .= ' ' . __('messages.claim_fully_paid');
            }
            
            $this->redirect('/transactions/view/' . $id . '?success=' . urlencode($successMsg));
            
        } catch (\Exception $e) {
            $db->rollback();
            $this->redirect('/transactions/partial-payment/' . $id . '?error=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Update client balance from all transactions - FIXED VERSION
     */
    private function updateClientBalanceFromTransactions($clientId)
    {
        $db = \App\Core\Database::getInstance();
        
        // Calculate total claims and payments correctly
        $sql = "SELECT 
                -- Claims: Total amounts for expense transactions
                SUM(CASE WHEN tt.type = 'expense' THEN t.total_amount_rmb ELSE 0 END) as total_claims_rmb,
                SUM(CASE WHEN tt.type = 'expense' THEN t.shipping_usd ELSE 0 END) as total_claims_usd,
                
                -- Payments: All payments regardless of transaction type
                SUM(t.payment_rmb) as total_payments_rmb,
                SUM(t.payment_usd) as total_payments_usd,
                SUM(t.payment_sdg) as total_payments_sdg,
                SUM(t.payment_aed) as total_payments_aed
                
                FROM transactions t
                JOIN transaction_types tt ON t.transaction_type_id = tt.id
                WHERE t.client_id = ? AND t.status IN ('approved', 'pending')";
        
        $stmt = $db->query($sql, [$clientId]);
        $balances = $stmt->fetch();
        
        // Calculate net balance using international accounting standards (payments - claims)
        // Negative balance means client owes money (debt), positive means company owes client (credit)
        $balanceRmb = ($balances['total_payments_rmb'] ?? 0) - ($balances['total_claims_rmb'] ?? 0);
        $balanceUsd = ($balances['total_payments_usd'] ?? 0) - ($balances['total_claims_usd'] ?? 0);
        $balanceSdg = ($balances['total_payments_sdg'] ?? 0) - 0; // SDG payments only
        $balanceAed = ($balances['total_payments_aed'] ?? 0) - 0; // AED payments only
        
        // Update client balances
        $clientModel = new Client();
        $clientModel->updateBalance($clientId, $balanceRmb, $balanceUsd, $balanceSdg, $balanceAed);
    }
    
    /**
     * Log activity to audit_log table
     * 2025-01-11: إضافة تسجيل النشاطات للمعاملات
     */
    private function logActivity($action, $userId, $description, $tableName = null, $recordId = null)
    {
        try {
            $sql = "INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            // تحويل الوصف إلى JSON صالح
            $jsonData = json_encode(['description' => $description], JSON_UNESCAPED_UNICODE);
            
            $this->db->query($sql, [
                $userId,
                $action,
                $tableName ?? 'transactions',
                $recordId,
                $jsonData,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log activity in TransactionController: " . $e->getMessage());
        }
    }
}