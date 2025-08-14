<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Services\ReportPdfService;
use App\Services\ExcelExportService;
use App\Services\InvoiceService;
use App\Models\Transaction;
use App\Models\Client;
use App\Models\Cashbox;

class ExportController extends Controller
{
    private $reportPdfService;
    private $excelExportService;
    private $invoiceService;
    
    public function __construct()
    {
        parent::__construct();
        $this->reportPdfService = new ReportPdfService();
        $this->excelExportService = new ExcelExportService();
        $this->invoiceService = new InvoiceService();
    }
    
    /**
     * Export daily report
     */
    public function dailyReport()
    {
        try {
            $date = $_GET['date'] ?? date('Y-m-d');
            $format = $_GET['format'] ?? 'pdf';
            $language = $_GET['language'] ?? lang();
            
            // Validate format
            if (!in_array($format, ['pdf', 'excel'])) {
                throw new \Exception(__('messages.invalid_format'));
            }
            
            // Get report data
            $data = $this->getDailyReportData($date);
            
            // Generate export
            if ($format === 'pdf') {
                $result = $this->reportPdfService->generateDailyReport(
                    $date, 
                    $data['transactions'], 
                    $data['movements'], 
                    $data['dailyTotals']
                );
            } else {
                $result = $this->excelExportService->exportDailyReport(
                    $date, 
                    $data['transactions'], 
                    $data['movements'], 
                    $data['dailyTotals']
                );
            }
            
            $this->downloadFile($result);
            
        } catch (\Exception $e) {
            $this->handleExportError($e);
        }
    }
    
    /**
     * Export monthly report
     */
    public function monthlyReport()
    {
        try {
            $month = $_GET['month'] ?? date('Y-m');
            $format = $_GET['format'] ?? 'pdf';
            $language = $_GET['language'] ?? lang();
            
            // Validate format
            if (!in_array($format, ['pdf', 'excel'])) {
                throw new \Exception(__('messages.invalid_format'));
            }
            
            // Get report data
            $data = $this->getMonthlyReportData($month);
            
            // Generate export
            if ($format === 'pdf') {
                $result = $this->reportPdfService->generateMonthlyReport(
                    $month,
                    $data['monthlyStats'],
                    $data['topClients'],
                    $data['cashboxSummary']
                );
            } else {
                $result = $this->excelExportService->exportMonthlyReport(
                    $month,
                    $data['monthlyStats'],
                    $data['topClients'],
                    $data['cashboxSummary']
                );
            }
            
            $this->downloadFile($result);
            
        } catch (\Exception $e) {
            $this->handleExportError($e);
        }
    }
    
    /**
     * Export cashbox report
     */
    public function cashboxReport()
    {
        try {
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $format = $_GET['format'] ?? 'pdf';
            
            // Validate format
            if (!in_array($format, ['pdf', 'excel'])) {
                throw new \Exception(__('messages.invalid_format'));
            }
            
            // Get report data
            $data = $this->getCashboxReportData($startDate, $endDate);
            
            // Generate export
            if ($format === 'pdf') {
                $result = $this->reportPdfService->generateCashboxReport(
                    $startDate,
                    $endDate,
                    $data['categorySummary'],
                    $data['dailyBalances'],
                    $data['currentBalance']
                );
            } else {
                $result = $this->excelExportService->exportCashboxReport(
                    $startDate,
                    $endDate,
                    $data['categorySummary'],
                    $data['dailyBalances'],
                    $data['currentBalance']
                );
            }
            
            $this->downloadFile($result);
            
        } catch (\Exception $e) {
            $this->handleExportError($e);
        }
    }
    
