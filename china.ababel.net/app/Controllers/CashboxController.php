<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Cashbox;

class CashboxController extends Controller
{
    private $cashboxModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->cashboxModel = new Cashbox();
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
            } else {
                $data['balance_after_rmb'] = ($currentBalance['balance_rmb'] ?? 0) - $data['amount_rmb'];
                $data['balance_after_usd'] = ($currentBalance['balance_usd'] ?? 0) - $data['amount_usd'];
            }
            
            try {
                $this->cashboxModel->create($data);
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
}