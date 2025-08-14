<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Client;

class ClientController extends Controller
{
    private $clientModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->clientModel = new Client();
    }
    
    public function index()
    {
    $clientModel = new Client();
    
    // Use the new method that includes transaction count
    $clients = $clientModel->allWithTransactionCount(['status' => 'active']);
    
    $this->view('clients/index', [
        'title' => __('clients.title'),
        'clients' => $clients
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
        
        $this->view('reports/client-statement', [
            'title' => 'Client Statement - ' . $client['name'],
            'client' => $client,
            'transactions' => $transactions,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }
    
    /**
     * Process client payment
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
        
        // Check if client has sufficient balance in selected currency
        $balanceField = 'balance_' . strtolower($paymentCurrency);
        if (!isset($client[$balanceField]) || $client[$balanceField] < $paymentAmount) {
            $_SESSION['error'] = __('messages.insufficient_balance');
            $this->redirect('/clients/statement/' . $clientId);
            return;
        }
        
        try {
            $db = \App\Core\Database::getInstance();
            $db->getConnection()->beginTransaction();
            
            // Generate transaction number
            $transactionNo = $this->generateTransactionNumber();
            
            // Prepare transaction data
            $transactionData = [
                'transaction_no' => $transactionNo,
                'client_id' => $clientId,
                'transaction_type_id' => 2, // Payment Received type
                'transaction_date' => date('Y-m-d'),
                'description' => $description ?: "Payment received from {$client['name']} ({$client['client_code']})",
                'bank_name' => $bankName,
                'payment_' . strtolower($paymentCurrency) => $paymentAmount,
                'balance_' . strtolower($paymentCurrency) => -$paymentAmount, // Negative because it reduces client balance
                'created_by' => $_SESSION['user_id'],
                'status' => 'approved' // Auto-approve client payments
            ];
            
            // Add goods_amount_rmb and commission_rmb as 0 for payment transactions
            $transactionData['goods_amount_rmb'] = 0;
            $transactionData['commission_rmb'] = 0;
            $transactionData['total_amount_rmb'] = 0;
            
            // Insert transaction
            $transactionModel = new \App\Models\Transaction();
            $transactionId = $transactionModel->create($transactionData);
            
            // Update client balance
            $updateSql = "UPDATE clients SET {$balanceField} = {$balanceField} - ? WHERE id = ?";
            $db->query($updateSql, [$paymentAmount, $clientId]);
            
            // Create cashbox entry for the payment
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
}