    /**
     * Export client statement
     */
    public function clientStatement()
    {
        try {
            $clientId = $_GET['client_id'] ?? null;
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $format = $_GET['format'] ?? 'pdf';
            
            if (!$clientId) {
                throw new \Exception(__('messages.client_required'));
            }
            
            // Validate format
            if (!in_array($format, ['pdf', 'excel'])) {
                throw new \Exception(__('messages.invalid_format'));
            }
            
            // Get client statement data
            $data = $this->getClientStatementData($clientId, $startDate, $endDate);
            
            // Generate export
            if ($format === 'pdf') {
                $result = $this->reportPdfService->generateClientStatement(
                    $data['client'],
                    $data['transactions'],
                    $startDate,
                    $endDate
                );
            } else {
                $result = $this->excelExportService->exportClientStatement(
                    $data['client'],
                    $data['transactions'],
                    $startDate,
                    $endDate
                );
            }
            
            $this->downloadFile($result);
            
        } catch (\Exception $e) {
            $this->handleExportError($e);
        }
    }
    
    /**
     * Export transactions list
     */
    public function transactionsList()
    {
        try {
            $format = $_GET['format'] ?? 'excel';
            $filters = $this->getTransactionFilters();
            
            // Validate format
            if (!in_array($format, ['pdf', 'excel'])) {
                throw new \Exception(__('messages.invalid_format'));
            }
            
            // Get transactions data
            $transactions = $this->getTransactionsData($filters);
            
            // Generate export
            if ($format === 'excel') {
                $result = $this->excelExportService->exportTransactionsList($transactions, $filters);
            } else {
                // For PDF, we can create a simple list or redirect to a report format
                $result = $this->generateTransactionsPdf($transactions, $filters);
            }
            
            $this->downloadFile($result);
            
        } catch (\Exception $e) {
            $this->handleExportError($e);
        }
    }
    
    /**
     * Export clients list
     */
    public function clientsList()
    {
        try {
            $format = $_GET['format'] ?? 'excel';
            
            // Validate format
            if (!in_array($format, ['pdf', 'excel'])) {
                throw new \Exception(__('messages.invalid_format'));
            }
            
            // Get clients data
            $clients = $this->getClientsData();
            
            // Generate export
            $result = $this->excelExportService->exportClientsList($clients);
            
            $this->downloadFile($result);
            
        } catch (\Exception $e) {
            $this->handleExportError($e);
        }
    }
    
    /**
     * Generate invoice
     */
    public function generateInvoice()
    {
        try {
            $transactionId = $_GET['transaction_id'] ?? null;
            $format = $_GET['format'] ?? 'pdf';
            
            if (!$transactionId) {
                throw new \Exception(__('messages.transaction_required'));
            }
            
            // Get additional options
            $options = [
                'language' => $_GET['language'] ?? lang(),
                'template' => $_GET['template'] ?? 'default',
                'watermark' => $_GET['watermark'] ?? null,
                'notes' => $_GET['notes'] ?? '',
                'payment_terms' => intval($_GET['payment_terms'] ?? 30)
            ];
            
            // Generate invoice
            $result = $this->invoiceService->generateInvoice($transactionId, $format, $options);
            
            if ($format === 'html') {
                // For HTML, return the content directly
                header('Content-Type: text/html; charset=utf-8');
                echo $result;
                exit;
            }
            
            $this->downloadFile($result);
            
        } catch (\Exception $e) {
            $this->handleExportError($e);
        }
    }
    
    /**
     * Generate payment receipt
     */
    public function generateReceipt()
    {
        try {
            $paymentId = $_GET['payment_id'] ?? null;
            $format = $_GET['format'] ?? 'pdf';
            
            if (!$paymentId) {
                throw new \Exception(__('messages.payment_required'));
            }
            
            $options = [
                'language' => $_GET['language'] ?? lang()
            ];
            
            // Generate receipt
            $result = $this->invoiceService->generateReceipt($paymentId, $format, $options);
            
            if ($format === 'html') {
                header('Content-Type: text/html; charset=utf-8');
                echo $result;
                exit;
            }
            
            $this->downloadFile($result);
            
        } catch (\Exception $e) {
            $this->handleExportError($e);
        }
    }
    
    /**
     * Download generated file
     */
    private function downloadFile($result)
    {
        if (!$result['success']) {
            throw new \Exception($result['error'] ?? __('messages.export_failed'));
        }
        
        $filepath = $result['filepath'];
        $filename = $result['filename'];
        
        if (!file_exists($filepath)) {
            throw new \Exception(__('messages.file_not_found'));
        }
        
        // Determine content type
        $contentType = $result['type'] ?? $this->getContentType($filepath);
        
        // Set headers for download
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Clear any output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output file
        readfile($filepath);
        
        // Optionally clean up file after download
        if (isset($_GET['cleanup']) && $_GET['cleanup'] == '1') {
            unlink($filepath);
        }
        
        exit;
    }
    
