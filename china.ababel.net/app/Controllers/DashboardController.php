<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Client;
use App\Models\Transaction;
use App\Models\Cashbox;

class DashboardController extends Controller
{
    public function index()
    {
        $clientModel = new Client();
        $transactionModel = new Transaction();
        $cashboxModel = new Cashbox();
        
        $data = [
            'title' => 'Dashboard',
            'totalClients' => count($clientModel->all(['status' => 'active'])),
            'cashboxBalance' => $cashboxModel->getCurrentBalance(),
            'recentTransactions' => $transactionModel->getLatest(5),
            'topClients' => $clientModel->getWithBalance(),
            'todaySummary' => $cashboxModel->getDailySummary(date('Y-m-d'))
        ];
        
        $this->view('dashboard/index', $data);
    }
}
