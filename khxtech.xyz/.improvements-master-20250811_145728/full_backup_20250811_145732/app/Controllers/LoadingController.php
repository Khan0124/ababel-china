<?php
// app/Controllers/LoadingController.php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Loading;
use App\Models\Client;

class LoadingController extends Controller
{
    private $loadingModel;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->loadingModel = new Loading();
        $this->db = Database::getInstance();
    }
    
    /**
     * Display loadings list
     */
    public function index()
    {
        $this->view('loadings/loading_list', [
            'title' => __('loadings.title')
        ]);
    }
    
    /**
     * Show create form
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->view('loadings/add_loading', [
                'title' => __('loadings.add_new')
            ]);
            return;
        }
        
        // Handle POST request
        $this->store();
    }
    
    /**
     * Store new loading - FIXED VERSION
     */
    protected function store()
    {
        $db = \App\Core\Database::getInstance();
        
        try {
            // Get client ID from code
            $clientModel = new Client();
            $client = $clientModel->findByCode($_POST['client_code']);
            if (!$client) {
                $_SESSION['error'] = __('messages.invalid_client_code');
                $this->redirect('/loadings/create');
                return;
            }
            
            // Auto-generate claim number
            $claimNumber = $this->generateClaimNumber();
            
            // Check if loading_no is provided, if not generate it
            $loadingNo = trim($_POST['loading_no'] ?? '');
            if (empty($loadingNo)) {
                // Auto-generate loading number
                $loadingNo = $this->loadingModel->generateLoadingNumber();
            } else {
                // Check for duplicate loading number within fiscal year
                if ($this->loadingModel->isDuplicateLoadingNumber($loadingNo)) {
                    $_SESSION['error'] = __('messages.duplicate_loading_number');
                    $this->redirect('/loadings/create');
                    return;
                }
            }
            
            // Prepare data
            $data = [
                'shipping_date' => $_POST['shipping_date'],
                'loading_no' => $loadingNo,
                'claim_number' => $claimNumber,
                'container_no' => strtoupper($_POST['container_no']),
                'client_id' => $client['id'],
                'client_code' => $_POST['client_code'],
                'client_name' => $_POST['client_name'],
                'item_description' => $_POST['item_description'] ?: null,
                'cartons_count' => intval($_POST['cartons_count']),
                'purchase_amount' => floatval($_POST['purchase_amount']),
                'commission_amount' => floatval($_POST['commission_amount']),
                'total_amount' => floatval($_POST['total_amount']),
                'shipping_usd' => floatval($_POST['shipping_usd']),
                'total_with_shipping' => floatval($_POST['total_with_shipping']),
                'office' => $_POST['office'] ?: null,
                'notes' => $_POST['notes'] ?: null,
                'status' => 'pending',
                'created_by' => $_SESSION['user_id']
            ];
            
            // Create loading
            $loadingId = $this->loadingModel->create($data);
            
            // 2025-01-11: تسجيل إضافة تحميل جديد
            $this->logActivity('loading_create', $_SESSION['user_id'], 'إضافة تحميل جديد: ' . $data['loading_no'] . ' - ' . $data['client_name'], 'loadings', $loadingId);
            
            // Record financial details in client's account
            $this->recordFinancialDetails($client['id'], $data, $loadingId);
            
            // Handle Port Sudan sync if selected
            if ($data['office'] === 'port_sudan') {
                try {
                    $syncResult = $this->syncToPortSudan($loadingId, $data);
                    if ($syncResult['success']) {
                        $_SESSION['sync_success'] = 'Loading synced to Port Sudan successfully';
                        
                        // Update sync status
                        $this->loadingModel->update($loadingId, [
                            'sync_status' => 'synced',
                            'last_sync_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                } catch (\Exception $e) {
                    error_log("Port Sudan sync failed but loading created: " . $e->getMessage());
                    $_SESSION['sync_warning'] = 'Loading created but sync to Port Sudan failed. It will be retried automatically.';
                    
                    // Update sync status to failed
                    $this->loadingModel->update($loadingId, [
                        'sync_status' => 'failed',
                        'sync_attempts' => 1
                    ]);
                }
            }
            
            // Create office notification for other offices
            if ($data['office'] && $data['office'] !== 'port_sudan') {
                $this->createOfficeNotification($data['office'], $loadingId, $data);
            }
            
            $_SESSION['success'] = 'Loading created successfully';
            $this->redirect('/loadings');
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Operation failed: ' . $e->getMessage();
            $this->redirect('/loadings/create');
        }
    }
    
    /**
     * Show edit form
     */
    public function edit($id)
    {
        $loading = $this->loadingModel->find($id);
        
        if (!$loading) {
            $this->redirect('/loadings?error=' . urlencode('Loading not found'));
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->view('loadings/edit_loading', [
                'title' => 'Edit Loading',
                'loading' => $loading
            ]);
            return;
        }
        
        // Handle POST request
        $this->update($id);
    }
    
    /**
     * Update loading - FIXED VERSION
     */
    protected function update($id)
    {
        $db = \App\Core\Database::getInstance();
        
        try {
            $originalLoading = $this->loadingModel->find($id);
            if (!$originalLoading) {
                $_SESSION['error'] = 'Loading not found';
                $this->redirect('/loadings');
                return;
            }
            
            // Get client ID from code
            $clientModel = new Client();
            $client = $clientModel->findByCode($_POST['client_code']);
            if (!$client) {
                $_SESSION['error'] = 'Invalid client code';
                $this->redirect('/loadings/edit/' . $id);
                return;
            }
            
            // Check loading number (keep existing for edit)
            $loadingNo = trim($_POST['loading_no'] ?? '');
            if (empty($loadingNo)) {
                $loadingNo = $originalLoading['loading_no'];
            } else if ($loadingNo !== $originalLoading['loading_no']) {
                // Only check for duplicates if loading number has changed
                if ($this->loadingModel->isDuplicateLoadingNumber($loadingNo, $id)) {
                    $_SESSION['error'] = __('messages.duplicate_loading_number');
                    $this->redirect('/loadings/edit/' . $id);
                    return;
                }
            }
            
            // Prepare updated data
            $data = [
                'shipping_date' => $_POST['shipping_date'],
                'loading_no' => $loadingNo,
                'container_no' => strtoupper($_POST['container_no']),
                'client_id' => $client['id'],
                'client_code' => $_POST['client_code'],
                'client_name' => $_POST['client_name'],
                'item_description' => $_POST['item_description'] ?: null,
                'cartons_count' => intval($_POST['cartons_count']),
                'purchase_amount' => floatval($_POST['purchase_amount']),
                'commission_amount' => floatval($_POST['commission_amount']),
                'total_amount' => floatval($_POST['total_amount']),
                'shipping_usd' => floatval($_POST['shipping_usd']),
                'total_with_shipping' => floatval($_POST['total_with_shipping']),
                'office' => $_POST['office'] ?: null,
                'notes' => $_POST['notes'] ?: null,
                'updated_by' => $_SESSION['user_id']
            ];
            
            // Check if financial amounts changed and update client balance accordingly
            $this->adjustClientBalanceOnUpdate($originalLoading, $data, $client['id']);
            
            // Update loading
            $this->loadingModel->update($id, $data);
            
            // 2025-01-11: تسجيل تحديث بيانات التحميل
            $this->logActivity('loading_update', $_SESSION['user_id'], 'تحديث بيانات التحميل: ' . $data['loading_no'] . ' - ' . $data['client_name'], 'loadings', $id);
            
            // Sync changes to Port Sudan if applicable
            if ($originalLoading['office'] === 'port_sudan' || $data['office'] === 'port_sudan') {
                try {
                    $this->syncUpdateToPortSudan($id, $data, $originalLoading);
                    $_SESSION['sync_success'] = 'Changes synced to Port Sudan';
                } catch (\Exception $e) {
                    error_log("Port Sudan update sync failed: " . $e->getMessage());
                    $_SESSION['sync_warning'] = 'Loading updated but sync to Port Sudan failed';
                }
            }
            
            $_SESSION['success'] = 'Loading updated successfully';
            $this->redirect('/loadings');
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Operation failed: ' . $e->getMessage();
            $this->redirect('/loadings/edit/' . $id);
        }
    }
    
    /**
     * Delete loading
     */
    public function delete($id)
    {
        try {
            $loading = $this->loadingModel->find($id);
            if (!$loading) {
                $_SESSION['error'] = 'Loading not found';
                $this->redirect('/loadings');
                return;
            }
            
            // Reverse client balance before deletion
            $this->reverseClientBalanceOnDelete($loading);
            
            // Sync deletion to Port Sudan if applicable
            if ($loading['office'] === 'port_sudan') {
                try {
                    $this->syncDeletionToPortSudan($id, $loading);
                } catch (\Exception $e) {
                    error_log("Port Sudan deletion sync failed: " . $e->getMessage());
                }
            }
            
            // 2025-01-11: تسجيل حذف التحميل
            $this->logActivity('loading_delete', $_SESSION['user_id'], 'حذف التحميل: ' . $loading['loading_no'] . ' - ' . $loading['client_name'], 'loadings', $id);
            
            $this->loadingModel->delete($id);
            $_SESSION['success'] = 'Loading deleted successfully';
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Operation failed: ' . $e->getMessage();
        }
        
        $this->redirect('/loadings');
    }
    
    /**
     * Show loading details
     */
    public function show($id)
    {
        $loading = $this->loadingModel->getWithDetails($id);
        
        if (!$loading) {
            $this->redirect('/loadings?error=' . urlencode('Loading not found'));
        }
        
        $this->view('loadings/view_loading', [
            'title' => 'Loading Details',
            'loading' => $loading
        ]);
    }
    
    /**
     * Generate unique claim number
     */
    private function generateClaimNumber()
    {
        $date = date('Ymd');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return "CLM-{$date}-{$random}";
    }
    
    /**
     * Record financial details in client's account with automatic invoice creation
     */
    private function recordFinancialDetails($clientId, $data, $loadingId)
    {
        $db = \App\Core\Database::getInstance();
        
        // Calculate amounts
        $purchase = floatval($data['purchase_amount']);
        $commission = floatval($data['commission_amount']);
        $shippingUSD = floatval($data['shipping_usd']);
        
        // Get exchange rates
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'exchange_rate_%'");
        $rates = $stmt->fetchAll();
        $exchangeRates = [];
        foreach ($rates as $rate) {
            $exchangeRates[$rate['setting_key']] = floatval($rate['setting_value']);
        }
        
        $usdToRmb = $exchangeRates['exchange_rate_usd_rmb'] ?? 7.20;
        
        $shippingRMB = $shippingUSD * $usdToRmb;
        $totalRMB = $purchase + $commission + $shippingRMB;
        
        // Generate transaction number
        $transactionNo = $this->generateTransactionNumber();
        
        // Create transaction record (as an invoice/claim)
        $transactionData = [
            'transaction_no' => $transactionNo,
            'client_id' => $clientId,
            'loading_id' => $loadingId,
            'transaction_type_id' => 1, // GOODS_PURCHASE - This creates a claim/debt
            'transaction_date' => date('Y-m-d'),
            'description' => 'Invoice for Loading #' . $data['loading_no'] . ' - Container: ' . $data['container_no'],
            'description_ar' => 'فاتورة للتحميل رقم ' . $data['loading_no'] . ' - حاوية: ' . $data['container_no'],
            'invoice_no' => 'INV-' . date('Ymd') . '-' . $data['loading_no'],
            'loading_no' => $data['loading_no'],
            'goods_amount_rmb' => $purchase,
            'commission_rmb' => $commission,
            'shipping_usd' => $shippingUSD,
            'total_amount_rmb' => $totalRMB,
            'payment_rmb' => 0, // No payment yet - this is a claim
            'balance_rmb' => $totalRMB, // Full amount is owed
            'payment_usd' => 0, // No payment yet
            'balance_usd' => $shippingUSD, // Full shipping amount is owed
            'payment_sdg' => 0,
            'payment_aed' => 0,
            'balance_sdg' => 0,
            'balance_aed' => 0,
            'rate_usd_rmb' => $usdToRmb,
            'created_by' => $_SESSION['user_id'],
            'status' => 'pending' // Changed to pending until paid
        ];
        
        // Insert transaction
        $columns = implode(',', array_keys($transactionData));
        $placeholders = implode(',', array_fill(0, count($transactionData), '?'));
        $db->query(
            "INSERT INTO transactions ($columns) VALUES ($placeholders)",
            array_values($transactionData)
        );
        
        // Log in loading_financial_records for tracking
        $db->query(
            "INSERT INTO loading_financial_records (loading_id, client_id, transaction_type, amount_rmb, amount_usd, description) 
             VALUES (?, ?, 'purchase', ?, ?, ?)",
            [$loadingId, $clientId, $totalRMB, $shippingUSD, 'Automatic claim/invoice created for loading']
        );
        
        // Update client balance to reflect the new claim
        $this->updateClientBalanceFromTransactions($clientId);
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
     * Sync loading data to Port Sudan system - FIXED VERSION
     */
    private function syncToPortSudan($loadingId, $data)
    {
        $syncData = [
            'china_loading_id' => $loadingId,
            'entry_date' => $data['shipping_date'],
            'code' => $data['client_code'],
            'client_name' => $data['client_name'],
            'loading_number' => $data['loading_no'],
            'carton_count' => $data['cartons_count'],
            'container_number' => $data['container_no'],
            'bill_number' => $data['claim_number'] ?? '',
            'category' => $data['item_description'] ?: 'General Cargo',
            'carrier' => 'TBD',
            'expected_arrival' => date('Y-m-d', strtotime($data['shipping_date'] . ' +30 days')),
            'ship_name' => 'TBD',
            'custom_station' => 'Port Sudan',
            'office' => 'بورتسودان'
        ];
        
        // Call Port Sudan API
        $response = $this->callPortSudanAPI('', $syncData, $loadingId);
        
        if ($response['success']) {
            // Create notification for Port Sudan
            $this->createPortSudanNotification($loadingId, $data);
        }
        
        return $response;
    }
    
    /**
     * Sync loading updates to Port Sudan
     */
    private function syncUpdateToPortSudan($loadingId, $newData, $originalData)
    {
        $updateData = [
            'china_loading_id' => $loadingId,
            'entry_date' => $newData['shipping_date'],
            'client_name' => $newData['client_name'],
            'loading_number' => $newData['loading_no'],
            'carton_count' => $newData['cartons_count'],
            'container_number' => $newData['container_no'],
            'category' => $newData['item_description'] ?: 'General Cargo'
        ];
        
        return $this->callPortSudanAPI('/containers/update', $updateData, $loadingId);
    }
    
    /**
     * Sync deletion to Port Sudan
     */
    private function syncDeletionToPortSudan($loadingId, $loadingData)
    {
        $deleteData = ['china_loading_id' => $loadingId];
        return $this->callPortSudanAPI('/containers/delete', $deleteData, $loadingId, 'DELETE');
    }
    
    /**
     * Create notification for Port Sudan office
     */
    private function createPortSudanNotification($loadingId, $data)
    {
        error_log("Port Sudan notification created for loading $loadingId");
    }
    
    /**
     * Call Port Sudan API for synchronization - FIXED VERSION
     */
    private function callPortSudanAPI($endpoint, $data, $loadingId, $method = 'POST')
    {
        $db = \App\Core\Database::getInstance();
        
        // Log the sync attempt
        error_log("Attempting Port Sudan sync for loading $loadingId");
        error_log("Endpoint: $endpoint");
        error_log("Data: " . json_encode($data));
        
        try {
            // Use the china_sync.php endpoint
            $apiUrl = 'https://ababel.net/app/api/china_sync.php' . $endpoint;
            
            // Get API key from config (should be in environment variable in production)
            $apiKey = defined('PORT_SUDAN_API_KEY') ? PORT_SUDAN_API_KEY : 'AB@1234X-China2Port!';
            
            $postData = json_encode($data);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-API-Key: ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            // Enable verbose output for debugging
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            // Get verbose output
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            error_log("CURL Verbose: " . $verboseLog);
            
            curl_close($ch);
            
            // Log response
            error_log("HTTP Code: $httpCode");
            error_log("Response: $response");
            error_log("Error: $error");
            
            // Log sync attempt
            if ($this->tableExists('api_sync_log')) {
                $this->logSyncAttempt($endpoint, $loadingId, $data, $httpCode, $response);
            }
            
            if ($error) {
                throw new \Exception("CURL Error: " . $error);
            }
            
            if ($httpCode >= 400) {
                $responseData = json_decode($response, true);
                $errorMsg = isset($responseData['error']) ? $responseData['error'] : "HTTP Error $httpCode";
                throw new \Exception("API Error: " . $errorMsg);
            }
            
            $responseData = json_decode($response, true);
            if (!$responseData) {
                throw new \Exception("Invalid JSON response");
            }
            
            return $responseData;
            
        } catch (\Exception $e) {
            // Log error
            error_log("Port Sudan sync failed: " . $e->getMessage());
            
            // Log failed sync attempt
            if ($this->tableExists('api_sync_log')) {
                $this->logSyncAttempt($endpoint, $loadingId, $data, 0, json_encode(['error' => $e->getMessage()]));
            }
            
            throw $e;
        }
    }
    
    /**
     * Check if table exists in current database
     */
    private function tableExists($tableName)
    {
        $db = \App\Core\Database::getInstance();
        try {
            $stmt = $db->query("SHOW TABLES LIKE ?", [$tableName]);
            return $stmt->fetch() !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Adjust client balance when loading financial amounts are updated
     */
    private function adjustClientBalanceOnUpdate($originalLoading, $newData, $clientId)
    {
        $db = \App\Core\Database::getInstance();
        
        // If amounts changed, update the related transaction
        $originalTotal = floatval($originalLoading['purchase_amount']) + floatval($originalLoading['commission_amount']);
        $newTotal = floatval($newData['purchase_amount']) + floatval($newData['commission_amount']);
        $originalShippingUSD = floatval($originalLoading['shipping_usd']);
        $newShippingUSD = floatval($newData['shipping_usd']);
        
        // Get exchange rates
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'exchange_rate_%'");
        $rates = $stmt->fetchAll();
        $exchangeRates = [];
        foreach ($rates as $rate) {
            $exchangeRates[$rate['setting_key']] = floatval($rate['setting_value']);
        }
        
        $usdToRmb = $exchangeRates['exchange_rate_usd_rmb'] ?? 7.20;
        
        // Calculate new totals
        $newShippingRMB = $newShippingUSD * $usdToRmb;
        $newTotalRMB = $newTotal + $newShippingRMB;
        
        // If amounts changed, update the related transaction
        if ($originalTotal != $newTotal || $originalShippingUSD != $newShippingUSD) {
            // Find and update the related transaction
            $transactionStmt = $db->query(
                "SELECT id, payment_rmb, payment_usd FROM transactions WHERE loading_id = ? AND client_id = ? AND transaction_type_id = 1",
                [$originalLoading['id'], $clientId]
            );
            $transaction = $transactionStmt->fetch();
            
            if ($transaction) {
                // Recalculate balance based on existing payments
                $newBalanceRmb = $newTotalRMB - floatval($transaction['payment_rmb']);
                $newBalanceUsd = $newShippingUSD - floatval($transaction['payment_usd']);
                
                $db->query(
                    "UPDATE transactions SET 
                        goods_amount_rmb = ?,
                        commission_rmb = ?,
                        shipping_usd = ?,
                        total_amount_rmb = ?,
                        balance_rmb = ?,
                        balance_usd = ?,
                        rate_usd_rmb = ?,
                        updated_at = NOW()
                    WHERE id = ?",
                    [
                        floatval($newData['purchase_amount']), 
                        floatval($newData['commission_amount']), 
                        $newShippingUSD, 
                        $newTotalRMB,
                        $newBalanceRmb,
                        $newBalanceUsd,
                        $usdToRmb,
                        $transaction['id']
                    ]
                );
            }
            
            // Recalculate client balance from all transactions
            $this->updateClientBalanceFromTransactions($clientId);
        }
    }
    
    /**
     * Reverse client balance when loading is deleted
     */
    private function reverseClientBalanceOnDelete($loading)
    {
        $db = \App\Core\Database::getInstance();
        
        // Delete the related transaction record if it exists
        $db->query(
            "DELETE FROM transactions WHERE loading_id = ? AND client_id = ?",
            [$loading['id'], $loading['client_id']]
        );
        
        // Delete from loading_financial_records
        $db->query(
            "DELETE FROM loading_financial_records WHERE loading_id = ?",
            [$loading['id']]
        );
        
        // Recalculate client balance from remaining transactions
        $this->updateClientBalanceFromTransactions($loading['client_id']);
    }
    
    /**
     * Log sync attempt
     */
    private function logSyncAttempt($endpoint, $chinaLoadingId, $requestData, $responseCode, $responseData)
    {
        $db = \App\Core\Database::getInstance();
        
        try {
            $sql = "INSERT INTO api_sync_log (endpoint, method, china_loading_id, request_data, response_code, response_data, ip_address, created_at) 
                   VALUES (?, 'POST', ?, ?, ?, ?, ?, NOW())";
            
            $db->query($sql, [
                $endpoint,
                $chinaLoadingId,
                json_encode($requestData),
                $responseCode,
                $responseData,
                $_SERVER['REMOTE_ADDR'] ?? 'system'
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log sync attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Create office notification for non-Port Sudan offices
     */
    private function createOfficeNotification($office, $loadingId, $data)
    {
        $db = \App\Core\Database::getInstance();
        
        $officeNames = [
            'uae' => 'UAE Office',
            'tanzania' => 'Tanzania Office',
            'egypt' => 'Egypt Office'
        ];
        
        $officeName = $officeNames[$office] ?? $office;
        $message = "New loading assigned to {$officeName}: Container {$data['container_no']} for client {$data['client_name']}";
        
        try {
            // Try to insert notification if table exists
            if ($this->tableExists('office_notifications')) {
                $sql = "INSERT INTO office_notifications (office, type, message, reference_type, reference_id, created_at) 
                       VALUES (?, 'loading_assigned', ?, 'loading', ?, NOW())";
                $db->query($sql, [$office, $message, $loadingId]);
            }
        } catch (\Exception $e) {
            error_log("Office notification failed: " . $e->getMessage());
        }
    }
    
    /**
     * Export loadings to Excel
     */
    public function export()
    {
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $loadings = $this->loadingModel->getFiltered($_GET);
        
        // Create new Spreadsheet object
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headers = [
            'A1' => 'Loading No',
            'B1' => 'Shipping Date',
            'C1' => 'Claim Number',
            'D1' => 'Container No',
            'E1' => 'Client Code',
            'F1' => 'Client Name',
            'G1' => 'Item Description',
            'H1' => 'Cartons Count',
            'I1' => 'Purchase Amount (¥)',
            'J1' => 'Commission Amount (¥)',
            'K1' => 'Total Amount (¥)',
            'L1' => 'Shipping (USD)',
            'M1' => 'Total with Shipping (¥)',
            'N1' => 'Office',
            'O1' => 'Status',
            'P1' => 'Notes'
        ];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF4472C4');
            $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
        }
        
        // Add data
        $row = 2;
        foreach ($loadings as $loading) {
            $sheet->setCellValue('A' . $row, $loading['loading_no'] ?? '');
            $sheet->setCellValue('B' . $row, date('d/m/Y', strtotime($loading['shipping_date'])));
            $sheet->setCellValue('C' . $row, $loading['claim_number'] ?? '');
            $sheet->setCellValue('D' . $row, $loading['container_no']);
            $sheet->setCellValue('E' . $row, $loading['client_code']);
            $sheet->setCellValue('F' . $row, $loading['client_name'] ?? $loading['client_name_db']);
            $sheet->setCellValue('G' . $row, $loading['item_description'] ?? '');
            $sheet->setCellValue('H' . $row, $loading['cartons_count']);
            $sheet->setCellValue('I' . $row, $loading['purchase_amount']);
            $sheet->setCellValue('J' . $row, $loading['commission_amount']);
            $sheet->setCellValue('K' . $row, $loading['total_amount']);
            $sheet->setCellValue('L' . $row, $loading['shipping_usd']);
            $sheet->setCellValue('M' . $row, $loading['total_with_shipping']);
            $sheet->setCellValue('N' . $row, $loading['office'] ?? '');
            $sheet->setCellValue('O' . $row, $loading['status']);
            $sheet->setCellValue('P' . $row, $loading['notes'] ?? '');
            
            // Format numbers
            $sheet->getStyle('I' . $row . ':K' . $row)->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $sheet->getStyle('L' . $row)->getNumberFormat()
                ->setFormatCode('$#,##0.00');
            $sheet->getStyle('M' . $row)->getNumberFormat()
                ->setFormatCode('#,##0.00');
            
            $row++;
        }
        
        // Add totals row
        $totalRow = $row;
        $sheet->setCellValue('G' . $totalRow, 'TOTAL');
        $sheet->setCellValue('H' . $totalRow, '=SUM(H2:H' . ($row - 1) . ')');
        $sheet->setCellValue('I' . $totalRow, '=SUM(I2:I' . ($row - 1) . ')');
        $sheet->setCellValue('J' . $totalRow, '=SUM(J2:J' . ($row - 1) . ')');
        $sheet->setCellValue('K' . $totalRow, '=SUM(K2:K' . ($row - 1) . ')');
        $sheet->setCellValue('L' . $totalRow, '=SUM(L2:L' . ($row - 1) . ')');
        $sheet->setCellValue('M' . $totalRow, '=SUM(M2:M' . ($row - 1) . ')');
        
        // Style totals row
        $sheet->getStyle('G' . $totalRow . ':M' . $totalRow)->getFont()->setBold(true);
        $sheet->getStyle('G' . $totalRow . ':M' . $totalRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE7E6E6');
        
        // Auto-size columns
        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Set active sheet title
        $sheet->setTitle('Loadings Export');
        
        // Create writer and output
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="loadings_export_' . date('Y_m_d_H_i_s') . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Update loading status
     */
    public function updateStatus($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/loadings');
            return;
        }
        
        $status = $_POST['status'] ?? '';
        $validStatuses = ['pending', 'shipped', 'arrived', 'cleared', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            $_SESSION['error'] = 'Invalid status';
            $this->redirect('/loadings');
            return;
        }
        
        try {
            $loading = $this->loadingModel->find($id);
            $this->loadingModel->update($id, [
                'status' => $status,
                'updated_by' => $_SESSION['user_id']
            ]);
            
            // 2025-01-11: تسجيل تحديث حالة التحميل
            $statusArabic = ['pending' => 'معلق', 'shipped' => 'تم الشحن', 'arrived' => 'وصل', 'cleared' => 'تم التخليص', 'cancelled' => 'ملغي'][$status] ?? $status;
            $this->logActivity('loading_status_change', $_SESSION['user_id'], "تحديث حالة التحميل {$loading['loading_no']} إلى: {$statusArabic}", 'loadings', $id);
            
            $_SESSION['success'] = 'Status updated successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Operation failed';
        }
        
        $this->redirect('/loadings');
    }
    
    /**
     * Manually retry sync for a loading
     */
    public function retrySync($id)
    {
        try {
            $loading = $this->loadingModel->find($id);
            if (!$loading) {
                $_SESSION['error'] = 'Loading not found';
                $this->redirect('/loadings');
                return;
            }
            
            if ($loading['office'] !== 'port_sudan') {
                $_SESSION['error'] = 'This loading is not assigned to Port Sudan';
                $this->redirect('/loadings');
                return;
            }
            
            // Prepare sync data
            $syncData = [
                'shipping_date' => $loading['shipping_date'],
                'loading_no' => $loading['loading_no'],
                'claim_number' => $loading['claim_number'],
                'container_no' => $loading['container_no'],
                'client_code' => $loading['client_code'],
                'client_name' => $loading['client_name'],
                'item_description' => $loading['item_description'],
                'cartons_count' => $loading['cartons_count'],
                'office' => 'port_sudan'
            ];
            
            $syncResult = $this->syncToPortSudan($id, $syncData);
            
            if ($syncResult['success']) {
                $_SESSION['success'] = 'Loading synced to Port Sudan successfully';
                
                // Update sync status
                $this->loadingModel->update($id, [
                    'sync_status' => 'synced',
                    'last_sync_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                $_SESSION['error'] = 'Sync failed: ' . ($syncResult['message'] ?? 'Unknown error');
            }
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Sync failed: ' . $e->getMessage();
        }
        
        $this->redirect('/loadings');
    }
    
    public function issueBol($id)
    {
        $loading = $this->loadingModel->find($id);
        if (!$loading) {
            $this->redirect('/loadings?error=' . urlencode(__('messages.loading_not_found')));
        }
        
        if ($loading['bol_number']) {
            $this->redirect('/loadings/show/' . $id . '?error=' . urlencode(__('messages.bol_already_issued')));
        }
        
        try {
            $db = \App\Core\Database::getInstance();
            $db->getConnection()->beginTransaction();
            
            $bolNumber = 'BOL-' . date('Ymd') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
            
            // Update loading with BOL information
            $this->loadingModel->update($id, [
                'bol_number' => $bolNumber,
                'bol_issued_date' => date('Y-m-d'),
                'bol_issued_by' => $_SESSION['user_id']
            ]);
            
            // Trigger sync if office is port_sudan (but don't fail BOL issuance if sync fails)
            if ($loading['office'] === 'port_sudan') {
                try {
                    $this->triggerSync($id);
                    error_log("Port Sudan sync successful for BOL issuance on loading $id");
                } catch (\Exception $syncError) {
                    error_log("Port Sudan sync failed for BOL issuance on loading $id: " . $syncError->getMessage());
                    // Don't throw exception - BOL should still be issued even if sync fails
                }
            }
            
            // 2025-01-11: تسجيل إصدار بوليصة الشحن
            $this->logActivity('bol_issued', $_SESSION['user_id'], "إصدار بوليصة الشحن: {$bolNumber} للتحميل: {$loading['loading_no']}", 'loadings', $id);
            
            $db->getConnection()->commit();
            
            $this->redirect('/loadings/show/' . $id . '?success=' . urlencode(__('loadings.bol_issued_successfully')));
        } catch (\Exception $e) {
            if (isset($db) && $db->getConnection()->inTransaction()) {
                $db->getConnection()->rollback();
            }
            
            // Log the actual error for debugging
            error_log("BOL Issue Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            
            $this->redirect('/loadings/show/' . $id . '?error=' . urlencode(__('messages.error_issuing_bol') . ': ' . $e->getMessage()));
        }
    }

    private function triggerSync($loadingId)
    {
        $loading = $this->loadingModel->find($loadingId);
        if (!$loading) {
            return false;
        }
        
        $syncData = [
            'action' => 'create_container',
            'data' => [
                'china_loading_id' => $loadingId,
                'entry_date' => date('Y-m-d'),
                'code' => $loading['client_code'],
                'client_name' => $loading['client_name'],
                'loading_number' => $loading['loading_no'],
                'carton_count' => $loading['cartons_count'],
                'container_number' => $loading['container_no'],
                'bill_number' => $loading['claim_number'],
                'category' => $loading['item_description'],
                'carrier' => 'TBD',
                'expected_arrival' => $loading['arrival_date'] ?: date('Y-m-d', strtotime('+30 days')),
                'ship_name' => 'TBD',
                'custom_station' => 'Port Sudan',
                'office' => 'بورتسودان'
            ]
        ];
        
        $db = \App\Core\Database::getInstance();
        
        // Get API settings with proper key-value mapping
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('port_sudan_api_url', 'port_sudan_api_key')");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $apiUrl = $settings['port_sudan_api_url'] ?? '';
            $apiKey = $settings['port_sudan_api_key'] ?? '';
            
            // Debug log
            error_log("Retrieved API settings: URL=" . $apiUrl . ", Key=" . substr($apiKey, 0, 5) . "...");
            
            if (empty($apiUrl) || empty($apiKey)) {
                throw new \Exception('API settings not configured. Please configure port_sudan_api_url and port_sudan_api_key in system settings.');
            }
        } catch (\Exception $e) {
            error_log("Error retrieving API settings: " . $e->getMessage());
            throw new \Exception('Failed to retrieve API settings: ' . $e->getMessage());
        }
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($syncData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $db->query("INSERT INTO api_sync_log (endpoint, method, china_loading_id, request_data, response_code, response_data) VALUES (?, ?, ?, ?, ?, ?)", [
            $apiUrl,
            'POST',
            $loadingId,
            json_encode($syncData),
            $httpCode,
            $response
        ]);
        
        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            if ($responseData && isset($responseData['container_id'])) {
                $this->loadingModel->update($loadingId, [
                    'sync_status' => 'synced',
                    'last_sync_at' => date('Y-m-d H:i:s'),
                    'port_sudan_id' => $responseData['container_id']
                ]);
                
                $db->query("INSERT INTO loading_sync_log (loading_id, action, status, response_data) VALUES (?, ?, ?, ?)", [
                    $loadingId,
                    'create',
                    'success',
                    $response
                ]);
                return true;
            }
        }
        
        $this->loadingModel->update($loadingId, [
            'sync_status' => 'failed',
            'sync_attempts' => ($loading['sync_attempts'] ?? 0) + 1,
            'last_sync_at' => date('Y-m-d H:i:s')
        ]);
        
        $db->query("INSERT INTO loading_sync_log (loading_id, action, status, error_message) VALUES (?, ?, ?, ?)", [
            $loadingId,
            'create',
            'failed',
            'HTTP Code: ' . $httpCode . ' - Response: ' . $response
        ]);
        
        return false;
    }

    /**
     * Update client balance from all approved transactions - FIXED VERSION
     */
    private function updateClientBalanceFromTransactions($clientId)
    {
        $db = \App\Core\Database::getInstance();
        
        // حساب إجمالي المطالبات والمدفوعات بشكل صحيح
        $sql = "SELECT 
                -- المطالبات: المبالغ الإجمالية للمعاملات من نوع expense
                SUM(CASE WHEN tt.type = 'expense' THEN t.total_amount_rmb ELSE 0 END) as total_claims_rmb,
                SUM(CASE WHEN tt.type = 'expense' THEN t.shipping_usd ELSE 0 END) as total_claims_usd,
                
                -- المدفوعات: جميع المدفوعات بغض النظر عن نوع المعاملة
                SUM(t.payment_rmb) as total_payments_rmb,
                SUM(t.payment_usd) as total_payments_usd,
                SUM(t.payment_sdg) as total_payments_sdg,
                SUM(t.payment_aed) as total_payments_aed
                
                FROM transactions t
                JOIN transaction_types tt ON t.transaction_type_id = tt.id
                WHERE t.client_id = ? AND t.status IN ('approved', 'pending')";
        
        $stmt = $db->query($sql, [$clientId]);
        $balances = $stmt->fetch();
        
        // حساب الرصيد الصافي بالمعايير المحاسبية الدولية (المدفوعات - المطالبات)
        // رصيد سالب = العميل مدين للشركة، رصيد موجب = الشركة مدينة للعميل
        $balanceRmb = ($balances['total_payments_rmb'] ?? 0) - ($balances['total_claims_rmb'] ?? 0);
        $balanceUsd = ($balances['total_payments_usd'] ?? 0) - ($balances['total_claims_usd'] ?? 0);
        $balanceSdg = ($balances['total_payments_sdg'] ?? 0) - 0; // SDG دفعات فقط
        $balanceAed = ($balances['total_payments_aed'] ?? 0) - 0; // AED دفعات فقط
        
        // تحديث أرصدة العميل
        $clientModel = new \App\Models\Client();
        $clientModel->updateBalance($clientId, $balanceRmb, $balanceUsd, $balanceSdg, $balanceAed);
    }
    
    /**
     * Log activity to audit_log table
     * 2025-01-11: إضافة تسجيل النشاطات لعمليات التحميل
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
                $tableName ?? 'loadings',
                $recordId,
                $jsonData,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log activity in LoadingController: " . $e->getMessage());
        }
    }
}