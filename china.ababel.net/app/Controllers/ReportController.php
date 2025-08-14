<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Client;
use App\Models\Transaction;
use App\Models\Cashbox;

class ReportController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function daily()
    {
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $transactionModel = new Transaction();
        $cashboxModel = new Cashbox();
        $clientModel = new Client();
        
        // Get daily transactions
        $transactions = $transactionModel->all([
            'transaction_date' => $date,
            'status' => 'approved'
        ], 'id ASC');
        
        // Get daily cashbox movements
        $movements = $cashboxModel->getMovements($date, $date);
        
        // Calculate daily totals
        $dailyTotals = [
            'transactions_count' => count($transactions),
            'total_goods_rmb' => 0,
            'total_commission_rmb' => 0,
            'total_payments_rmb' => 0,
            'total_payments_usd' => 0,
            'cashbox_in_rmb' => 0,
            'cashbox_out_rmb' => 0,
            'cashbox_in_usd' => 0,
            'cashbox_out_usd' => 0
        ];
        
        foreach ($transactions as $transaction) {
            $dailyTotals['total_goods_rmb'] += $transaction['goods_amount_rmb'];
            $dailyTotals['total_commission_rmb'] += $transaction['commission_rmb'];
            $dailyTotals['total_payments_rmb'] += $transaction['payment_rmb'];
            $dailyTotals['total_payments_usd'] += $transaction['payment_usd'];
        }
        
        foreach ($movements as $movement) {
            if ($movement['movement_type'] === 'in') {
                $dailyTotals['cashbox_in_rmb'] += $movement['amount_rmb'];
                $dailyTotals['cashbox_in_usd'] += $movement['amount_usd'];
            } else {
                $dailyTotals['cashbox_out_rmb'] += $movement['amount_rmb'];
                $dailyTotals['cashbox_out_usd'] += $movement['amount_usd'];
            }
        }
        
        $this->view('reports/daily', [
            'title' => __('reports.daily_report'),
            'date' => $date,
            'transactions' => $transactions,
            'movements' => $movements,
            'dailyTotals' => $dailyTotals
        ]);
    }
    
    public function monthly()
    {
        $month = $_GET['month'] ?? date('Y-m');
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $transactionModel = new Transaction();
        $cashboxModel = new Cashbox();
        $clientModel = new Client();
        
        // Get monthly statistics
        $sql = "
            SELECT 
                COUNT(*) as total_transactions,
                SUM(goods_amount_rmb) as total_goods_rmb,
                SUM(commission_rmb) as total_commission_rmb,
                SUM(total_amount_rmb) as total_amount_rmb,
                SUM(payment_rmb) as total_payments_rmb,
                SUM(payment_usd) as total_payments_usd,
                SUM(shipping_usd) as total_shipping_usd,
                COUNT(DISTINCT client_id) as active_clients
            FROM transactions
            WHERE transaction_date BETWEEN ? AND ?
            AND status = 'approved'
        ";
        
        $db = \App\Core\Database::getInstance();
        $stmt = $db->query($sql, [$startDate, $endDate]);
        $monthlyStats = $stmt->fetch();
        
        // Get top clients for the month
        $sql = "
            SELECT 
                c.id,
                c.name,
                c.name_ar,
                c.client_code,
                COUNT(t.id) as transaction_count,
                SUM(t.total_amount_rmb) as total_amount_rmb,
                SUM(t.payment_rmb) as total_payments_rmb
            FROM clients c
            JOIN transactions t ON c.id = t.client_id
            WHERE t.transaction_date BETWEEN ? AND ?
            AND t.status = 'approved'
            GROUP BY c.id
            ORDER BY total_amount_rmb DESC
            LIMIT 10
        ";
        
        $stmt = $db->query($sql, [$startDate, $endDate]);
        $topClients = $stmt->fetchAll();
        
        // Get cashbox summary for the month
        $movements = $cashboxModel->getMovements($startDate, $endDate);
        
        $cashboxSummary = [
            'total_in_rmb' => 0,
            'total_out_rmb' => 0,
            'total_in_usd' => 0,
            'total_out_usd' => 0,
            'movements_count' => count($movements)
        ];
        
        foreach ($movements as $movement) {
            if ($movement['movement_type'] === 'in') {
                $cashboxSummary['total_in_rmb'] += $movement['amount_rmb'];
                $cashboxSummary['total_in_usd'] += $movement['amount_usd'];
            } else {
                $cashboxSummary['total_out_rmb'] += $movement['amount_rmb'];
                $cashboxSummary['total_out_usd'] += $movement['amount_usd'];
            }
        }
        
        $this->view('reports/monthly', [
            'title' => __('reports.monthly_report'),
            'month' => $month,
            'monthlyStats' => $monthlyStats,
            'topClients' => $topClients,
            'cashboxSummary' => $cashboxSummary
        ]);
    }
    
    public function clients()
    {
        $clientModel = new Client();
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        // Get all clients with their transaction summaries
        $sql = "
            SELECT 
                c.*,
                COUNT(t.id) as transaction_count,
                SUM(t.total_amount_rmb) as total_transactions_rmb,
                SUM(t.payment_rmb) as total_payments_rmb,
                SUM(t.balance_rmb) as total_balance_rmb,
                SUM(t.shipping_usd) as total_shipping_usd,
                SUM(t.payment_usd) as total_payments_usd,
                SUM(t.balance_usd) as total_balance_usd
            FROM clients c
            LEFT JOIN transactions t ON c.id = t.client_id 
                AND t.transaction_date BETWEEN ? AND ?
                AND t.status = 'approved'
            WHERE c.status = 'active'
            GROUP BY c.id
            ORDER BY total_transactions_rmb DESC
        ";
        
        $db = \App\Core\Database::getInstance();
        $stmt = $db->query($sql, [$startDate, $endDate]);
        $clientsReport = $stmt->fetchAll();
        
        $this->view('reports/clients', [
            'title' => __('reports.client_report'),
            'clientsReport' => $clientsReport,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }
    
    public function cashbox()
    {
        $cashboxModel = new Cashbox();
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        // Get movements grouped by category
        $sql = "
            SELECT 
                category,
                movement_type,
                COUNT(*) as count,
                SUM(amount_rmb) as total_rmb,
                SUM(amount_usd) as total_usd,
                SUM(amount_sdg) as total_sdg,
                SUM(amount_aed) as total_aed
            FROM cashbox_movements
            WHERE movement_date BETWEEN ? AND ?
            GROUP BY category, movement_type
            ORDER BY category, movement_type
        ";
        
        $db = \App\Core\Database::getInstance();
        $stmt = $db->query($sql, [$startDate, $endDate]);
        $categorySummary = $stmt->fetchAll();
        
        // Get daily balances
        $sql = "
            SELECT 
                movement_date,
                SUM(CASE WHEN movement_type = 'in' THEN amount_rmb ELSE -amount_rmb END) as daily_change_rmb,
                SUM(CASE WHEN movement_type = 'in' THEN amount_usd ELSE -amount_usd END) as daily_change_usd
            FROM cashbox_movements
            WHERE movement_date BETWEEN ? AND ?
            GROUP BY movement_date
            ORDER BY movement_date
        ";
        
        $stmt = $db->query($sql, [$startDate, $endDate]);
        $dailyBalances = $stmt->fetchAll();
        
        // Get current balance
        $currentBalance = $cashboxModel->getCurrentBalance();
        
        $this->view('reports/cashbox', [
            'title' => __('reports.cashbox_report'),
            'categorySummary' => $categorySummary,
            'dailyBalances' => $dailyBalances,
            'currentBalance' => $currentBalance,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }
}