    /**
     * Handle export errors
     */
    private function handleExportError(\Exception $e)
    {
        error_log('Export error: ' . $e->getMessage());
        
        // Return JSON error for AJAX requests
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
        
        // Redirect with error message for regular requests
        $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard';
        header('Location: ' . $referer . '?error=' . urlencode($e->getMessage()));
        exit;
    }
    
    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get content type based on file extension
     */
    private function getContentType($filepath)
    {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv',
            'html' => 'text/html',
            'txt' => 'text/plain'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Get daily report data
     */
    private function getDailyReportData($date)
    {
        $transactionModel = new Transaction();
        $cashboxModel = new Cashbox();
        
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
            'total_goods_rmb' => array_sum(array_column($transactions, 'goods_amount_rmb')),
            'total_commission_rmb' => array_sum(array_column($transactions, 'commission_rmb')),
            'total_payments_rmb' => array_sum(array_column($transactions, 'payment_rmb')),
            'total_payments_usd' => array_sum(array_column($transactions, 'payment_usd')),
            'cashbox_in_rmb' => 0,
            'cashbox_out_rmb' => 0,
            'cashbox_in_usd' => 0,
            'cashbox_out_usd' => 0
        ];
        
        foreach ($movements as $movement) {
            if ($movement['movement_type'] === 'in') {
                $dailyTotals['cashbox_in_rmb'] += $movement['amount_rmb'];
                $dailyTotals['cashbox_in_usd'] += $movement['amount_usd'];
            } else {
                $dailyTotals['cashbox_out_rmb'] += $movement['amount_rmb'];
                $dailyTotals['cashbox_out_usd'] += $movement['amount_usd'];
            }
        }
        
        return [
            'transactions' => $transactions,
            'movements' => $movements,
            'dailyTotals' => $dailyTotals
        ];
    }
    
    /**
     * Get monthly report data
     */
    private function getMonthlyReportData($month)
    {
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $transactionModel = new Transaction();
        $cashboxModel = new Cashbox();
        $clientModel = new Client();
        $db = \App\Core\Database::getInstance();
        
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
        
        $stmt = $db->query($sql, [$startDate, $endDate]);
        $monthlyStats = $stmt->fetch();
        
        // Get top clients
        $sql = "
            SELECT 
                c.id, c.name, c.name_ar, c.client_code,
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
        
        // Get cashbox summary
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
        
        return [
            'monthlyStats' => $monthlyStats,
            'topClients' => $topClients,
            'cashboxSummary' => $cashboxSummary
        ];
    }
    
    /**
     * Get cashbox report data
     */
    private function getCashboxReportData($startDate, $endDate)
    {
        $cashboxModel = new Cashbox();
        $db = \App\Core\Database::getInstance();
        
        // Get movements grouped by category
        $sql = "
            SELECT 
                category, movement_type,
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
        
        return [
            'categorySummary' => $categorySummary,
            'dailyBalances' => $dailyBalances,
            'currentBalance' => $currentBalance
        ];
    }
    
    /**
     * Get client statement data
     */
    private function getClientStatementData($clientId, $startDate, $endDate)
    {
        $clientModel = new Client();
        $transactionModel = new Transaction();
        
        // Get client
        $client = $clientModel->find($clientId);
        if (!$client) {
            throw new \Exception(__('messages.client_not_found'));
        }
        
        // Get client transactions
        $transactions = $transactionModel->all([
            'client_id' => $clientId,
            'transaction_date >=' => $startDate,
            'transaction_date <=' => $endDate
        ], 'transaction_date DESC');
        
        return [
            'client' => $client,
            'transactions' => $transactions
        ];
    }
    
