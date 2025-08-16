<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Cashbox;

class CashboxController extends Controller
{
    private $cashboxModel;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->cashboxModel = new Cashbox();
        $this->db = Database::getInstance();
    }
    
    public function index()
    {
        // Get current balance
        $currentBalance = $this->cashboxModel->getCurrentBalance();
        
        // Get filter parameters
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $category = $_GET['category'] ?? null;
        
        // Get movements
        $movements = $this->cashboxModel->getMovements($startDate, $endDate, $category);
        
        // Get daily summary for today
        $todaySummary = $this->cashboxModel->getDailySummary(date('Y-m-d'));
        
        $this->view('cashbox/index', [
            'title' => __('cashbox.title'),
            'currentBalance' => $currentBalance,
            'movements' => $movements,
            'todaySummary' => $todaySummary,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedCategory' => $category
        ]);
    }
    
    public function movement()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'movement_date' => $_POST['movement_date'],
                'movement_type' => $_POST['movement_type'],
                'category' => $_POST['category'],
                'amount_rmb' => $_POST['amount_rmb'] ?? 0,
                'amount_usd' => $_POST['amount_usd'] ?? 0,
                'amount_sdg' => $_POST['amount_sdg'] ?? 0,
                'amount_aed' => $_POST['amount_aed'] ?? 0,
                'bank_name' => $_POST['bank_name'] ?? null,
                'tt_number' => $_POST['tt_number'] ?? null,
                'receipt_no' => $_POST['receipt_no'] ?? null,
                'description' => $_POST['description'] ?? null,
                'created_by' => $_SESSION['user_id']
            ];
            
            // Calculate balance after movement
            $currentBalance = $this->cashboxModel->getCurrentBalance();
            if ($data['movement_type'] === 'in') {
                $data['balance_after_rmb'] = ($currentBalance['balance_rmb'] ?? 0) + $data['amount_rmb'];
                $data['balance_after_usd'] = ($currentBalance['balance_usd'] ?? 0) + $data['amount_usd'];
                $data['balance_after_sdg'] = ($currentBalance['balance_sdg'] ?? 0) + $data['amount_sdg'];
                $data['balance_after_aed'] = ($currentBalance['balance_aed'] ?? 0) + $data['amount_aed'];
            } else {
                $data['balance_after_rmb'] = ($currentBalance['balance_rmb'] ?? 0) - $data['amount_rmb'];
                $data['balance_after_usd'] = ($currentBalance['balance_usd'] ?? 0) - $data['amount_usd'];
                $data['balance_after_sdg'] = ($currentBalance['balance_sdg'] ?? 0) - $data['amount_sdg'];
                $data['balance_after_aed'] = ($currentBalance['balance_aed'] ?? 0) - $data['amount_aed'];
            }
            
            try {
                $movementId = $this->cashboxModel->create($data);
                
                // 2025-01-11: تسجيل حركة الخزنة
                $movementTypeAr = $data['movement_type'] === 'in' ? 'إيداع' : 'سحب';
                $amounts = [];
                if ($data['amount_rmb'] > 0) $amounts[] = $data['amount_rmb'] . ' RMB';
                if ($data['amount_usd'] > 0) $amounts[] = $data['amount_usd'] . ' USD';
                if ($data['amount_sdg'] > 0) $amounts[] = $data['amount_sdg'] . ' SDG';
                if ($data['amount_aed'] > 0) $amounts[] = $data['amount_aed'] . ' AED';
                
                $description = sprintf('حركة خزنة: %s - %s - %s', 
                    $movementTypeAr, 
                    implode(', ', $amounts),
                    $data['description'] ?? ''
                );
                
                $this->logActivity('cashbox_movement', $_SESSION['user_id'], $description, 'cashbox', $movementId);
                
                $this->redirect('/cashbox?success=' . urlencode(__('cashbox.movement_added')));
            } catch (\Exception $e) {
                $this->view('cashbox/movement', [
                    'title' => __('cashbox.movement'),
                    'error' => __('messages.operation_failed') . ': ' . $e->getMessage(),
                    'data' => $data
                ]);
                return;
            }
        }
        
        $this->view('cashbox/movement', [
            'title' => __('cashbox.movement')
        ]);
    }
    
    /**
     * Log activity to audit_log table
     * 2025-01-11: إضافة تسجيل النشاطات لحركات الخزنة
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
                $tableName ?? 'cashbox',
                $recordId,
                $jsonData,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log activity in CashboxController: " . $e->getMessage());
        }
    }
}