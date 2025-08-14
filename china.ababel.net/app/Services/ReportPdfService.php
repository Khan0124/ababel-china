<?php
namespace App\Services;

use App\Core\Database;
use Mpdf\Mpdf;

class ReportPdfService
{
    private $db;
    private $companySettings;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->loadCompanySettings();
    }
    
    /**
     * Generate PDF for daily report
     */
    public function generateDailyReport($date, $transactions, $movements, $dailyTotals)
    {
        $html = $this->getDailyReportTemplate($date, $transactions, $movements, $dailyTotals);
        
        $filename = str_replace('{date}', $date, __('export.filename_daily'));
        return $this->generatePdf($html, $filename);
    }
    
    /**
     * Generate PDF for monthly report
     */
    public function generateMonthlyReport($month, $monthlyStats, $topClients, $cashboxSummary)
    {
        $html = $this->getMonthlyReportTemplate($month, $monthlyStats, $topClients, $cashboxSummary);
        
        $filename = str_replace('{month}', $month, __('export.filename_monthly'));
        return $this->generatePdf($html, $filename);
    }
    
    /**
     * Generate PDF for cashbox report
     */
    public function generateCashboxReport($startDate, $endDate, $categorySummary, $dailyBalances, $currentBalance)
    {
        $html = $this->getCashboxReportTemplate($startDate, $endDate, $categorySummary, $dailyBalances, $currentBalance);
        
        $filename = str_replace(['{start_date}', '{end_date}'], [$startDate, $endDate], __('export.filename_cashbox'));
        return $this->generatePdf($html, $filename);
    }
    
    /**
     * Generate PDF for client statement
     */
    public function generateClientStatement($client, $transactions, $startDate, $endDate)
    {
        $html = $this->getClientStatementTemplate($client, $transactions, $startDate, $endDate);
        
        $filename = str_replace(['{client_code}', '{date}'], [$client['client_code'], $startDate], __('export.filename_client_statement'));
        return $this->generatePdf($html, $filename);
    }
    
    /**
     * Core PDF generation method
     */
    private function generatePdf($html, $filename)
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 11,
            'default_font' => 'Arial',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 8,
            'margin_footer' => 8,
            'orientation' => 'P'
        ]);
        
        // Set header and footer
        $mpdf->SetHTMLHeader($this->getReportHeader());
        $mpdf->SetHTMLFooter($this->getReportFooter());
        
        // Add company logo watermark
        if (file_exists(BASE_PATH . '/public/assets/images/logo.png')) {
            $mpdf->SetWatermarkImage(BASE_PATH . '/public/assets/images/logo.png', 0.1, 'P');
            $mpdf->showWatermarkImage = true;
        }
        
        $mpdf->WriteHTML($html);
        
        // Create directory if not exists
        $dir = BASE_PATH . '/storage/exports/pdf/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $filepath = $dir . $filename . '_' . date('YmdHis') . '.pdf';
        $mpdf->Output($filepath, 'F');
        
        return [
            'success' => true,
            'filename' => basename($filepath),
            'filepath' => $filepath,
            'download_url' => '/exports/pdf/' . basename($filepath)
        ];
    }
    
    /**
     * Daily report template
     */
    private function getDailyReportTemplate($date, $transactions, $movements, $dailyTotals)
    {
        $isRTL = (lang() === 'ar');
        $direction = $isRTL ? 'rtl' : 'ltr';
        
        $html = '<!DOCTYPE html>
<html dir="' . $direction . '">
<head>
    <meta charset="UTF-8">
    <title>' . __('reports.daily_report') . ' - ' . $date . '</title>
    <style>' . $this->getReportCSS() . '</style>
</head>
<body>
    <div class="report-container">
        <div class="report-title">
            <h1>' . __('reports.daily_report') . '</h1>
            <p class="report-date">' . __('date') . ': ' . date('Y-m-d', strtotime($date)) . '</p>
        </div>
        
        <!-- Summary Section -->
        <div class="summary-grid">
            <div class="summary-card">
                <h3>' . __('transactions.title') . '</h3>
                <div class="summary-value">' . $dailyTotals['transactions_count'] . '</div>
            </div>
            <div class="summary-card">
                <h3>' . __('transactions.goods_amount') . ' (RMB)</h3>
                <div class="summary-value">¥' . number_format($dailyTotals['total_goods_rmb'], 2) . '</div>
            </div>
            <div class="summary-card">
                <h3>' . __('transactions.payment') . ' (RMB)</h3>
                <div class="summary-value">¥' . number_format($dailyTotals['total_payments_rmb'], 2) . '</div>
            </div>
            <div class="summary-card">
                <h3>' . __('cashbox.movements') . '</h3>
                <div class="summary-value">' . count($movements) . '</div>
            </div>
        </div>';
        
        // Transactions table
        if (!empty($transactions)) {
            $html .= '<div class="section">
                <h2>' . __('transactions.title') . '</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>' . __('transactions.transaction_no') . '</th>
                            <th>' . __('clients.name') . '</th>
                            <th>' . __('transactions.goods_amount') . '</th>
                            <th>' . __('transactions.commission') . '</th>
                            <th>' . __('total') . '</th>
                            <th>' . __('transactions.payment') . '</th>
                            <th>' . __('balance') . '</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($transactions as $transaction) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($transaction['transaction_no']) . '</td>
                    <td>' . htmlspecialchars($transaction['client_name'] ?? '-') . '</td>
                    <td class="amount">¥' . number_format($transaction['goods_amount_rmb'], 2) . '</td>
                    <td class="amount">¥' . number_format($transaction['commission_rmb'], 2) . '</td>
                    <td class="amount">¥' . number_format($transaction['total_amount_rmb'], 2) . '</td>
                    <td class="amount positive">¥' . number_format($transaction['payment_rmb'], 2) . '</td>
                    <td class="amount ' . ($transaction['balance_rmb'] > 0 ? 'negative' : 'positive') . '">¥' . number_format($transaction['balance_rmb'], 2) . '</td>
                </tr>';
            }
            
            $html .= '</tbody>
                    <tfoot>
                        <tr class="total-row">
                            <th colspan="2">' . __('total') . '</th>
                            <th class="amount">¥' . number_format($dailyTotals['total_goods_rmb'], 2) . '</th>
                            <th class="amount">¥' . number_format($dailyTotals['total_commission_rmb'], 2) . '</th>
                            <th class="amount">¥' . number_format($dailyTotals['total_goods_rmb'] + $dailyTotals['total_commission_rmb'], 2) . '</th>
                            <th class="amount">¥' . number_format($dailyTotals['total_payments_rmb'], 2) . '</th>
                            <th>-</th>
                        </tr>
                    </tfoot>
                </table>
            </div>';
        }
        
        // Cashbox movements
        if (!empty($movements)) {
            $html .= '<div class="section">
                <h2>' . __('cashbox.movements') . '</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>' . __('cashbox.movement_type') . '</th>
                            <th>' . __('cashbox.category') . '</th>
                            <th>' . __('transactions.description') . '</th>
                            <th>RMB</th>
                            <th>USD</th>
                            <th>' . __('cashbox.receipt_no') . '</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($movements as $movement) {
                $html .= '<tr>
                    <td><span class="badge ' . ($movement['movement_type'] == 'in' ? 'success' : 'danger') . '">' . __('cashbox.' . $movement['movement_type']) . '</span></td>
                    <td>' . __('cashbox.' . $movement['category']) . '</td>
                    <td>' . htmlspecialchars($movement['description'] ?? '-') . '</td>
                    <td class="amount ' . ($movement['movement_type'] == 'in' ? 'positive' : 'negative') . '">' . ($movement['movement_type'] == 'in' ? '+' : '-') . '¥' . number_format($movement['amount_rmb'], 2) . '</td>
                    <td class="amount ' . ($movement['movement_type'] == 'in' ? 'positive' : 'negative') . '">' . ($movement['amount_usd'] > 0 ? ($movement['movement_type'] == 'in' ? '+' : '-') . '$' . number_format($movement['amount_usd'], 2) : '') . '</td>
                    <td>' . htmlspecialchars($movement['receipt_no'] ?? '-') . '</td>
                </tr>';
            }
            
            $html .= '</tbody>
                </table>
            </div>';
        }
        
        $html .= '
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Monthly report template
     */
    private function getMonthlyReportTemplate($month, $monthlyStats, $topClients, $cashboxSummary)
    {
        $isRTL = (lang() === 'ar');
        $direction = $isRTL ? 'rtl' : 'ltr';
        
        $html = '<!DOCTYPE html>
<html dir="' . $direction . '">
<head>
    <meta charset="UTF-8">
    <title>' . __('reports.monthly_report') . ' - ' . date('F Y', strtotime($month . '-01')) . '</title>
    <style>' . $this->getReportCSS() . '</style>
</head>
<body>
    <div class="report-container">
        <div class="report-title">
            <h1>' . __('reports.monthly_report') . '</h1>
            <p class="report-date">' . date('F Y', strtotime($month . '-01')) . '</p>
        </div>
        
        <!-- Summary Section -->
        <div class="summary-grid">
            <div class="summary-card">
                <h3>' . __('transactions.title') . '</h3>
                <div class="summary-value">' . $monthlyStats['total_transactions'] . '</div>
            </div>
            <div class="summary-card">
                <h3>' . __('clients.active') . '</h3>
                <div class="summary-value">' . $monthlyStats['active_clients'] . '</div>
            </div>
            <div class="summary-card">
                <h3>' . __('total') . ' (RMB)</h3>
                <div class="summary-value">¥' . number_format($monthlyStats['total_amount_rmb'], 0) . '</div>
            </div>
            <div class="summary-card">
                <h3>' . __('transactions.payment') . ' (RMB)</h3>
                <div class="summary-value">¥' . number_format($monthlyStats['total_payments_rmb'], 0) . '</div>
            </div>
        </div>
        
        <!-- Financial Summary -->
        <div class="section">
            <h2>' . __('reports.financial_summary') . '</h2>
            <div class="financial-grid">
                <div class="financial-column">
                    <table class="summary-table">
                        <tr>
                            <td>' . __('transactions.goods_amount') . '</td>
                            <td class="amount">¥' . number_format($monthlyStats['total_goods_rmb'], 2) . '</td>
                        </tr>
                        <tr>
                            <td>' . __('transactions.commission') . '</td>
                            <td class="amount">¥' . number_format($monthlyStats['total_commission_rmb'], 2) . '</td>
                        </tr>
                        <tr class="total-row">
                            <th>' . __('total') . ' RMB</th>
                            <th class="amount">¥' . number_format($monthlyStats['total_amount_rmb'], 2) . '</th>
                        </tr>
                        <tr>
                            <td>' . __('transactions.payment') . ' RMB</td>
                            <td class="amount positive">¥' . number_format($monthlyStats['total_payments_rmb'], 2) . '</td>
                        </tr>
                    </table>
                </div>
                <div class="financial-column">
                    <table class="summary-table">
                        <tr>
                            <td>' . __('transactions.shipping') . ' USD</td>
                            <td class="amount">$' . number_format($monthlyStats['total_shipping_usd'], 2) . '</td>
                        </tr>
                        <tr>
                            <td>' . __('transactions.payment') . ' USD</td>
                            <td class="amount positive">$' . number_format($monthlyStats['total_payments_usd'], 2) . '</td>
                        </tr>
                        <tr class="total-row">
                            <th>' . __('cashbox.movements') . '</th>
                            <th class="amount">' . $cashboxSummary['movements_count'] . '</th>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Top Clients -->
        <div class="section">
            <h2>' . __('reports.top_clients') . '</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>' . __('clients.client_code') . '</th>
                        <th>' . __('clients.name') . '</th>
                        <th>' . __('transactions.count') . '</th>
                        <th>' . __('total') . ' (RMB)</th>
                        <th>' . __('transactions.payment') . ' (RMB)</th>
                        <th>' . __('percentage') . '</th>
                    </tr>
                </thead>
                <tbody>';
        
        $rank = 1;
        $totalRevenue = $monthlyStats['total_amount_rmb'];
        foreach ($topClients as $client) {
            $percentage = $totalRevenue > 0 ? ($client['total_amount_rmb'] / $totalRevenue * 100) : 0;
            $html .= '<tr>
                <td>' . $rank++ . '</td>
                <td>' . htmlspecialchars($client['client_code']) . '</td>
                <td>' . htmlspecialchars((lang() == 'ar') ? ($client['name_ar'] ?? $client['name']) : $client['name']) . '</td>
                <td>' . $client['transaction_count'] . '</td>
                <td class="amount">¥' . number_format($client['total_amount_rmb'], 2) . '</td>
                <td class="amount positive">¥' . number_format($client['total_payments_rmb'], 2) . '</td>
                <td>' . number_format($percentage, 1) . '%</td>
            </tr>';
        }
        
        $html .= '</tbody>
            </table>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Client statement template
     */
    private function getClientStatementTemplate($client, $transactions, $startDate, $endDate)
    {
        $isRTL = (lang() === 'ar');
        $direction = $isRTL ? 'rtl' : 'ltr';
        
        // Calculate totals
        $totalTransactions = array_sum(array_column($transactions, 'total_amount_rmb'));
        $totalPayments = array_sum(array_column($transactions, 'payment_rmb'));
        $totalBalance = array_sum(array_column($transactions, 'balance_rmb'));
        
        $html = '<!DOCTYPE html>
<html dir="' . $direction . '">
<head>
    <meta charset="UTF-8">
    <title>' . __('clients.client_statement') . ' - ' . $client['client_code'] . '</title>
    <style>' . $this->getReportCSS() . '</style>
</head>
<body>
    <div class="report-container">
        <div class="report-title">
            <h1>' . __('clients.client_statement') . '</h1>
            <p class="report-date">' . __('reports.date_range') . ': ' . $startDate . ' - ' . $endDate . '</p>
        </div>
        
        <!-- Client Info -->
        <div class="client-info">
            <h2>' . htmlspecialchars((lang() == 'ar') ? ($client['name_ar'] ?? $client['name']) : $client['name']) . '</h2>
            <p><strong>' . __('clients.client_code') . ':</strong> ' . htmlspecialchars($client['client_code']) . '</p>
            <p><strong>' . __('clients.phone') . ':</strong> ' . htmlspecialchars($client['phone']) . '</p>
            ' . (isset($client['email']) && $client['email'] ? '<p><strong>' . __('clients.email') . ':</strong> ' . htmlspecialchars($client['email']) . '</p>' : '') . '
        </div>
        
        <!-- Summary -->
        <div class="summary-grid">
            <div class="summary-card">
                <h3>' . __('transactions.total_amount') . '</h3>
                <div class="summary-value">¥' . number_format($totalTransactions, 2) . '</div>
            </div>
            <div class="summary-card positive">
                <h3>' . __('transactions.payment') . '</h3>
                <div class="summary-value">¥' . number_format($totalPayments, 2) . '</div>
            </div>
            <div class="summary-card ' . ($totalBalance > 0 ? 'negative' : 'positive') . '">
                <h3>' . __('balance') . '</h3>
                <div class="summary-value">¥' . number_format($totalBalance, 2) . '</div>
            </div>
            <div class="summary-card">
                <h3>' . __('clients.transaction_count') . '</h3>
                <div class="summary-value">' . count($transactions) . '</div>
            </div>
        </div>';
        
        if (!empty($transactions)) {
            $html .= '
        <!-- Transactions -->
        <div class="section">
            <h2>' . __('transactions.title') . '</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>' . __('date') . '</th>
                        <th>' . __('transactions.transaction_no') . '</th>
                        <th>' . __('transactions.type') . '</th>
                        <th>' . __('transactions.goods_amount') . '</th>
                        <th>' . __('transactions.commission') . '</th>
                        <th>' . __('total') . '</th>
                        <th>' . __('transactions.payment') . '</th>
                        <th>' . __('balance') . '</th>
                        <th>' . __('status') . '</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($transactions as $transaction) {
                $html .= '<tr>
                    <td>' . date('Y-m-d', strtotime($transaction['transaction_date'])) . '</td>
                    <td>' . htmlspecialchars($transaction['transaction_no']) . '</td>
                    <td>' . htmlspecialchars($transaction['transaction_type_name'] ?? '-') . '</td>
                    <td class="amount">¥' . number_format($transaction['goods_amount_rmb'], 2) . '</td>
                    <td class="amount">¥' . number_format($transaction['commission_rmb'], 2) . '</td>
                    <td class="amount">¥' . number_format($transaction['total_amount_rmb'], 2) . '</td>
                    <td class="amount positive">¥' . number_format($transaction['payment_rmb'], 2) . '</td>
                    <td class="amount ' . ($transaction['balance_rmb'] > 0 ? 'negative' : 'positive') . '">¥' . number_format($transaction['balance_rmb'], 2) . '</td>
                    <td><span class="badge ' . $this->getStatusBadgeClass($transaction['status']) . '">' . __('transactions.' . $transaction['status']) . '</span></td>
                </tr>';
            }
            
            $html .= '</tbody>
                <tfoot>
                    <tr class="total-row">
                        <th colspan="3">' . __('total') . '</th>
                        <th class="amount">¥' . number_format(array_sum(array_column($transactions, 'goods_amount_rmb')), 2) . '</th>
                        <th class="amount">¥' . number_format(array_sum(array_column($transactions, 'commission_rmb')), 2) . '</th>
                        <th class="amount">¥' . number_format($totalTransactions, 2) . '</th>
                        <th class="amount">¥' . number_format($totalPayments, 2) . '</th>
                        <th class="amount ' . ($totalBalance > 0 ? 'negative' : 'positive') . '">¥' . number_format($totalBalance, 2) . '</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>';
        }
        
        $html .= '
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Cashbox report template  
     */
    private function getCashboxReportTemplate($startDate, $endDate, $categorySummary, $dailyBalances, $currentBalance)
    {
        $isRTL = (lang() === 'ar');
        $direction = $isRTL ? 'rtl' : 'ltr';
        
        $html = '<!DOCTYPE html>
<html dir="' . $direction . '">
<head>
    <meta charset="UTF-8">
    <title>' . __('reports.cashbox_report') . '</title>
    <style>' . $this->getReportCSS() . '</style>
</head>
<body>
    <div class="report-container">
        <div class="report-title">
            <h1>' . __('reports.cashbox_report') . '</h1>
            <p class="report-date">' . __('reports.date_range') . ': ' . $startDate . ' - ' . $endDate . '</p>
        </div>
        
        <!-- Current Balance -->
        <div class="section">
            <h2>' . __('cashbox.current_balance') . '</h2>
            <div class="balance-grid">
                <div class="balance-card">
                    <h3>RMB</h3>
                    <div class="balance-value ' . (($currentBalance['balance_rmb'] ?? 0) >= 0 ? 'positive' : 'negative') . '">¥' . number_format($currentBalance['balance_rmb'] ?? 0, 2) . '</div>
                </div>
                <div class="balance-card">
                    <h3>USD</h3>
                    <div class="balance-value ' . (($currentBalance['balance_usd'] ?? 0) >= 0 ? 'positive' : 'negative') . '">$' . number_format($currentBalance['balance_usd'] ?? 0, 2) . '</div>
                </div>
                <div class="balance-card">
                    <h3>SDG</h3>
                    <div class="balance-value ' . (($currentBalance['balance_sdg'] ?? 0) >= 0 ? 'positive' : 'negative') . '">' . number_format($currentBalance['balance_sdg'] ?? 0, 2) . '</div>
                </div>
                <div class="balance-card">
                    <h3>AED</h3>
                    <div class="balance-value ' . (($currentBalance['balance_aed'] ?? 0) >= 0 ? 'positive' : 'negative') . '">' . number_format($currentBalance['balance_aed'] ?? 0, 2) . '</div>
                </div>
            </div>
        </div>';
        
        // Category summary
        if (!empty($categorySummary)) {
            $totalIn = ['rmb' => 0, 'usd' => 0, 'sdg' => 0, 'aed' => 0];
            $totalOut = ['rmb' => 0, 'usd' => 0, 'sdg' => 0, 'aed' => 0];
            
            foreach ($categorySummary as $row) {
                if ($row['movement_type'] == 'in') {
                    $totalIn['rmb'] += $row['total_rmb'];
                    $totalIn['usd'] += $row['total_usd'];
                    $totalIn['sdg'] += $row['total_sdg'];
                    $totalIn['aed'] += $row['total_aed'];
                } else {
                    $totalOut['rmb'] += $row['total_rmb'];
                    $totalOut['usd'] += $row['total_usd'];
                    $totalOut['sdg'] += $row['total_sdg'];
                    $totalOut['aed'] += $row['total_aed'];
                }
            }
            
            $html .= '
        <div class="section">
            <h2>' . __('reports.movement_by_category') . '</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>' . __('cashbox.category') . '</th>
                        <th>' . __('cashbox.movement_type') . '</th>
                        <th>' . __('reports.transactions') . '</th>
                        <th>RMB</th>
                        <th>USD</th>
                        <th>SDG</th>
                        <th>AED</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($categorySummary as $row) {
                $html .= '<tr>
                    <td>' . __('cashbox.' . $row['category']) . '</td>
                    <td><span class="badge ' . ($row['movement_type'] == 'in' ? 'success' : 'danger') . '">' . __('cashbox.' . $row['movement_type']) . '</span></td>
                    <td>' . $row['count'] . '</td>
                    <td class="amount ' . ($row['movement_type'] == 'in' ? 'positive' : 'negative') . '">' . ($row['movement_type'] == 'in' ? '+' : '-') . '¥' . number_format(abs($row['total_rmb']), 2) . '</td>
                    <td class="amount ' . ($row['movement_type'] == 'in' ? 'positive' : 'negative') . '">' . ($row['total_usd'] != 0 ? ($row['movement_type'] == 'in' ? '+' : '-') . '$' . number_format(abs($row['total_usd']), 2) : '') . '</td>
                    <td class="amount ' . ($row['movement_type'] == 'in' ? 'positive' : 'negative') . '">' . ($row['total_sdg'] != 0 ? ($row['movement_type'] == 'in' ? '+' : '-') . number_format(abs($row['total_sdg']), 2) : '') . '</td>
                    <td class="amount ' . ($row['movement_type'] == 'in' ? 'positive' : 'negative') . '">' . ($row['total_aed'] != 0 ? ($row['movement_type'] == 'in' ? '+' : '-') . number_format(abs($row['total_aed']), 2) : '') . '</td>
                </tr>';
            }
            
            $html .= '</tbody>
                <tfoot>
                    <tr class="total-in">
                        <th colspan="3">' . __('reports.total_in') . '</th>
                        <th class="amount">+¥' . number_format($totalIn['rmb'], 2) . '</th>
                        <th class="amount">+$' . number_format($totalIn['usd'], 2) . '</th>
                        <th class="amount">+' . number_format($totalIn['sdg'], 2) . '</th>
                        <th class="amount">+' . number_format($totalIn['aed'], 2) . '</th>
                    </tr>
                    <tr class="total-out">
                        <th colspan="3">' . __('reports.total_out') . '</th>
                        <th class="amount">-¥' . number_format($totalOut['rmb'], 2) . '</th>
                        <th class="amount">-$' . number_format($totalOut['usd'], 2) . '</th>
                        <th class="amount">-' . number_format($totalOut['sdg'], 2) . '</th>
                        <th class="amount">-' . number_format($totalOut['aed'], 2) . '</th>
                    </tr>
                    <tr class="net-change">
                        <th colspan="3">' . __('reports.net_change') . '</th>
                        <th class="amount">¥' . number_format($totalIn['rmb'] - $totalOut['rmb'], 2) . '</th>
                        <th class="amount">$' . number_format($totalIn['usd'] - $totalOut['usd'], 2) . '</th>
                        <th class="amount">' . number_format($totalIn['sdg'] - $totalOut['sdg'], 2) . '</th>
                        <th class="amount">' . number_format($totalIn['aed'] - $totalOut['aed'], 2) . '</th>
                    </tr>
                </tfoot>
            </table>
        </div>';
        }
        
        $html .= '
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Get unified CSS for all reports
     */
    private function getReportCSS()
    {
        $isRTL = (lang() === 'ar');
        $fontFamily = $isRTL ? 'Arial, "Times New Roman", serif' : 'Arial, sans-serif';
        
        return '
        @page {
            margin: 15mm;
        }
        
        body {
            font-family: ' . $fontFamily . ';
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            direction: ' . ($isRTL ? 'rtl' : 'ltr') . ';
        }
        
        .report-container {
            max-width: 100%;
            margin: 0 auto;
        }
        
        .report-title {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 15px;
        }
        
        .report-title h1 {
            color: #3498db;
            font-size: 20px;
            margin: 0 0 5px 0;
        }
        
        .report-date {
            color: #666;
            margin: 0;
            font-size: 12px;
        }
        
        .section {
            margin: 20px 0;
            page-break-inside: avoid;
        }
        
        .section h2 {
            color: #2c3e50;
            font-size: 16px;
            margin: 0 0 15px 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        /* Summary Grid */
        .summary-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
            border-collapse: separate;
            border-spacing: 10px;
        }
        
        .summary-card {
            display: table-cell;
            width: 23%;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            vertical-align: top;
        }
        
        .summary-card.positive {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        
        .summary-card.negative {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .summary-card h3 {
            font-size: 12px;
            margin: 0 0 8px 0;
            color: #666;
        }
        
        .summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        /* Balance Grid */
        .balance-grid {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px;
        }
        
        .balance-card {
            display: table-cell;
            width: 23%;
            text-align: center;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
        }
        
        .balance-card h3 {
            font-size: 14px;
            margin: 0 0 10px 0;
            color: #666;
        }
        
        .balance-value {
            font-size: 20px;
            font-weight: bold;
        }
        
        .balance-value.positive {
            color: #28a745;
        }
        
        .balance-value.negative {
            color: #dc3545;
        }
        
        /* Financial Grid */
        .financial-grid {
            display: table;
            width: 100%;
        }
        
        .financial-column {
            display: table-cell;
            width: 48%;
            vertical-align: top;
            padding: 0 1%;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .data-table th {
            background: #3498db;
            color: white;
            padding: 8px 6px;
            text-align: ' . ($isRTL ? 'right' : 'left') . ';
            font-size: 11px;
            font-weight: bold;
            border: 1px solid #2980b9;
        }
        
        .data-table td {
            padding: 6px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .data-table tfoot th {
            background: #34495e;
            color: white;
            font-weight: bold;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .summary-table td,
        .summary-table th {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            text-align: ' . ($isRTL ? 'right' : 'left') . ';
        }
        
        .summary-table .total-row {
            background: #f8f9fa;
            font-weight: bold;
            border-top: 2px solid #3498db;
        }
        
        /* Amount formatting */
        .amount {
            text-align: ' . ($isRTL ? 'left' : 'right') . ' !important;
            font-family: "Courier New", monospace;
            white-space: nowrap;
        }
        
        .positive {
            color: #28a745 !important;
        }
        
        .negative {
            color: #dc3545 !important;
        }
        
        /* Badges */
        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .badge.danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .badge.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        /* Client Info */
        .client-info {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .client-info h2 {
            color: #2c3e50;
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        
        .client-info p {
            margin: 5px 0;
            font-size: 11px;
        }
        
        /* Total rows */
        .total-row {
            background: #e9ecef !important;
            font-weight: bold;
        }
        
        .total-in {
            background: #d4edda !important;
            color: #155724;
        }
        
        .total-out {
            background: #f8d7da !important;
            color: #721c24;
        }
        
        .net-change {
            background: #cce5ff !important;
            color: #004085;
            font-weight: bold;
        }
        
        /* Page breaks */
        .page-break {
            page-break-before: always;
        }
        
        .no-break {
            page-break-inside: avoid;
        }
        ';
    }
    
    /**
     * Get report header
     */
    private function getReportHeader()
    {
        return '<table width="100%">
            <tr>
                <td width="50%">
                    <strong>' . htmlspecialchars($this->companySettings['name']) . '</strong><br>
                    ' . htmlspecialchars($this->companySettings['address']) . '
                </td>
                <td width="50%" style="text-align: right;">
                    ' . __('date') . ': {DATE j-m-Y}<br>
                    ' . __('reports.page') . ': {PAGENO}/{nbpg}
                </td>
            </tr>
        </table>
        <hr style="border: 1px solid #3498db; margin: 10px 0;">';
    }
    
    /**
     * Get report footer
     */
    private function getReportFooter()
    {
        return '<hr style="border: 1px solid #ddd; margin: 10px 0;">
        <table width="100%">
            <tr>
                <td width="50%" style="font-size: 9px; color: #666;">
                    ' . htmlspecialchars($this->companySettings['name']) . ' - ' . __('reports.confidential') . '
                </td>
                <td width="50%" style="text-align: right; font-size: 9px; color: #666;">
                    ' . __('reports.generated') . ': {DATE j-m-Y H:i:s} | ' . __('nav.profile') . ': ' . htmlspecialchars($_SESSION['user_name'] ?? '-') . '
                </td>
            </tr>
        </table>';
    }
    
    /**
     * Load company settings
     */
    private function loadCompanySettings()
    {
        $sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        $this->companySettings = [
            'name' => 'China Office Accounting System',
            'address' => '',
            'phone' => '',
            'email' => '',
            'bank_details' => ''
        ];
        
        foreach ($results as $row) {
            $key = str_replace('company_', '', $row['setting_key']);
            $this->companySettings[$key] = $row['setting_value'];
        }
    }
    
    /**
     * Get status badge class
     */
    private function getStatusBadgeClass($status)
    {
        switch ($status) {
            case 'approved':
                return 'success';
            case 'pending':
                return 'warning';
            case 'cancelled':
                return 'danger';
            default:
                return 'secondary';
        }
    }
}