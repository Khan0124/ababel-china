<?php
namespace App\Services;

use App\Core\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ExcelExportService
{
    private $db;
    private $companySettings;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->loadCompanySettings();
    }
    
    /**
     * Export daily report to Excel
     */
    public function exportDailyReport($date, $transactions, $movements, $dailyTotals)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set sheet properties
        $sheet->setTitle(__('reports.daily_report'));
        $spreadsheet->getProperties()
            ->setCreator($this->companySettings['name'])
            ->setTitle(__('reports.daily_report') . ' - ' . $date)
            ->setSubject(__('reports.daily_report'))
            ->setDescription(__('reports.daily_report') . ' for ' . $date);
        
        // Add header
        $this->addReportHeader($sheet, __('reports.daily_report'), $date);
        
        $currentRow = 6; // Start after header
        
        // Add summary section
        $currentRow = $this->addDailySummary($sheet, $currentRow, $dailyTotals);
        
        // Add transactions section
        if (!empty($transactions)) {
            $currentRow = $this->addTransactionsSection($sheet, $currentRow, $transactions);
        }
        
        // Add movements section
        if (!empty($movements)) {
            $currentRow = $this->addMovementsSection($sheet, $currentRow, $movements);
        }
        
        // Auto-size columns
        $this->autoSizeColumns($sheet);
        
        return $this->saveExcel($spreadsheet, "daily-report-{$date}");
    }
    
    /**
     * Export monthly report to Excel
     */
    public function exportMonthlyReport($month, $monthlyStats, $topClients, $cashboxSummary)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set sheet properties
        $sheet->setTitle(__('reports.monthly_report'));
        $spreadsheet->getProperties()
            ->setCreator($this->companySettings['name'])
            ->setTitle(__('reports.monthly_report') . ' - ' . date('F Y', strtotime($month . '-01')))
            ->setSubject(__('reports.monthly_report'))
            ->setDescription(__('reports.monthly_report') . ' for ' . date('F Y', strtotime($month . '-01')));
        
        // Add header
        $this->addReportHeader($sheet, __('reports.monthly_report'), date('F Y', strtotime($month . '-01')));
        
        $currentRow = 6;
        
        // Add monthly summary
        $currentRow = $this->addMonthlySummary($sheet, $currentRow, $monthlyStats);
        
        // Add financial summary
        $currentRow = $this->addFinancialSummary($sheet, $currentRow, $monthlyStats, $cashboxSummary);
        
        // Add top clients
        if (!empty($topClients)) {
            $currentRow = $this->addTopClientsSection($sheet, $currentRow, $topClients, $monthlyStats);
        }
        
        $this->autoSizeColumns($sheet);
        
        return $this->saveExcel($spreadsheet, "monthly-report-{$month}");
    }
    
    /**
     * Export cashbox report to Excel
     */
    public function exportCashboxReport($startDate, $endDate, $categorySummary, $dailyBalances, $currentBalance)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set sheet properties  
        $sheet->setTitle(__('reports.cashbox_report'));
        $spreadsheet->getProperties()
            ->setCreator($this->companySettings['name'])
            ->setTitle(__('reports.cashbox_report'))
            ->setSubject(__('reports.cashbox_report'))
            ->setDescription(__('reports.cashbox_report') . ' from ' . $startDate . ' to ' . $endDate);
        
        // Add header
        $this->addReportHeader($sheet, __('reports.cashbox_report'), $startDate . ' - ' . $endDate);
        
        $currentRow = 6;
        
        // Add current balance
        $currentRow = $this->addCurrentBalance($sheet, $currentRow, $currentBalance);
        
        // Add category summary
        if (!empty($categorySummary)) {
            $currentRow = $this->addCategorySummary($sheet, $currentRow, $categorySummary);
        }
        
        // Add daily balances
        if (!empty($dailyBalances)) {
            $currentRow = $this->addDailyBalances($sheet, $currentRow, $dailyBalances);
        }
        
        $this->autoSizeColumns($sheet);
        
        return $this->saveExcel($spreadsheet, "cashbox-report-{$startDate}-{$endDate}");
    }
    
    /**
     * Export client statement to Excel
     */
    public function exportClientStatement($client, $transactions, $startDate, $endDate)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set sheet properties
        $sheet->setTitle(__('clients.client_statement'));
        $spreadsheet->getProperties()
            ->setCreator($this->companySettings['name'])
            ->setTitle(__('clients.client_statement') . ' - ' . $client['client_code'])
            ->setSubject(__('clients.client_statement'))
            ->setDescription(__('clients.client_statement') . ' for ' . $client['name'] . ' (' . $client['client_code'] . ')');
        
        // Add header
        $this->addReportHeader($sheet, __('clients.client_statement'), $startDate . ' - ' . $endDate);
        
        $currentRow = 6;
        
        // Add client info
        $currentRow = $this->addClientInfo($sheet, $currentRow, $client);
        
        // Add client summary
        $currentRow = $this->addClientSummary($sheet, $currentRow, $transactions);
        
        // Add transactions
        if (!empty($transactions)) {
            $currentRow = $this->addClientTransactions($sheet, $currentRow, $transactions);
        }
        
        // Add USD section if applicable
        $hasUsd = array_sum(array_column($transactions, 'shipping_usd')) > 0 || 
                  array_sum(array_column($transactions, 'payment_usd')) > 0;
        
        if ($hasUsd) {
            $currentRow = $this->addUsdSection($sheet, $currentRow, $transactions);
        }
        
        $this->autoSizeColumns($sheet);
        
        return $this->saveExcel($spreadsheet, "statement-{$client['client_code']}-{$startDate}-{$endDate}");
    }
    
    /**
     * Export transactions list to Excel
     */
    public function exportTransactionsList($transactions, $filters = [])
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set sheet properties
        $sheet->setTitle(__('transactions.title'));
        $spreadsheet->getProperties()
            ->setCreator($this->companySettings['name'])
            ->setTitle(__('transactions.title'))
            ->setSubject(__('transactions.title'))
            ->setDescription(__('transactions.title') . ' export');
        
        // Add header
        $this->addReportHeader($sheet, __('transactions.title'), date('Y-m-d'));
        
        $currentRow = 6;
        
        // Add filters info if any
        if (!empty($filters)) {
            $currentRow = $this->addFiltersInfo($sheet, $currentRow, $filters);
        }
        
        // Add transactions
        $currentRow = $this->addTransactionsSection($sheet, $currentRow, $transactions);
        
        $this->autoSizeColumns($sheet);
        
        return $this->saveExcel($spreadsheet, "transactions-export-" . date('Y-m-d'));
    }
    
    /**
     * Export clients list to Excel
     */
    public function exportClientsList($clients)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set sheet properties
        $sheet->setTitle(__('clients.title'));
        $spreadsheet->getProperties()
            ->setCreator($this->companySettings['name'])
            ->setTitle(__('clients.title'))
            ->setSubject(__('clients.title'))
            ->setDescription(__('clients.title') . ' export');
        
        // Add header
        $this->addReportHeader($sheet, __('clients.title'), date('Y-m-d'));
        
        $currentRow = 6;
        
        // Add clients section
        $currentRow = $this->addClientsSection($sheet, $currentRow, $clients);
        
        $this->autoSizeColumns($sheet);
        
        return $this->saveExcel($spreadsheet, "clients-export-" . date('Y-m-d'));
    }
    
    /**
     * Add report header
     */
    private function addReportHeader($sheet, $title, $subtitle)
    {
        // Company name
        $sheet->setCellValue('A1', $this->companySettings['name']);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('A1:H1');
        
        // Report title
        $sheet->setCellValue('A2', $title);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('A2:H2');
        
        // Subtitle
        $sheet->setCellValue('A3', $subtitle);
        $sheet->getStyle('A3')->getFont()->setSize(12);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('A3:H3');
        
        // Generation info
        $sheet->setCellValue('A4', __('reports.generated') . ': ' . date('Y-m-d H:i:s') . ' | ' . 
                             __('nav.profile') . ': ' . ($_SESSION['user_name'] ?? '-'));
        $sheet->getStyle('A4')->getFont()->setSize(10)->setItalic(true);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('A4:H4');
        
        // Add logo if exists
        if (file_exists(BASE_PATH . '/public/assets/images/logo.png')) {
            $drawing = new Drawing();
            $drawing->setName('Logo');
            $drawing->setDescription('Company Logo');
            $drawing->setPath(BASE_PATH . '/public/assets/images/logo.png');
            $drawing->setHeight(50);
            $drawing->setCoordinates('A1');
            $drawing->setWorksheet($sheet);
        }
        
        $sheet->getRowDimension('5')->setRowHeight(20); // Empty row
    }
    
    /**
     * Add daily summary section
     */
    private function addDailySummary($sheet, $row, $dailyTotals)
    {
        // Section title
        $sheet->setCellValue("A{$row}", __('reports.daily_summary'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:D{$row}");
        $row++;
        
        // Headers
        $sheet->setCellValue("A{$row}", __('metric'));
        $sheet->setCellValue("B{$row}", __('value'));
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:B{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        $row++;
        
        // Data
        $summaryData = [
            __('transactions.title') => $dailyTotals['transactions_count'],
            __('transactions.goods_amount') . ' (RMB)' => '¥' . number_format($dailyTotals['total_goods_rmb'], 2),
            __('transactions.commission') . ' (RMB)' => '¥' . number_format($dailyTotals['total_commission_rmb'], 2),
            __('transactions.payment') . ' (RMB)' => '¥' . number_format($dailyTotals['total_payments_rmb'], 2),
            __('transactions.payment') . ' (USD)' => '$' . number_format($dailyTotals['total_payments_usd'], 2),
            __('cashbox.in') . ' (RMB)' => '¥' . number_format($dailyTotals['cashbox_in_rmb'], 2),
            __('cashbox.out') . ' (RMB)' => '¥' . number_format($dailyTotals['cashbox_out_rmb'], 2),
            __('cashbox.in') . ' (USD)' => '$' . number_format($dailyTotals['cashbox_in_usd'], 2),
            __('cashbox.out') . ' (USD)' => '$' . number_format($dailyTotals['cashbox_out_usd'], 2)
        ];
        
        foreach ($summaryData as $label => $value) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }
        
        $this->addBorders($sheet, "A" . ($row - count($summaryData) - 1) . ":B" . ($row - 1));
        
        return $row + 1;
    }
    
    /**
     * Add transactions section
     */
    private function addTransactionsSection($sheet, $row, $transactions)
    {
        // Section title
        $sheet->setCellValue("A{$row}", __('transactions.title'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:H{$row}");
        $row++;
        
        // Headers
        $headers = [
            'A' => __('transactions.transaction_no'),
            'B' => __('clients.name'),
            'C' => __('transactions.invoice_no'),
            'D' => __('transactions.goods_amount'),
            'E' => __('transactions.commission'),
            'F' => __('total'),
            'G' => __('transactions.payment'),
            'H' => __('balance')
        ];
        
        foreach ($headers as $col => $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
        }
        
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:H{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        
        $row++;
        $startDataRow = $row;
        
        // Data
        $totals = [
            'goods' => 0,
            'commission' => 0,
            'total' => 0,
            'payment' => 0
        ];
        
        foreach ($transactions as $transaction) {
            $sheet->setCellValue("A{$row}", $transaction['transaction_no'] ?? '');
            $sheet->setCellValue("B{$row}", $transaction['client_name'] ?? '');
            $sheet->setCellValue("C{$row}", $transaction['invoice_no'] ?? '');
            $sheet->setCellValue("D{$row}", $transaction['goods_amount_rmb'] ?? 0);
            $sheet->setCellValue("E{$row}", $transaction['commission_rmb'] ?? 0);
            $sheet->setCellValue("F{$row}", $transaction['total_amount_rmb'] ?? 0);
            $sheet->setCellValue("G{$row}", $transaction['payment_rmb'] ?? 0);
            $sheet->setCellValue("H{$row}", $transaction['balance_rmb'] ?? 0);
            
            // Format amounts
            foreach (['D', 'E', 'F', 'G', 'H'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                    ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
            }
            
            // Color balance based on value
            if (($transaction['balance_rmb'] ?? 0) > 0) {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FFDC3545');
            } else {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FF28A745');
            }
            
            $totals['goods'] += $transaction['goods_amount_rmb'] ?? 0;
            $totals['commission'] += $transaction['commission_rmb'] ?? 0;
            $totals['total'] += $transaction['total_amount_rmb'] ?? 0;
            $totals['payment'] += $transaction['payment_rmb'] ?? 0;
            
            $row++;
        }
        
        // Totals row
        $sheet->setCellValue("A{$row}", __('total'));
        $sheet->setCellValue("D{$row}", $totals['goods']);
        $sheet->setCellValue("E{$row}", $totals['commission']);
        $sheet->setCellValue("F{$row}", $totals['total']);
        $sheet->setCellValue("G{$row}", $totals['payment']);
        
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:H{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF8F9FA');
        
        foreach (['D', 'E', 'F', 'G'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
        }
        
        $this->addBorders($sheet, "A" . ($startDataRow - 1) . ":H{$row}");
        
        return $row + 2;
    }
    
    /**
     * Add movements section
     */
    private function addMovementsSection($sheet, $row, $movements)
    {
        // Section title
        $sheet->setCellValue("A{$row}", __('cashbox.movements'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:F{$row}");
        $row++;
        
        // Headers
        $headers = [
            'A' => __('cashbox.movement_type'),
            'B' => __('cashbox.category'),
            'C' => __('transactions.description'),
            'D' => 'RMB',
            'E' => 'USD',
            'F' => __('cashbox.receipt_no')
        ];
        
        foreach ($headers as $col => $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
        }
        
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:F{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        
        $row++;
        $startDataRow = $row;
        
        // Data
        $totals = ['in_rmb' => 0, 'out_rmb' => 0, 'in_usd' => 0, 'out_usd' => 0];
        
        foreach ($movements as $movement) {
            $sheet->setCellValue("A{$row}", __('cashbox.' . $movement['movement_type']));
            $sheet->setCellValue("B{$row}", __('cashbox.' . $movement['category']));
            $sheet->setCellValue("C{$row}", $movement['description'] ?? '');
            $sheet->setCellValue("D{$row}", ($movement['movement_type'] == 'in' ? 1 : -1) * $movement['amount_rmb']);
            $sheet->setCellValue("E{$row}", ($movement['movement_type'] == 'in' ? 1 : -1) * $movement['amount_usd']);
            $sheet->setCellValue("F{$row}", $movement['receipt_no'] ?? '');
            
            // Format amounts
            foreach (['D', 'E'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                    ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
                
                // Color based on movement type
                if ($movement['movement_type'] == 'in') {
                    $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setARGB('FF28A745');
                } else {
                    $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setARGB('FFDC3545');
                }
            }
            
            if ($movement['movement_type'] == 'in') {
                $totals['in_rmb'] += $movement['amount_rmb'];
                $totals['in_usd'] += $movement['amount_usd'];
            } else {
                $totals['out_rmb'] += $movement['amount_rmb'];
                $totals['out_usd'] += $movement['amount_usd'];
            }
            
            $row++;
        }
        
        // Totals row
        $sheet->setCellValue("A{$row}", __('reports.total_in'));
        $sheet->setCellValue("D{$row}", $totals['in_rmb']);
        $sheet->setCellValue("E{$row}", $totals['in_usd']);
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:F{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD4EDDA');
        $row++;
        
        $sheet->setCellValue("A{$row}", __('reports.total_out'));
        $sheet->setCellValue("D{$row}", -$totals['out_rmb']);
        $sheet->setCellValue("E{$row}", -$totals['out_usd']);
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:F{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF8D7DA');
        $row++;
        
        $sheet->setCellValue("A{$row}", __('reports.net_change'));
        $sheet->setCellValue("D{$row}", $totals['in_rmb'] - $totals['out_rmb']);
        $sheet->setCellValue("E{$row}", $totals['in_usd'] - $totals['out_usd']);
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:F{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCE5FF');
        
        foreach (['D', 'E'] as $col) {
            for ($i = $row - 2; $i <= $row; $i++) {
                $sheet->getStyle("{$col}{$i}")->getNumberFormat()
                    ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
            }
        }
        
        $this->addBorders($sheet, "A" . ($startDataRow - 1) . ":F{$row}");
        
        return $row + 2;
    }
    
    /**
     * Add client info section
     */
    private function addClientInfo($sheet, $row, $client)
    {
        $sheet->setCellValue("A{$row}", __('clients.client_info'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:D{$row}");
        $row++;
        
        $clientInfo = [
            __('clients.name') => (lang() == 'ar') ? ($client['name_ar'] ?? $client['name']) : $client['name'],
            __('clients.client_code') => $client['client_code'],
            __('clients.phone') => $client['phone'] ?? '',
            __('clients.email') => $client['email'] ?? ''
        ];
        
        foreach ($clientInfo as $label => $value) {
            if (!empty($value)) {
                $sheet->setCellValue("A{$row}", $label . ':');
                $sheet->setCellValue("B{$row}", $value);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $row++;
            }
        }
        
        return $row + 1;
    }
    
    /**
     * Add client summary
     */
    private function addClientSummary($sheet, $row, $transactions)
    {
        $totalTransactions = array_sum(array_column($transactions, 'total_amount_rmb'));
        $totalPayments = array_sum(array_column($transactions, 'payment_rmb'));
        $totalBalance = array_sum(array_column($transactions, 'balance_rmb'));
        
        $sheet->setCellValue("A{$row}", __('reports.summary'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:D{$row}");
        $row++;
        
        $summaryData = [
            __('transactions.total_amount') => '¥' . number_format($totalTransactions, 2),
            __('transactions.payment') => '¥' . number_format($totalPayments, 2),
            __('balance') => '¥' . number_format($totalBalance, 2),
            __('clients.transaction_count') => count($transactions)
        ];
        
        // Headers
        $sheet->setCellValue("A{$row}", __('metric'));
        $sheet->setCellValue("B{$row}", __('value'));
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:B{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        $row++;
        
        foreach ($summaryData as $label => $value) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            if ($label == __('balance') && $totalBalance > 0) {
                $sheet->getStyle("B{$row}")->getFont()->getColor()->setARGB('FFDC3545');
            } elseif ($label == __('balance') && $totalBalance <= 0) {
                $sheet->getStyle("B{$row}")->getFont()->getColor()->setARGB('FF28A745');
            }
            $row++;
        }
        
        $this->addBorders($sheet, "A" . ($row - count($summaryData) - 1) . ":B" . ($row - 1));
        
        return $row + 1;
    }
    
    /**
     * Add client transactions
     */
    private function addClientTransactions($sheet, $row, $transactions)
    {
        // Section title
        $sheet->setCellValue("A{$row}", __('transactions.title'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:I{$row}");
        $row++;
        
        // Headers
        $headers = [
            'A' => __('date'),
            'B' => __('transactions.transaction_no'),
            'C' => __('transactions.type'),
            'D' => __('transactions.goods_amount'),
            'E' => __('transactions.commission'),
            'F' => __('total'),
            'G' => __('transactions.payment'),
            'H' => __('balance'),
            'I' => __('status')
        ];
        
        foreach ($headers as $col => $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
        }
        
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:I{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        
        $row++;
        $startDataRow = $row;
        
        // Data
        $totals = [
            'goods' => 0,
            'commission' => 0,
            'total' => 0,
            'payment' => 0,
            'balance' => 0
        ];
        
        foreach ($transactions as $transaction) {
            $sheet->setCellValue("A{$row}", date('Y-m-d', strtotime($transaction['transaction_date'])));
            $sheet->setCellValue("B{$row}", $transaction['transaction_no'] ?? '');
            $sheet->setCellValue("C{$row}", $transaction['transaction_type_name'] ?? '');
            $sheet->setCellValue("D{$row}", $transaction['goods_amount_rmb'] ?? 0);
            $sheet->setCellValue("E{$row}", $transaction['commission_rmb'] ?? 0);
            $sheet->setCellValue("F{$row}", $transaction['total_amount_rmb'] ?? 0);
            $sheet->setCellValue("G{$row}", $transaction['payment_rmb'] ?? 0);
            $sheet->setCellValue("H{$row}", $transaction['balance_rmb'] ?? 0);
            $sheet->setCellValue("I{$row}", __('transactions.' . $transaction['status']));
            
            // Format amounts
            foreach (['D', 'E', 'F', 'G', 'H'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                    ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
            }
            
            // Color balance
            if (($transaction['balance_rmb'] ?? 0) > 0) {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FFDC3545');
            } else {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FF28A745');
            }
            
            // Color status
            switch ($transaction['status']) {
                case 'approved':
                    $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FF28A745');
                    break;
                case 'pending':
                    $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FFFFC107');
                    break;
                case 'cancelled':
                    $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FFDC3545');
                    break;
            }
            
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $transaction[$key == 'total' ? 'total_amount_rmb' : $key . '_rmb'] ?? 0;
            }
            
            $row++;
        }
        
        // Totals row
        $sheet->setCellValue("A{$row}", __('total'));
        $sheet->setCellValue("D{$row}", $totals['goods']);
        $sheet->setCellValue("E{$row}", $totals['commission']);
        $sheet->setCellValue("F{$row}", $totals['total']);
        $sheet->setCellValue("G{$row}", $totals['payment']);
        $sheet->setCellValue("H{$row}", $totals['balance']);
        
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:I{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF8F9FA');
        
        foreach (['D', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
        }
        
        // Color total balance
        if ($totals['balance'] > 0) {
            $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FFDC3545');
        } else {
            $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FF28A745');
        }
        
        $this->addBorders($sheet, "A" . ($startDataRow - 1) . ":I{$row}");
        
        return $row + 2;
    }
    
    /**
     * Save Excel file
     */
    private function saveExcel($spreadsheet, $filename)
    {
        $writer = new Xlsx($spreadsheet);
        
        // Create directory if not exists
        $dir = BASE_PATH . '/storage/exports/excel/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $filepath = $dir . $filename . '_' . date('YmdHis') . '.xlsx';
        $writer->save($filepath);
        
        return [
            'success' => true,
            'filename' => basename($filepath),
            'filepath' => $filepath,
            'download_url' => '/exports/excel/' . basename($filepath)
        ];
    }
    
    /**
     * Auto-size columns
     */
    private function autoSizeColumns($sheet)
    {
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
    
    /**
     * Add borders to range
     */
    private function addBorders($sheet, $range)
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('FF000000');
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
            'email' => ''
        ];
        
        foreach ($results as $row) {
            $key = str_replace('company_', '', $row['setting_key']);
            $this->companySettings[$key] = $row['setting_value'];
        }
    }
    
    /**
     * Add additional helper methods as needed for other sections...
     */
    private function addMonthlySummary($sheet, $row, $monthlyStats)
    {
        // Implementation for monthly summary
        $sheet->setCellValue("A{$row}", __('reports.monthly_summary'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:D{$row}");
        $row++;
        
        // Headers
        $sheet->setCellValue("A{$row}", __('metric'));
        $sheet->setCellValue("B{$row}", __('value'));
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:B{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        $row++;
        
        $summaryData = [
            __('transactions.title') => $monthlyStats['total_transactions'],
            __('clients.active') => $monthlyStats['active_clients'],
            __('total') . ' (RMB)' => '¥' . number_format($monthlyStats['total_amount_rmb'], 2),
            __('transactions.payment') . ' (RMB)' => '¥' . number_format($monthlyStats['total_payments_rmb'], 2)
        ];
        
        foreach ($summaryData as $label => $value) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }
        
        $this->addBorders($sheet, "A" . ($row - count($summaryData) - 1) . ":B" . ($row - 1));
        
        return $row + 1;
    }
    
    private function addFinancialSummary($sheet, $row, $monthlyStats, $cashboxSummary)
    {
        // Implementation for financial summary
        $sheet->setCellValue("A{$row}", __('reports.financial_summary'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:D{$row}");
        $row++;
        
        // Headers
        $sheet->setCellValue("A{$row}", __('category'));
        $sheet->setCellValue("B{$row}", 'RMB');
        $sheet->setCellValue("C{$row}", 'USD');
        $sheet->setCellValue("D{$row}", __('transactions.count'));
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        $row++;
        
        $financialData = [
            __('transactions.goods_amount') => [
                'rmb' => $monthlyStats['total_goods_rmb'] ?? 0,
                'usd' => 0,
                'count' => ''
            ],
            __('transactions.commission') => [
                'rmb' => $monthlyStats['total_commission_rmb'] ?? 0,
                'usd' => 0,
                'count' => ''
            ],
            __('transactions.shipping') => [
                'rmb' => 0,
                'usd' => $monthlyStats['total_shipping_usd'] ?? 0,
                'count' => ''
            ],
            __('transactions.payment') => [
                'rmb' => $monthlyStats['total_payments_rmb'] ?? 0,
                'usd' => $monthlyStats['total_payments_usd'] ?? 0,
                'count' => ''
            ],
            __('cashbox.movements') => [
                'rmb' => 0,
                'usd' => 0,
                'count' => $cashboxSummary['movements_count'] ?? 0
            ]
        ];
        
        foreach ($financialData as $label => $data) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $data['rmb'] > 0 ? $data['rmb'] : '');
            $sheet->setCellValue("C{$row}", $data['usd'] > 0 ? $data['usd'] : '');
            $sheet->setCellValue("D{$row}", $data['count'] ?: '');
            
            // Format amounts
            foreach (['B', 'C'] as $col) {
                if ($sheet->getCell("{$col}{$row}")->getValue()) {
                    $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                        ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
                }
            }
            
            $row++;
        }
        
        $this->addBorders($sheet, "A" . ($row - count($financialData) - 1) . ":D" . ($row - 1));
        
        return $row + 1;
    }
    
    private function addTopClientsSection($sheet, $row, $topClients, $monthlyStats)
    {
        // Implementation for top clients
        $sheet->setCellValue("A{$row}", __('reports.top_clients'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:G{$row}");
        $row++;
        
        // Headers
        $headers = [
            'A' => '#',
            'B' => __('clients.client_code'),
            'C' => __('clients.name'),
            'D' => __('transactions.count'),
            'E' => __('total') . ' (RMB)',
            'F' => __('transactions.payment') . ' (RMB)',
            'G' => __('percentage')
        ];
        
        foreach ($headers as $col => $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
        }
        
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        
        $row++;
        $startDataRow = $row;
        
        $rank = 1;
        $totalRevenue = $monthlyStats['total_amount_rmb'];
        
        foreach ($topClients as $client) {
            $percentage = $totalRevenue > 0 ? ($client['total_amount_rmb'] / $totalRevenue * 100) : 0;
            
            $sheet->setCellValue("A{$row}", $rank++);
            $sheet->setCellValue("B{$row}", $client['client_code']);
            $sheet->setCellValue("C{$row}", (lang() == 'ar') ? ($client['name_ar'] ?? $client['name']) : $client['name']);
            $sheet->setCellValue("D{$row}", $client['transaction_count']);
            $sheet->setCellValue("E{$row}", $client['total_amount_rmb']);
            $sheet->setCellValue("F{$row}", $client['total_payments_rmb']);
            $sheet->setCellValue("G{$row}", $percentage . '%');
            
            // Format amounts
            foreach (['E', 'F'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                    ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
            }
            
            $row++;
        }
        
        $this->addBorders($sheet, "A" . ($startDataRow - 1) . ":G" . ($row - 1));
        
        return $row + 1;
    }
    
    private function addCurrentBalance($sheet, $row, $currentBalance)
    {
        // Implementation for current balance
        $sheet->setCellValue("A{$row}", __('cashbox.current_balance'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:E{$row}");
        $row++;
        
        // Headers
        $sheet->setCellValue("A{$row}", __('currency'));
        $sheet->setCellValue("B{$row}", __('balance'));
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:B{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        $row++;
        
        $balances = [
            'RMB' => $currentBalance['balance_rmb'] ?? 0,
            'USD' => $currentBalance['balance_usd'] ?? 0,
            'SDG' => $currentBalance['balance_sdg'] ?? 0,
            'AED' => $currentBalance['balance_aed'] ?? 0
        ];
        
        foreach ($balances as $currency => $balance) {
            $sheet->setCellValue("A{$row}", $currency);
            $sheet->setCellValue("B{$row}", $balance);
            
            // Format amount
            $sheet->getStyle("B{$row}")->getNumberFormat()
                ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
            
            // Color based on balance
            if ($balance >= 0) {
                $sheet->getStyle("B{$row}")->getFont()->getColor()->setARGB('FF28A745');
            } else {
                $sheet->getStyle("B{$row}")->getFont()->getColor()->setARGB('FFDC3545');
            }
            
            $row++;
        }
        
        $this->addBorders($sheet, "A" . ($row - count($balances) - 1) . ":B" . ($row - 1));
        
        return $row + 1;
    }
    
    private function addCategorySummary($sheet, $row, $categorySummary)
    {
        // Implementation for category summary
        $sheet->setCellValue("A{$row}", __('reports.movement_by_category'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:G{$row}");
        $row++;
        
        // Headers
        $headers = [
            'A' => __('cashbox.category'),
            'B' => __('cashbox.movement_type'),
            'C' => __('reports.transactions'),
            'D' => 'RMB',
            'E' => 'USD',
            'F' => 'SDG',
            'G' => 'AED'
        ];
        
        foreach ($headers as $col => $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
        }
        
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        
        $row++;
        $startDataRow = $row;
        
        // Calculate totals
        $totalIn = ['rmb' => 0, 'usd' => 0, 'sdg' => 0, 'aed' => 0];
        $totalOut = ['rmb' => 0, 'usd' => 0, 'sdg' => 0, 'aed' => 0];
        
        foreach ($categorySummary as $rowData) {
            $sheet->setCellValue("A{$row}", __('cashbox.' . $rowData['category']));
            $sheet->setCellValue("B{$row}", __('cashbox.' . $rowData['movement_type']));
            $sheet->setCellValue("C{$row}", $rowData['count']);
            $sheet->setCellValue("D{$row}", ($rowData['movement_type'] == 'in' ? 1 : -1) * $rowData['total_rmb']);
            $sheet->setCellValue("E{$row}", ($rowData['movement_type'] == 'in' ? 1 : -1) * $rowData['total_usd']);
            $sheet->setCellValue("F{$row}", ($rowData['movement_type'] == 'in' ? 1 : -1) * $rowData['total_sdg']);
            $sheet->setCellValue("G{$row}", ($rowData['movement_type'] == 'in' ? 1 : -1) * $rowData['total_aed']);
            
            // Format amounts and color
            foreach (['D', 'E', 'F', 'G'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                    ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
                
                if ($rowData['movement_type'] == 'in') {
                    $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setARGB('FF28A745');
                } else {
                    $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setARGB('FFDC3545');
                }
            }
            
            if ($rowData['movement_type'] == 'in') {
                $totalIn['rmb'] += $rowData['total_rmb'];
                $totalIn['usd'] += $rowData['total_usd'];
                $totalIn['sdg'] += $rowData['total_sdg'];
                $totalIn['aed'] += $rowData['total_aed'];
            } else {
                $totalOut['rmb'] += $rowData['total_rmb'];
                $totalOut['usd'] += $rowData['total_usd'];
                $totalOut['sdg'] += $rowData['total_sdg'];
                $totalOut['aed'] += $rowData['total_aed'];
            }
            
            $row++;
        }
        
        // Add total rows
        $sheet->setCellValue("A{$row}", __('reports.total_in'));
        $sheet->setCellValue("D{$row}", $totalIn['rmb']);
        $sheet->setCellValue("E{$row}", $totalIn['usd']);
        $sheet->setCellValue("F{$row}", $totalIn['sdg']);
        $sheet->setCellValue("G{$row}", $totalIn['aed']);
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD4EDDA');
        $row++;
        
        $sheet->setCellValue("A{$row}", __('reports.total_out'));
        $sheet->setCellValue("D{$row}", -$totalOut['rmb']);
        $sheet->setCellValue("E{$row}", -$totalOut['usd']);
        $sheet->setCellValue("F{$row}", -$totalOut['sdg']);
        $sheet->setCellValue("G{$row}", -$totalOut['aed']);
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF8D7DA');
        $row++;
        
        $sheet->setCellValue("A{$row}", __('reports.net_change'));
        $sheet->setCellValue("D{$row}", $totalIn['rmb'] - $totalOut['rmb']);
        $sheet->setCellValue("E{$row}", $totalIn['usd'] - $totalOut['usd']);
        $sheet->setCellValue("F{$row}", $totalIn['sdg'] - $totalOut['sdg']);
        $sheet->setCellValue("G{$row}", $totalIn['aed'] - $totalOut['aed']);
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCE5FF');
        
        // Format total amounts
        for ($i = $row - 2; $i <= $row; $i++) {
            foreach (['D', 'E', 'F', 'G'] as $col) {
                $sheet->getStyle("{$col}{$i}")->getNumberFormat()
                    ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
            }
        }
        
        $this->addBorders($sheet, "A" . ($startDataRow - 1) . ":G{$row}");
        
        return $row + 2;
    }
    
    private function addDailyBalances($sheet, $row, $dailyBalances)
    {
        // Implementation for daily balances
        $sheet->setCellValue("A{$row}", __('reports.daily_balance_changes'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:E{$row}");
        $row++;
        
        // Headers
        $headers = [
            'A' => __('date'),
            'B' => __('reports.change') . ' (RMB)',
            'C' => __('reports.change') . ' (USD)',
            'D' => __('reports.running_balance') . ' (RMB)',
            'E' => __('reports.running_balance') . ' (USD)'
        ];
        
        foreach ($headers as $col => $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
        }
        
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        
        $row++;
        $startDataRow = $row;
        
        $runningRMB = 0;
        $runningUSD = 0;
        
        foreach ($dailyBalances as $day) {
            $runningRMB += $day['daily_change_rmb'];
            $runningUSD += $day['daily_change_usd'];
            
            $sheet->setCellValue("A{$row}", $day['movement_date']);
            $sheet->setCellValue("B{$row}", $day['daily_change_rmb']);
            $sheet->setCellValue("C{$row}", $day['daily_change_usd']);
            $sheet->setCellValue("D{$row}", $runningRMB);
            $sheet->setCellValue("E{$row}", $runningUSD);
            
            // Format amounts
            foreach (['B', 'C', 'D', 'E'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                    ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
            }
            
            // Color changes
            if ($day['daily_change_rmb'] >= 0) {
                $sheet->getStyle("B{$row}")->getFont()->getColor()->setARGB('FF28A745');
            } else {
                $sheet->getStyle("B{$row}")->getFont()->getColor()->setARGB('FFDC3545');
            }
            
            if ($day['daily_change_usd'] >= 0) {
                $sheet->getStyle("C{$row}")->getFont()->getColor()->setARGB('FF28A745');
            } else {
                $sheet->getStyle("C{$row}")->getFont()->getColor()->setARGB('FFDC3545');
            }
            
            $row++;
        }
        
        $this->addBorders($sheet, "A" . ($startDataRow - 1) . ":E" . ($row - 1));
        
        return $row + 1;
    }
    
    private function addUsdSection($sheet, $row, $transactions)
    {
        // Implementation for USD section
        $sheet->setCellValue("A{$row}", __('transactions.shipping') . ' (USD)');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:E{$row}");
        $row++;
        
        // Headers
        $headers = [
            'A' => __('date'),
            'B' => __('transactions.transaction_no'),
            'C' => __('transactions.shipping'),
            'D' => __('transactions.payment'),
            'E' => __('balance')
        ];
        
        foreach ($headers as $col => $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
        }
        
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        
        $row++;
        $startDataRow = $row;
        
        $totals = ['shipping' => 0, 'payment' => 0, 'balance' => 0];
        
        foreach ($transactions as $transaction) {
            if ($transaction['shipping_usd'] > 0 || $transaction['payment_usd'] > 0) {
                $sheet->setCellValue("A{$row}", date('Y-m-d', strtotime($transaction['transaction_date'])));
                $sheet->setCellValue("B{$row}", $transaction['transaction_no']);
                $sheet->setCellValue("C{$row}", $transaction['shipping_usd']);
                $sheet->setCellValue("D{$row}", $transaction['payment_usd']);
                $sheet->setCellValue("E{$row}", $transaction['balance_usd']);
                
                // Format amounts
                foreach (['C', 'D', 'E'] as $col) {
                    $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                        ->setFormatCode('_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)');
                }
                
                // Color balance
                if ($transaction['balance_usd'] > 0) {
                    $sheet->getStyle("E{$row}")->getFont()->getColor()->setARGB('FFDC3545');
                } else {
                    $sheet->getStyle("E{$row}")->getFont()->getColor()->setARGB('FF28A745');
                }
                
                $totals['shipping'] += $transaction['shipping_usd'];
                $totals['payment'] += $transaction['payment_usd'];
                $totals['balance'] += $transaction['balance_usd'];
                
                $row++;
            }
        }
        
        // Totals row
        if ($row > $startDataRow) {
            $sheet->setCellValue("A{$row}", __('total'));
            $sheet->setCellValue("C{$row}", $totals['shipping']);
            $sheet->setCellValue("D{$row}", $totals['payment']);
            $sheet->setCellValue("E{$row}", $totals['balance']);
            
            $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:E{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFF8F9FA');
            
            foreach (['C', 'D', 'E'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                    ->setFormatCode('_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)');
            }
            
            // Color total balance
            if ($totals['balance'] > 0) {
                $sheet->getStyle("E{$row}")->getFont()->getColor()->setARGB('FFDC3545');
            } else {
                $sheet->getStyle("E{$row}")->getFont()->getColor()->setARGB('FF28A745');
            }
            
            $this->addBorders($sheet, "A" . ($startDataRow - 1) . ":E{$row}");
            $row++;
        }
        
        return $row + 1;
    }
    
    private function addClientsSection($sheet, $row, $clients)
    {
        // Implementation for clients export
        $sheet->setCellValue("A{$row}", __('clients.title'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:H{$row}");
        $row++;
        
        // Headers
        $headers = [
            'A' => __('clients.client_code'),
            'B' => __('clients.name'),
            'C' => __('clients.name_ar'),
            'D' => __('clients.phone'),
            'E' => __('clients.email'),
            'F' => __('balance') . ' (RMB)',
            'G' => __('balance') . ' (USD)',
            'H' => __('status')
        ];
        
        foreach ($headers as $col => $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
        }
        
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:H{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');
        
        $row++;
        $startDataRow = $row;
        
        foreach ($clients as $client) {
            $sheet->setCellValue("A{$row}", $client['client_code']);
            $sheet->setCellValue("B{$row}", $client['name']);
            $sheet->setCellValue("C{$row}", $client['name_ar'] ?? '');
            $sheet->setCellValue("D{$row}", $client['phone'] ?? '');
            $sheet->setCellValue("E{$row}", $client['email'] ?? '');
            $sheet->setCellValue("F{$row}", $client['balance_rmb'] ?? 0);
            $sheet->setCellValue("G{$row}", $client['balance_usd'] ?? 0);
            $sheet->setCellValue("H{$row}", __('clients.' . $client['status']));
            
            // Format amounts
            foreach (['F', 'G'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                    ->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
            }
            
            // Color status
            if ($client['status'] == 'active') {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FF28A745');
            } else {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FFDC3545');
            }
            
            $row++;
        }
        
        $this->addBorders($sheet, "A" . ($startDataRow - 1) . ":H" . ($row - 1));
        
        return $row + 1;
    }
    
    private function addFiltersInfo($sheet, $row, $filters)
    {
        // Implementation for filters info
        $sheet->setCellValue("A{$row}", __('reports.filters_applied'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->mergeCells("A{$row}:D{$row}");
        $row++;
        
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $sheet->setCellValue("A{$row}", __($key) . ':');
                $sheet->setCellValue("B{$row}", $value);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $row++;
            }
        }
        
        return $row + 1;
    }
}