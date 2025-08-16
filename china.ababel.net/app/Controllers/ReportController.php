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
        // النظام المحاسبي الاحترافي: حساب الرصيد = المدفوعات - المبيعات
        $sql = "
            SELECT 
                c.*,
                COUNT(t.id) as transaction_count,
                SUM(CASE WHEN tt.type IN ('expense', 'transfer') THEN t.total_amount_rmb ELSE 0 END) as total_transactions_rmb,
                SUM(CASE WHEN tt.type = 'income' THEN t.payment_rmb ELSE 0 END) as total_payments_rmb,
                (
                    SUM(CASE WHEN tt.type = 'income' THEN t.payment_rmb ELSE 0 END) - 
                    SUM(CASE WHEN tt.type IN ('expense', 'transfer') THEN t.total_amount_rmb ELSE 0 END)
                ) as total_balance_rmb,
                SUM(CASE WHEN tt.type IN ('expense', 'transfer') THEN t.shipping_usd ELSE 0 END) as total_shipping_usd,
                SUM(CASE WHEN tt.type = 'income' THEN t.payment_usd ELSE 0 END) as total_payments_usd,
                (
                    SUM(CASE WHEN tt.type = 'income' THEN t.payment_usd ELSE 0 END) - 
                    SUM(CASE WHEN tt.type IN ('expense', 'transfer') THEN t.shipping_usd ELSE 0 END)
                ) as total_balance_usd
            FROM clients c
            LEFT JOIN transactions t ON c.id = t.client_id 
                AND t.transaction_date BETWEEN ? AND ?
                AND t.status = 'approved'
            LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
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
    
    /**
     * Unpaid transactions report by due date
     */
    public function unpaidTransactions()
    {
        $db = \App\Core\Database::getInstance();
        $currentDate = date('Y-m-d');
        
        $sql = "
            SELECT 
                t.*,
                c.name as client_name,
                c.name_ar as client_name_ar,
                c.client_code,
                c.phone as client_phone,
                tt.name as transaction_type_name,
                DATEDIFF(?, t.transaction_date) as days_overdue,
                CASE 
                    WHEN DATEDIFF(?, t.transaction_date) <= 30 THEN 'current'
                    WHEN DATEDIFF(?, t.transaction_date) <= 60 THEN '30_days'
                    WHEN DATEDIFF(?, t.transaction_date) <= 90 THEN '60_days'
                    ELSE 'over_90_days'
                END as aging_category
            FROM transactions t
            JOIN clients c ON t.client_id = c.id
            JOIN transaction_types tt ON t.transaction_type_id = tt.id
            WHERE t.status = 'approved' 
            AND (t.balance_rmb > 0 OR t.balance_usd > 0 OR t.balance_sdg > 0 OR t.balance_aed > 0)
            ORDER BY c.name, t.transaction_date DESC
        ";
        
        $stmt = $db->query($sql, [$currentDate, $currentDate, $currentDate, $currentDate]);
        $unpaidTransactions = $stmt->fetchAll();
        
        // Group by aging category
        $agingSummary = [
            'current' => ['count' => 0, 'amount_rmb' => 0, 'amount_usd' => 0],
            '30_days' => ['count' => 0, 'amount_rmb' => 0, 'amount_usd' => 0],
            '60_days' => ['count' => 0, 'amount_rmb' => 0, 'amount_usd' => 0], 
            'over_90_days' => ['count' => 0, 'amount_rmb' => 0, 'amount_usd' => 0]
        ];
        
        foreach ($unpaidTransactions as $transaction) {
            $category = $transaction['aging_category'];
            $agingSummary[$category]['count']++;
            $agingSummary[$category]['amount_rmb'] += $transaction['balance_rmb'];
            $agingSummary[$category]['amount_usd'] += $transaction['balance_usd'];
        }
        
        $this->view('reports/unpaid-transactions', [
            'title' => __('reports.unpaid_transactions'),
            'unpaidTransactions' => $unpaidTransactions,
            'agingSummary' => $agingSummary
        ]);
    }
    
    /**
     * Payment analysis report (daily/monthly/yearly)
     */
    public function paymentAnalysis()
    {
        $period = $_GET['period'] ?? 'monthly';
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        
        $db = \App\Core\Database::getInstance();
        
        switch ($period) {
            case 'daily':
                $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
                $endDate = date('Y-m-t', strtotime($startDate));
                $groupBy = 'DATE(t.transaction_date)';
                $dateFormat = '%Y-%m-%d';
                break;
            case 'yearly':
                $startDate = $year . '-01-01';
                $endDate = $year . '-12-31';
                $groupBy = 'YEAR(t.transaction_date), MONTH(t.transaction_date)';
                $dateFormat = '%Y-%m';
                break;
            default: // monthly
                $startDate = $year . '-01-01';
                $endDate = $year . '-12-31';
                $groupBy = 'YEAR(t.transaction_date), MONTH(t.transaction_date)';
                $dateFormat = '%Y-%m';
                break;
        }
        
        $sql = "
            SELECT 
                DATE_FORMAT(t.transaction_date, ?) as period_label,
                COUNT(*) as payment_count,
                SUM(t.payment_rmb) as total_payments_rmb,
                SUM(t.payment_usd) as total_payments_usd,
                SUM(t.payment_sdg) as total_payments_sdg,
                AVG(t.payment_rmb) as avg_payment_rmb,
                COUNT(DISTINCT t.client_id) as unique_clients
            FROM transactions t
            WHERE t.transaction_date BETWEEN ? AND ?
            AND t.status = 'approved'
            AND (t.payment_rmb > 0 OR t.payment_usd > 0 OR t.payment_sdg > 0)
            GROUP BY $groupBy
            ORDER BY t.transaction_date
        ";
        
        $stmt = $db->query($sql, [$dateFormat, $startDate, $endDate]);
        $paymentAnalysis = $stmt->fetchAll();
        
        // Get payment methods analysis
        $sql = "
            SELECT 
                COALESCE(t.bank_name, 'Cash') as payment_method,
                COUNT(*) as count,
                SUM(t.payment_rmb) as total_rmb,
                SUM(t.payment_usd) as total_usd
            FROM transactions t
            WHERE t.transaction_date BETWEEN ? AND ?
            AND t.status = 'approved'
            AND (t.payment_rmb > 0 OR t.payment_usd > 0)
            GROUP BY payment_method
            ORDER BY total_rmb DESC
        ";
        
        $stmt = $db->query($sql, [$startDate, $endDate]);
        $paymentMethods = $stmt->fetchAll();
        
        $this->view('reports/payment-analysis', [
            'title' => __('reports.payment_analysis'),
            'paymentAnalysis' => $paymentAnalysis,
            'paymentMethods' => $paymentMethods,
            'period' => $period,
            'year' => $year,
            'month' => $month
        ]);
    }
    
    /**
     * Overdue shipments payment report
     */
    public function overdueShipments()
    {
        $db = \App\Core\Database::getInstance();
        $currentDate = date('Y-m-d');
        
        $sql = "
            SELECT 
                l.*,
                c.name as client_name,
                c.name_ar as client_name_ar,
                c.client_code,
                c.phone as client_phone,
                t.balance_rmb,
                t.balance_usd,
                t.transaction_no,
                DATEDIFF(?, l.shipping_date) as days_since_shipping
            FROM loadings l
            JOIN clients c ON l.client_code = c.client_code
            LEFT JOIN transactions t ON l.loading_no = t.loading_no 
                AND t.status = 'approved'
                AND (t.balance_rmb > 0 OR t.balance_usd > 0)
            WHERE l.status IN ('shipped', 'arrived', 'cleared')
            AND DATEDIFF(?, l.shipping_date) > 30
            AND (t.balance_rmb > 0 OR t.balance_usd > 0 OR t.id IS NULL)
            ORDER BY l.shipping_date DESC
        ";
        
        $stmt = $db->query($sql, [$currentDate, $currentDate]);
        $overdueShipments = $stmt->fetchAll();
        
        // Group by overdue period
        $overdueSummary = [
            '30_60_days' => ['count' => 0, 'amount_rmb' => 0, 'amount_usd' => 0],
            '60_90_days' => ['count' => 0, 'amount_rmb' => 0, 'amount_usd' => 0],
            'over_90_days' => ['count' => 0, 'amount_rmb' => 0, 'amount_usd' => 0]
        ];
        
        foreach ($overdueShipments as $shipment) {
            $days = $shipment['days_since_shipping'];
            $category = $days <= 60 ? '30_60_days' : ($days <= 90 ? '60_90_days' : 'over_90_days');
            
            $overdueSummary[$category]['count']++;
            $overdueSummary[$category]['amount_rmb'] += $shipment['balance_rmb'] ?? 0;
            $overdueSummary[$category]['amount_usd'] += $shipment['balance_usd'] ?? 0;
        }
        
        $this->view('reports/overdue-shipments', [
            'title' => __('reports.overdue_shipments'),
            'overdueShipments' => $overdueShipments,
            'overdueSummary' => $overdueSummary
        ]);
    }
    
    /**
     * Client balances summary by currencies
     */
    public function clientBalancesSummary()
    {
        $db = \App\Core\Database::getInstance();
        
        $sql = "
            SELECT 
                c.*,
                c.balance_rmb,
                c.balance_usd,
                c.balance_sdg,
                c.balance_aed,
                (c.balance_rmb + (c.balance_usd * 7.2) + (c.balance_sdg * 0.0017) + (c.balance_aed * 1.96)) as total_balance_rmb_equivalent,
                COUNT(t.id) as active_transactions,
                MAX(t.transaction_date) as last_transaction_date
            FROM clients c
            LEFT JOIN transactions t ON c.id = t.client_id 
                AND t.status = 'approved'
                AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            WHERE c.status = 'active'
            AND (c.balance_rmb != 0 OR c.balance_usd != 0 OR c.balance_sdg != 0 OR c.balance_aed != 0)
            GROUP BY c.id
            ORDER BY total_balance_rmb_equivalent DESC
        ";
        
        $stmt = $db->query($sql);
        $clientBalances = $stmt->fetchAll();
        
        // Calculate currency totals
        $currencyTotals = [
            'rmb' => 0,
            'usd' => 0,
            'sdg' => 0,
            'aed' => 0,
            'total_clients' => count($clientBalances)
        ];
        
        foreach ($clientBalances as $client) {
            $currencyTotals['rmb'] += $client['balance_rmb'];
            $currencyTotals['usd'] += $client['balance_usd'];
            $currencyTotals['sdg'] += $client['balance_sdg'];
            $currencyTotals['aed'] += $client['balance_aed'];
        }
        
        $this->view('reports/client-balances-summary', [
            'title' => __('reports.client_balances_summary'),
            'clientBalances' => $clientBalances,
            'currencyTotals' => $currencyTotals
        ]);
    }
}