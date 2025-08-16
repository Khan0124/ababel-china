<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class AdvancedReportController extends Controller
{
    public function analytics()
    {
        $this->checkAuth();
        $this->checkRole('accountant');
        
        $db = Database::getInstance();
        
        // Get analytics data
        $data = [
            'monthly_revenue' => $this->getMonthlyRevenue(),
            'top_clients' => $this->getTopClients(),
            'transaction_trends' => $this->getTransactionTrends(),
            'currency_breakdown' => $this->getCurrencyBreakdown()
        ];
        
        $this->view('reports/analytics', [
            'title' => __('analytics_report'),
            'data' => $data
        ]);
    }
    
    public function forecasting()
    {
        $this->checkAuth();
        $this->checkRole('accountant');
        
        $db = Database::getInstance();
        
        // Get historical data for forecasting
        $historicalData = $this->getHistoricalData();
        $forecast = $this->generateForecast($historicalData);
        
        $this->view('reports/forecasting', [
            'title' => __('financial_forecasting'),
            'historical' => $historicalData,
            'forecast' => $forecast
        ]);
    }
    
    public function charts()
    {
        $this->checkAuth();
        $this->checkRole('manager');
        
        // Prepare chart data
        $chartData = [
            'revenue_chart' => $this->getRevenueChartData(),
            'client_chart' => $this->getClientChartData(),
            'transaction_chart' => $this->getTransactionChartData(),
            'currency_chart' => $this->getCurrencyChartData()
        ];
        
        $this->view('reports/charts', [
            'title' => __('visual_reports'),
            'chartData' => json_encode($chartData)
        ]);
    }
    
    public function executiveDashboard()
    {
        $this->checkAuth();
        $this->checkRole('admin');
        
        $db = Database::getInstance();
        
        // Get executive summary data
        $summary = [
            'total_revenue' => $this->getTotalRevenue(),
            'outstanding_balance' => $this->getOutstandingBalance(),
            'monthly_growth' => $this->getMonthlyGrowth(),
            'top_performers' => $this->getTopPerformers(),
            'risk_analysis' => $this->getRiskAnalysis(),
            'kpis' => $this->getKPIs()
        ];
        
        $this->view('reports/executive-dashboard', [
            'title' => __('executive_dashboard'),
            'summary' => $summary
        ]);
    }
    
    private function getMonthlyRevenue()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                currency,
                SUM(amount) as revenue
            FROM transactions
            WHERE type = 'payment' 
                AND status = 'completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY month, currency
            ORDER BY month DESC
        ")->fetchAll();
    }
    
    private function getTopClients()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                c.id,
                c.name,
                COUNT(t.id) as transaction_count,
                SUM(t.amount) as total_amount,
                SUM(t.amount_aed) as total_amount_aed
            FROM clients c
            LEFT JOIN transactions t ON c.id = t.client_id
            WHERE t.status = 'completed'
            GROUP BY c.id
            ORDER BY total_amount_aed DESC
            LIMIT 10
        ")->fetchAll();
    }
    
    private function getTransactionTrends()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count,
                SUM(amount) as volume
            FROM transactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY date
            ORDER BY date
        ")->fetchAll();
    }
    
    private function getCurrencyBreakdown()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                currency,
                COUNT(*) as count,
                SUM(amount) as total
            FROM transactions
            WHERE status = 'completed'
            GROUP BY currency
        ")->fetchAll();
    }
    
    private function getHistoricalData()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(amount_aed) as revenue
            FROM transactions
            WHERE type = 'payment' 
                AND status = 'completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
            GROUP BY month
            ORDER BY month
        ")->fetchAll();
    }
    
    private function generateForecast($historicalData)
    {
        // Simple linear regression forecast
        $forecast = [];
        $n = count($historicalData);
        
        if ($n < 3) {
            return $forecast;
        }
        
        // Calculate trend
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        
        foreach ($historicalData as $i => $data) {
            $x = $i + 1;
            $y = floatval($data['revenue']);
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        // Generate 6-month forecast
        for ($i = 1; $i <= 6; $i++) {
            $x = $n + $i;
            $y = $slope * $x + $intercept;
            $month = date('Y-m', strtotime("+$i months"));
            $forecast[] = [
                'month' => $month,
                'predicted_revenue' => max(0, $y)
            ];
        }
        
        return $forecast;
    }
    
    private function getRevenueChartData()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as label,
                SUM(amount_aed) as value
            FROM transactions
            WHERE type = 'payment' 
                AND status = 'completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY label
            ORDER BY label
        ")->fetchAll();
    }
    
    private function getClientChartData()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                c.name as label,
                SUM(t.amount_aed) as value
            FROM clients c
            JOIN transactions t ON c.id = t.client_id
            WHERE t.status = 'completed'
            GROUP BY c.id
            ORDER BY value DESC
            LIMIT 10
        ")->fetchAll();
    }
    
    private function getTransactionChartData()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                type as label,
                COUNT(*) as value
            FROM transactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY type
        ")->fetchAll();
    }
    
    private function getCurrencyChartData()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                currency as label,
                SUM(amount) as value
            FROM transactions
            WHERE status = 'completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY currency
        ")->fetchAll();
    }
    
    private function getTotalRevenue()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                SUM(amount_aed) as total
            FROM transactions
            WHERE type = 'payment' 
                AND status = 'completed'
        ")->fetchColumn() ?: 0;
    }
    
    private function getOutstandingBalance()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                SUM(amount_aed) as total
            FROM transactions
            WHERE status = 'pending'
        ")->fetchColumn() ?: 0;
    }
    
    private function getMonthlyGrowth()
    {
        $db = Database::getInstance();
        
        $currentMonth = $db->query("
            SELECT SUM(amount_aed) as total
            FROM transactions
            WHERE type = 'payment' 
                AND status = 'completed'
                AND MONTH(created_at) = MONTH(CURRENT_DATE())
                AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ")->fetchColumn() ?: 0;
        
        $lastMonth = $db->query("
            SELECT SUM(amount_aed) as total
            FROM transactions
            WHERE type = 'payment' 
                AND status = 'completed'
                AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
                AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
        ")->fetchColumn() ?: 0;
        
        if ($lastMonth > 0) {
            return round((($currentMonth - $lastMonth) / $lastMonth) * 100, 2);
        }
        
        return 0;
    }
    
    private function getTopPerformers()
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT 
                c.name,
                COUNT(t.id) as transactions,
                SUM(t.amount_aed) as revenue
            FROM clients c
            JOIN transactions t ON c.id = t.client_id
            WHERE t.status = 'completed'
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY c.id
            ORDER BY revenue DESC
            LIMIT 5
        ")->fetchAll();
    }
    
    private function getRiskAnalysis()
    {
        $db = Database::getInstance();
        
        $overdue = $db->query("
            SELECT COUNT(*) as count, SUM(amount_aed) as total
            FROM transactions
            WHERE status = 'pending'
                AND due_date < CURRENT_DATE()
        ")->fetch();
        
        $highRisk = $db->query("
            SELECT COUNT(DISTINCT client_id) as count
            FROM transactions
            WHERE status = 'pending'
                AND due_date < DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
        ")->fetchColumn();
        
        return [
            'overdue_transactions' => $overdue['count'],
            'overdue_amount' => $overdue['total'] ?: 0,
            'high_risk_clients' => $highRisk
        ];
    }
    
    private function getKPIs()
    {
        $db = Database::getInstance();
        
        return [
            'average_transaction_value' => $db->query("
                SELECT AVG(amount_aed) 
                FROM transactions 
                WHERE status = 'completed'
            ")->fetchColumn() ?: 0,
            
            'payment_success_rate' => $this->calculatePaymentSuccessRate(),
            
            'average_payment_time' => $this->calculateAveragePaymentTime(),
            
            'client_retention_rate' => $this->calculateClientRetentionRate()
        ];
    }
    
    private function calculatePaymentSuccessRate()
    {
        $db = Database::getInstance();
        
        $total = $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
        $completed = $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'completed'")->fetchColumn();
        
        if ($total > 0) {
            return round(($completed / $total) * 100, 2);
        }
        
        return 0;
    }
    
    private function calculateAveragePaymentTime()
    {
        $db = Database::getInstance();
        
        $avgDays = $db->query("
            SELECT AVG(DATEDIFF(updated_at, created_at)) as days
            FROM transactions
            WHERE status = 'completed'
                AND updated_at IS NOT NULL
        ")->fetchColumn();
        
        return round($avgDays ?: 0, 1);
    }
    
    private function calculateClientRetentionRate()
    {
        $db = Database::getInstance();
        
        $activeLastMonth = $db->query("
            SELECT COUNT(DISTINCT client_id)
            FROM transactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
                AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ")->fetchColumn();
        
        $activeThisMonth = $db->query("
            SELECT COUNT(DISTINCT client_id)
            FROM transactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                AND client_id IN (
                    SELECT DISTINCT client_id
                    FROM transactions
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
                        AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)
                )
        ")->fetchColumn();
        
        if ($activeLastMonth > 0) {
            return round(($activeThisMonth / $activeLastMonth) * 100, 2);
        }
        
        return 0;
    }
    
    private function checkRole($requiredRole)
    {
        $userRole = $_SESSION['user_role'] ?? 'user';
        $roleHierarchy = [
            'admin' => 4,
            'accountant' => 3,
            'manager' => 2,
            'user' => 1
        ];
        
        $userLevel = $roleHierarchy[$userRole] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        
        if ($userLevel < $requiredLevel) {
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Access Denied']);
            exit;
        }
    }
}