    /**
     * Get transaction filters from request
     */
    private function getTransactionFilters()
    {
        return [
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null,
            'client_id' => $_GET['client_id'] ?? null,
            'status' => $_GET['status'] ?? null,
            'transaction_type' => $_GET['transaction_type'] ?? null
        ];
    }
    
    /**
     * Get transactions data based on filters
     */
    private function getTransactionsData($filters)
    {
        $transactionModel = new Transaction();
        
        // Build query conditions
        $conditions = [];
        $params = [];
        
        if (!empty($filters['start_date'])) {
            $conditions[] = 'transaction_date >= ?';
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $conditions[] = 'transaction_date <= ?';
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['client_id'])) {
            $conditions[] = 'client_id = ?';
            $params[] = $filters['client_id'];
        }
        
        if (!empty($filters['status'])) {
            $conditions[] = 'status = ?';
            $params[] = $filters['status'];
        }
        
        // Get transactions with details
        return $transactionModel->getTransactionsWithDetails($conditions, $params);
    }
    
    /**
     * Get clients data
     */
    private function getClientsData()
    {
        $clientModel = new Client();
        
        $sql = "
            SELECT 
                c.*,
                COUNT(t.id) as transaction_count,
                SUM(t.total_amount_rmb) as total_transactions_rmb,
                SUM(t.payment_rmb) as total_payments_rmb,
                MAX(t.transaction_date) as last_transaction_date
            FROM clients c
            LEFT JOIN transactions t ON c.id = t.client_id AND t.status = 'approved'
            WHERE c.status = 'active'
            GROUP BY c.id
            ORDER BY c.name
        ";
        
        $db = \App\Core\Database::getInstance();
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Generate transactions PDF (simple list format)
     */
    private function generateTransactionsPdf($transactions, $filters)
    {
        // This is a simplified implementation
        // You might want to create a more detailed transactions report template
        
        $reportData = [
            'title' => __('transactions.title'),
            'filters' => $filters,
            'transactions' => $transactions,
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['user_name'] ?? __('system')
        ];
        
        // Use a simple template or create a basic PDF
        $html = $this->generateTransactionsPdfHtml($reportData);
        
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L' // Landscape for better table display
        ]);
        
        $mpdf->WriteHTML($html);
        
        $filename = 'transactions_' . date('YmdHis') . '.pdf';
        $filepath = BASE_PATH . '/storage/exports/pdf/' . $filename;
        
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        $mpdf->Output($filepath, 'F');
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'type' => 'application/pdf'
        ];
    }
    
    /**
     * Generate simple transactions PDF HTML
     */
    private function generateTransactionsPdfHtml($data)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . $data['title'] . '</title>
    <link rel="stylesheet" href="/assets/css/print.css">
</head>
<body>
    <div class="print-container">
        <div class="print-header">
            <h1>' . $data['title'] . '</h1>
            <p>' . __('reports.generated') . ': ' . $data['generated_at'] . '</p>
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>' . __('date') . '</th>
                    <th>' . __('transactions.transaction_no') . '</th>
                    <th>' . __('clients.name') . '</th>
                    <th>' . __('transactions.type') . '</th>
                    <th>' . __('total') . '</th>
                    <th>' . __('transactions.payment') . '</th>
                    <th>' . __('balance') . '</th>
                    <th>' . __('status') . '</th>
                </tr>
            </thead>
            <tbody>';
            
        foreach ($data['transactions'] as $transaction) {
            $html .= '<tr>
                <td>' . date('Y-m-d', strtotime($transaction['transaction_date'])) . '</td>
                <td>' . h($transaction['transaction_no'] ?? '') . '</td>
                <td>' . htmlspecialchars($transaction['client_name'] ?? '') . '</td>
                <td>' . htmlspecialchars($transaction['transaction_type_name'] ?? '') . '</td>
                <td class="amount">¥' . number_format($transaction['total_amount_rmb'], 2) . '</td>
                <td class="amount">¥' . number_format($transaction['payment_rmb'], 2) . '</td>
                <td class="amount">¥' . number_format($transaction['balance_rmb'], 2) . '</td>
                <td>' . __('transactions.' . $transaction['status']) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
        </table>
    </div>
</body>
</html>';
        
        return $html;
    }
}