<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Pagination;
use App\Core\Cache;
use App\Models\Client;

class ClientController extends Controller
{
    private $clientModel;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->clientModel = new Client();
        $this->db = Database::getInstance();
    }
    
    public function index()
    {
        $clientModel = new Client();
        
        // Get page and items per page from request
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 20);
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? 'active';
        
        // Check if this is an AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        // Try to get from cache
        $cacheKey = "clients_page_{$page}_{$perPage}_{$status}_" . md5($search);
        $cachedData = Cache::get($cacheKey);
        
        if (!$cachedData) {
            // Get total count for pagination
            $totalClients = $clientModel->countWithFilters($status, $search);
            
            // Create pagination
            $pagination = new Pagination($totalClients, $perPage, $page);
            
            // Get paginated clients with transaction count
            $clients = $clientModel->allWithTransactionCountPaginated([
                'status' => $status,
                'search' => $search,
                'limit' => $pagination->getLimit(),
                'offset' => $pagination->getOffset()
            ]);
            
            $cachedData = [
                'clients' => $clients,
                'pagination' => $pagination
            ];
            
            // Cache for 5 minutes
            Cache::set($cacheKey, $cachedData, 300);
        } else {
            $clients = $cachedData['clients'];
            $pagination = $cachedData['pagination'];
        }
        
        // Return JSON for AJAX requests
        if ($isAjax) {
            ob_start();
            include BASE_PATH . '/app/Views/clients/_table.php';
            $html = ob_get_clean();
            
            header('Content-Type: application/json');
            echo json_encode([
                'html' => $html,
                'pagination' => $pagination->renderBootstrap5('/clients', true)
            ]);
            return;
        }
        
        $this->view('clients/index', [
            'title' => __('clients.title'),
            'clients' => $clients,
            'pagination' => $pagination
        ]);
    }
    
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'client_code' => $_POST['client_code'],
                'name' => $_POST['name'],
                'name_ar' => $_POST['name_ar'],
                'phone' => $_POST['phone'],
                'email' => $_POST['email'],
                'address' => $_POST['address'],
                'credit_limit' => $_POST['credit_limit'] ?? 0
            ];
            
            try {
                $clientId = $this->clientModel->create($data);
                
                // 2025-01-11: تسجيل إضافة عميل جديد
                $this->logActivity('client_create', $_SESSION['user_id'], 'إضافة عميل جديد: ' . $data['name'], 'clients', $clientId);
                
                $this->redirect('/clients?success=Client created successfully');
            } catch (\Exception $e) {
                $this->view('clients/create', [
                    'title' => 'Add Client',
                    'error' => 'Error creating client: ' . $e->getMessage(),
                    'data' => $data
                ]);
                return;
            }
        }
        
        $this->view('clients/create', [
            'title' => 'Add Client'
        ]);
    }
    
    public function edit($id)
    {
        $client = $this->clientModel->find($id);
        
        if (!$client) {
            $this->redirect('/clients?error=Client not found');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'],
                'name_ar' => $_POST['name_ar'],
                'phone' => $_POST['phone'],
                'email' => $_POST['email'],
                'address' => $_POST['address'],
                'credit_limit' => $_POST['credit_limit'] ?? 0,
                'status' => $_POST['status']
            ];
            
            try {
                $this->clientModel->update($id, $data);
                
                // 2025-01-11: تسجيل تحديث بيانات العميل
                $this->logActivity('client_update', $_SESSION['user_id'], 'تحديث بيانات العميل: ' . $data['name'], 'clients', $id);
                
                $this->redirect('/clients?success=Client updated successfully');
            } catch (\Exception $e) {
                $this->view('clients/edit', [
                    'title' => 'Edit Client',
                    'error' => 'Error updating client: ' . $e->getMessage(),
                    'client' => array_merge($client, $data)
                ]);
                return;
            }
        }
        
        $this->view('clients/edit', [
            'title' => 'Edit Client',
            'client' => $client
        ]);
    }
    
    public function statement($id)
    {
        $client = $this->clientModel->find($id);
        
        if (!$client) {
            $this->redirect('/clients?error=Client not found');
        }
        
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        $transactions = $this->clientModel->getStatement($id, $startDate, $endDate);
        
        $this->view('clients/statement', [
            'title' => 'Client Statement - ' . $client['name'],
            'client' => $client,
            'transactions' => $transactions,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }
    
    /**
     * Process client payment - Enhanced version with bulk payments
     */
    public function makePayment()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/clients');
            return;
        }
        
        $clientId = $_POST['client_id'];
        $paymentAmount = floatval($_POST['payment_amount']);
        $paymentCurrency = $_POST['payment_currency'];
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        $bankName = $_POST['bank_name'] ?? null;
        $description = $_POST['payment_description'] ?? '';
        $transactionIds = $_POST['transaction_ids'] ?? '';
        $paymentType = $_POST['payment_type'] ?? 'full';
        
        if ($paymentAmount <= 0) {
            $_SESSION['error'] = __('messages.invalid_amount');
            $this->redirect('/clients/statement/' . $clientId);
            return;
        }
        
        // Get client details
        $client = $this->clientModel->find($clientId);
        if (!$client) {
            $_SESSION['error'] = __('messages.client_not_found');
            $this->redirect('/clients');
            return;
        }
        
        // Parse transaction IDs if provided
        $targetTransactionIds = array_filter(explode(',', $transactionIds));
        
        // Validate payment amount against outstanding debt
        $balanceField = 'balance_' . strtolower($paymentCurrency);
        $currentBalance = $client[$balanceField] ?? 0;
        
        if (!canMakePayment($currentBalance, $paymentAmount)) {
            $outstandingAmount = getOutstandingAmount($currentBalance);
            if ($outstandingAmount == 0) {
                $_SESSION['error'] = __('messages.no_outstanding_debt');
            } else {
                $_SESSION['error'] = __('messages.payment_exceeds_outstanding') . ' (' . formatBalance($currentBalance, $paymentCurrency) . ')';
            }
            $this->redirect('/clients/statement/' . $clientId);
            return;
        }
        
        try {
            $db = \App\Core\Database::getInstance();
            $db->getConnection()->beginTransaction();
            
            if (!empty($targetTransactionIds)) {
                // Process payment for specific transactions
                $this->processTransactionPayments($clientId, $targetTransactionIds, $paymentAmount, $paymentCurrency, $paymentType, $paymentMethod, $bankName, $description);
            } else {
                // Process general payment
                $this->processGeneralPayment($clientId, $paymentAmount, $paymentCurrency, $paymentMethod, $bankName, $description);
            }
            
            $db->getConnection()->commit();
            
            $_SESSION['success'] = __('transactions.payment_processed_successfully');
            $this->redirect('/clients/statement/' . $clientId);
            
        } catch (\Exception $e) {
            $db->getConnection()->rollback();
            $_SESSION['error'] = __('messages.operation_failed') . ': ' . $e->getMessage();
            $this->redirect('/clients/statement/' . $clientId);
        }
    }
    
    /**
     * Process payments for specific transactions
     */
    private function processTransactionPayments($clientId, $transactionIds, $totalPaymentAmount, $paymentCurrency, $paymentType, $paymentMethod, $bankName, $description)
    {
        $db = \App\Core\Database::getInstance();
        $transactionModel = new \App\Models\Transaction();
        
        // Get the transactions to be paid
        $placeholders = str_repeat('?,', count($transactionIds) - 1) . '?';
        $sql = "SELECT id, transaction_no, balance_rmb, balance_usd, balance_sdg, balance_aed 
                FROM transactions 
                WHERE id IN ($placeholders) AND client_id = ? AND status = 'approved'";
        
        $params = array_merge($transactionIds, [$clientId]);
        $stmt = $db->query($sql, $params);
        $transactions = $stmt->fetchAll();
        
        if (empty($transactions)) {
            throw new \Exception(__('messages.no_transactions_found'));
        }
        
        $balanceField = 'balance_' . strtolower($paymentCurrency);
        $paymentField = 'payment_' . strtolower($paymentCurrency);
        $remainingAmount = $totalPaymentAmount;
        
        foreach ($transactions as $transaction) {
            if ($remainingAmount <= 0) break;
            
            $transactionBalance = $transaction[$balanceField];
            $outstandingAmount = getOutstandingAmount($transactionBalance);
            if ($outstandingAmount <= 0) continue;
            
            $paymentForThisTransaction = min($remainingAmount, $outstandingAmount);
            
            // Update transaction payment and balance using correct accounting logic
            $updateSql = "UPDATE transactions 
                         SET {$paymentField} = {$paymentField} + ?, 
                             {$balanceField} = {$balanceField} + ? 
                         WHERE id = ?";
            $db->query($updateSql, [$paymentForThisTransaction, $paymentForThisTransaction, $transaction['id']]);
            
            $remainingAmount -= $paymentForThisTransaction;
        }
        
        // Update client balance using correct accounting logic (add to payments increases balance towards positive)
        $clientBalanceField = 'balance_' . strtolower($paymentCurrency);
        $updateClientSql = "UPDATE clients SET {$clientBalanceField} = {$clientBalanceField} + ? WHERE id = ?";
        $db->query($updateClientSql, [$totalPaymentAmount - $remainingAmount, $clientId]);
        
        // Create payment transaction record
        $this->createPaymentTransaction($clientId, $totalPaymentAmount - $remainingAmount, $paymentCurrency, $paymentMethod, $bankName, $description, count($transactions));
        
        // 2025-01-11: تسجيل دفعة العميل المحددة للمعاملات
        $client = $this->clientModel->find($clientId);
        $this->logActivity('client_payment', $_SESSION['user_id'], "دفعة عميل: {$client['name']} - {$totalPaymentAmount} {$paymentCurrency}", 'clients', $clientId);
        
        if ($remainingAmount > 0) {
            throw new \Exception(__('messages.partial_payment_applied') . " Remaining: " . $remainingAmount);
        }
    }
    
    /**
     * Process general payment (not for specific transactions)
     */
    private function processGeneralPayment($clientId, $paymentAmount, $paymentCurrency, $paymentMethod, $bankName, $description)
    {
        $db = \App\Core\Database::getInstance();
        
        // Update client balance using correct accounting logic (add to payments increases balance towards positive)
        $balanceField = 'balance_' . strtolower($paymentCurrency);
        $updateSql = "UPDATE clients SET {$balanceField} = {$balanceField} + ? WHERE id = ?";
        $db->query($updateSql, [$paymentAmount, $clientId]);
        
        // Create payment transaction record
        $this->createPaymentTransaction($clientId, $paymentAmount, $paymentCurrency, $paymentMethod, $bankName, $description);
        
        // 2025-01-11: تسجيل دفعة العميل العامة
        $client = $this->clientModel->find($clientId);
        $this->logActivity('client_payment', $_SESSION['user_id'], "دفعة عامة من العميل: {$client['name']} - {$paymentAmount} {$paymentCurrency}", 'clients', $clientId);
    }
    
    /**
     * Create payment transaction record
     */
    private function createPaymentTransaction($clientId, $paymentAmount, $paymentCurrency, $paymentMethod, $bankName, $description, $transactionCount = 0)
    {
        $db = \App\Core\Database::getInstance();
        $client = $this->clientModel->find($clientId);
        
        // Generate transaction number
        $transactionNo = $this->generateTransactionNumber();
        
        $desc = $description ?: "Payment received from {$client['name']} ({$client['client_code']})";
        if ($transactionCount > 1) {
            $desc .= " for {$transactionCount} transactions";
        }
        
        // Prepare transaction data
        $transactionData = [
            'transaction_no' => $transactionNo,
            'client_id' => $clientId,
            'transaction_type_id' => 2, // Payment Received type
            'transaction_date' => date('Y-m-d'),
            'description' => $desc,
            'bank_name' => $bankName,
            'payment_' . strtolower($paymentCurrency) => $paymentAmount,
            'balance_' . strtolower($paymentCurrency) => -$paymentAmount,
            'goods_amount_rmb' => 0,
            'commission_rmb' => 0,
            'total_amount_rmb' => 0,
            'created_by' => $_SESSION['user_id'],
            'status' => 'approved'
        ];
        
        // Insert transaction
        $transactionModel = new \App\Models\Transaction();
        $transactionId = $transactionModel->create($transactionData);
        
        // Create cashbox entry
        $cashboxData = [
            'movement_date' => date('Y-m-d'),
            'movement_type' => 'in',
            'category' => 'payment_received',
            'amount_' . strtolower($paymentCurrency) => $paymentAmount,
            'bank_name' => $bankName,
            'description' => "Payment from {$client['name']} - {$transactionNo}",
            'transaction_id' => $transactionId,
            'created_by' => $_SESSION['user_id']
        ];
        
        $cashboxModel = new \App\Models\Cashbox();
        $cashboxModel->create($cashboxData);
    }
    
    /**
     * Generate unique transaction number
     */
    private function generateTransactionNumber()
    {
        $db = \App\Core\Database::getInstance();
        $year = date('Y');
        
        // Get the last transaction number for this year
        $stmt = $db->query(
            "SELECT MAX(CAST(SUBSTRING(transaction_no, -6) AS UNSIGNED)) as last_num 
             FROM transactions 
             WHERE transaction_no LIKE ?",
            ["TRX-{$year}-%"]
        );
        $result = $stmt->fetch();
        $lastNum = $result['last_num'] ?? 0;
        $nextNum = $lastNum + 1;
        
        return sprintf("TRX-%s-%06d", $year, $nextNum);
    }
    
    /**
     * Log activity to audit_log table
     * 2025-01-11: إضافة تسجيل النشاطات لعمليات العملاء
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
                $tableName ?? 'clients',
                $recordId,
                $jsonData,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log activity in ClientController: " . $e->getMessage());
        }
    }
}