<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Transaction;
use App\Models\Client;
use Mpdf\Mpdf;

class InvoiceService
{
    private $db;
    private $transactionModel;
    private $clientModel;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->transactionModel = new Transaction();
        $this->clientModel = new Client();
    }
    
    /**
     * Generate invoice for a transaction
     */
    public function generateInvoice($transactionId, $format = 'pdf', $options = [])
    {
        try {
            // Get transaction details with client information
            $transaction = $this->transactionModel->getWithDetails($transactionId);
            
            if (!$transaction) {
                throw new \Exception(__('messages.transaction_not_found'));
            }
            
            // Get client details if not already included
            if (!isset($transaction['client_name'])) {
                $client = $this->clientModel->find($transaction['client_id']);
                if (!$client) {
                    throw new \Exception(__('messages.client_not_found'));
                }
                $transaction = array_merge($transaction, $client);
            }
            
            // Get company settings
            $settings = $this->getCompanySettings();
            
            // Generate invoice number if not exists
            if (empty($transaction['invoice_no'])) {
                $invoiceNo = $this->generateInvoiceNumber();
                $this->transactionModel->update($transactionId, ['invoice_no' => $invoiceNo]);
                $transaction['invoice_no'] = $invoiceNo;
            }
            
            // Calculate due date based on payment terms
            $paymentTerms = $options['payment_terms'] ?? 30;
            $dueDate = date('Y-m-d', strtotime($transaction['transaction_date'] . " +{$paymentTerms} days"));
            
            // Prepare invoice data
            $invoiceData = [
                'invoice_no' => $transaction['invoice_no'],
                'date' => $transaction['transaction_date'],
                'due_date' => $dueDate,
                'client' => $this->extractClientData($transaction),
                'transaction' => $transaction,
                'company' => $settings,
                'items' => $this->getInvoiceItems($transaction),
                'totals' => $this->calculateTotals($transaction),
                'currency_symbols' => $this->getCurrencySymbols(),
                'notes' => $options['notes'] ?? '',
                'terms' => $options['terms'] ?? $this->getDefaultTerms(),
                'language' => $options['language'] ?? lang(),
                'template' => $options['template'] ?? 'default'
            ];
            
            // Generate based on format
            switch ($format) {
                case 'pdf':
                    return $this->generatePdfInvoice($invoiceData, $options);
                case 'html':
                    return $this->generateHtmlInvoice($invoiceData, $options);
                case 'excel':
                    return $this->generateExcelInvoice($invoiceData, $options);
                default:
                    throw new \Exception(__('messages.unsupported_format'));
            }
        } catch (\Exception $e) {
            error_log('Invoice generation error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate PDF invoice
     */
    private function generatePdfInvoice($data, $options = [])
    {
        try {
            $html = $this->generateHtmlInvoice($data, $options);
            
            // Configure mPDF settings
            $isRTL = ($data['language'] ?? lang()) === 'ar';
            $config = [
                'mode' => 'utf-8',
                'format' => $options['page_size'] ?? 'A4',
                'default_font_size' => $options['font_size'] ?? 11,
                'default_font' => $isRTL ? 'Arial' : 'Arial',
                'margin_left' => $options['margin_left'] ?? 12,
                'margin_right' => $options['margin_right'] ?? 12,
                'margin_top' => $options['margin_top'] ?? 15,
                'margin_bottom' => $options['margin_bottom'] ?? 15,
                'margin_header' => $options['margin_header'] ?? 8,
                'margin_footer' => $options['margin_footer'] ?? 8,
                'orientation' => $options['orientation'] ?? 'P',
                'directionality' => $isRTL ? 'rtl' : 'ltr',
                'tempDir' => BASE_PATH . '/storage/temp/mpdf/'
            ];
            
            // Create temp directory if not exists
            if (!is_dir($config['tempDir'])) {
                mkdir($config['tempDir'], 0755, true);
            }
            
            $mpdf = new Mpdf($config);
            
            // Set document properties
            $mpdf->SetTitle(__('invoice') . ' #' . $data['invoice_no']);
            $mpdf->SetAuthor($data['company']['name']);
            $mpdf->SetCreator('China Office Accounting System');
            $mpdf->SetSubject(__('invoice') . ' for ' . $data['client']['name']);
            
            // Add header and footer
            if (!isset($options['no_header'])) {
                $mpdf->SetHTMLHeader($this->getInvoiceHeader($data));
            }
            if (!isset($options['no_footer'])) {
                $mpdf->SetHTMLFooter($this->getInvoiceFooter($data));
            }
            
            // Add watermarks based on status
            $this->addInvoiceWatermark($mpdf, $data, $options);
            
            // Add logo if available
            if (file_exists(BASE_PATH . '/public/assets/images/logo.png') && !isset($options['no_logo'])) {
                $mpdf->SetWatermarkImage(BASE_PATH . '/public/assets/images/logo.png', 0.08, 'P');
                $mpdf->showWatermarkImage = true;
            }
            
            // Write HTML content
            $mpdf->WriteHTML($html);
            
            // Generate filename
            $filename = $this->generateInvoiceFilename($data['invoice_no'], 'pdf');
            $filepath = $this->getInvoiceFilePath($filename);
            
            // Save file
            $mpdf->Output($filepath, 'F');
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'download_url' => $this->getDownloadUrl($filename, 'invoices'),
                'size' => filesize($filepath),
                'type' => 'application/pdf'
            ];
            
        } catch (\Exception $e) {
            error_log('PDF Invoice generation error: ' . $e->getMessage());
            throw new \Exception(__('messages.pdf_generation_failed') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Generate HTML invoice
     */
    private function generateHtmlInvoice($data, $options = [])
    {
        $isRTL = ($data['language'] ?? lang()) === 'ar';
        $direction = $isRTL ? 'rtl' : 'ltr';
        $template = $data['template'] ?? 'default';
        
        $html = '<!DOCTYPE html>
<html dir="' . $direction . '">
<head>
    <meta charset="UTF-8">
    <title>' . __('invoice') . ' ' . $data['invoice_no'] . '</title>
    <link rel="stylesheet" href="/assets/css/print.css">
    <style>
        body {
            font-family: ' . ($isRTL ? 'Arial, Tahoma' : 'Arial') . ', sans-serif;
            direction: ' . $direction . ';
        }
        .invoice-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 15mm;
            background: white;
        }
        .invoice-header {
            border-bottom: 2px solid #3498db;
            padding-bottom: 20px;
            margin-bottom: 30px;
            position: relative;
        }
        .company-logo {
            position: absolute;
            top: 0;
            ' . ($isRTL ? 'left' : 'right') . ': 0;
            max-height: 60px;
            max-width: 150px;
        }
        .company-info {
            width: 60%;
            float: ' . ($isRTL ? 'right' : 'left') . ';
        }
        .invoice-info {
            width: 35%;
            float: ' . ($isRTL ? 'left' : 'right') . ';
            text-align: ' . ($isRTL ? 'left' : 'right') . ';
        }
        .invoice-title {
            font-size: 32px;
            color: #3498db;
            margin: 0 0 10px 0;
            font-weight: bold;
        }
        .company-details {
            color: #666;
            font-size: 12px;
            line-height: 1.5;
        }
        .invoice-meta {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .invoice-meta h3 {
            color: #2c3e50;
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        .invoice-meta p {
            margin: 5px 0;
            font-size: 13px;
        }
        .client-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        .client-section h3 {
            color: #2c3e50;
            margin: 0 0 15px 0;
            font-size: 16px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 8px;
        }
        .client-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        .client-detail-item {
            display: flex;
            margin: 5px 0;
        }
        .client-detail-label {
            font-weight: bold;
            color: #495057;
            min-width: 100px;
            margin-' . ($isRTL ? 'left' : 'right') . ': 10px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        .items-table thead th {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            font-weight: bold;
            padding: 15px 12px;
            text-align: ' . ($isRTL ? 'right' : 'left') . ';
            font-size: 13px;
            border: none;
        }
        .items-table tbody td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
        }
        .items-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        .items-table tbody tr:hover {
            background: #e3f2fd;
        }
        .amount-cell {
            text-align: ' . ($isRTL ? 'left' : 'right') . ';
            font-family: "Courier New", monospace;
            font-weight: bold;
        }
        .totals-section {
            float: ' . ($isRTL ? 'left' : 'right') . ';
            width: 45%;
            margin-top: 30px;
        }
        .totals-table {
            width: 100%;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        .totals-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .totals-table .total-label {
            font-weight: bold;
            color: #495057;
            background: #f8f9fa;
        }
        .totals-table .total-amount {
            text-align: ' . ($isRTL ? 'left' : 'right') . ';
            font-family: "Courier New", monospace;
            font-weight: bold;
        }
        .final-total {
            background: linear-gradient(135deg, #2c3e50, #34495e) !important;
            color: white !important;
            font-size: 16px;
            font-weight: bold;
        }
        .payment-info {
            clear: both;
            margin-top: 50px;
            padding: 20px;
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 1px solid #ffc107;
            border-radius: 8px;
        }
        .payment-info h4 {
            color: #856404;
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        .payment-method {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .payment-method h5 {
            color: #2c3e50;
            margin: 0 0 10px 0;
        }
        .notes-section {
            margin-top: 30px;
            padding: 20px;
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
        }
        .invoice-footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #dee2e6;
            text-align: center;
            color: #6c757d;
            font-size: 11px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-unpaid {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status-partial {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header clearfix">';
        
        // Add logo if exists
        if (file_exists(BASE_PATH . '/public/assets/images/logo.png')) {
            $html .= '<img src="/assets/images/logo.png" alt="' . htmlspecialchars($data['company']['name']) . '" class="company-logo">';
        }
        
        $html .= '
            <div class="company-info">
                <h1 class="invoice-title">' . htmlspecialchars($data['company']['name']) . '</h1>
                <div class="company-details">
                    ' . nl2br(htmlspecialchars($data['company']['address'])) . '<br>
                    ' . __('phone') . ': ' . htmlspecialchars($data['company']['phone']) . '<br>
                    ' . __('email') . ': ' . htmlspecialchars($data['company']['email']) . '
                </div>
            </div>
            <div class="invoice-info">
                <div class="invoice-meta">
                    <h3>' . __('invoice') . '</h3>
                    <p><strong>' . __('invoice.invoice_no') . ':</strong> ' . htmlspecialchars($data['invoice_no']) . '</p>
                    <p><strong>' . __('date') . ':</strong> ' . date('F d, Y', strtotime($data['date'])) . '</p>
                    <p><strong>' . __('invoice.due_date') . ':</strong> ' . date('F d, Y', strtotime($data['due_date'])) . '</p>
                    ' . $this->getInvoiceStatusBadge($data) . '
                </div>
            </div>
        </div>
        
        <!-- Client Information -->
        <div class="client-section">
            <h3>' . __('invoice.bill_to') . ':</h3>
            <div class="client-details">
                <div class="client-detail-item">
                    <span class="client-detail-label">' . __('clients.name') . ':</span>
                    <span><strong>' . htmlspecialchars($this->getClientDisplayName($data['client'])) . '</strong></span>
                </div>
                <div class="client-detail-item">
                    <span class="client-detail-label">' . __('clients.client_code') . ':</span>
                    <span>' . htmlspecialchars($data['client']['client_code']) . '</span>
                </div>';
        
        if (!empty($data['client']['contact_person'])) {
            $html .= '
                <div class="client-detail-item">
                    <span class="client-detail-label">' . __('clients.contact_person') . ':</span>
                    <span>' . htmlspecialchars($data['client']['contact_person']) . '</span>
                </div>';
        }
        
        if (!empty($data['client']['phone'])) {
            $html .= '
                <div class="client-detail-item">
                    <span class="client-detail-label">' . __('clients.phone') . ':</span>
                    <span>' . htmlspecialchars($data['client']['phone']) . '</span>
                </div>';
        }
        
        if (!empty($data['client']['email'])) {
            $html .= '
                <div class="client-detail-item">
                    <span class="client-detail-label">' . __('clients.email') . ':</span>
                    <span>' . htmlspecialchars($data['client']['email']) . '</span>
                </div>';
        }
        
        if (!empty($data['client']['address'])) {
            $html .= '
                <div class="client-detail-item">
                    <span class="client-detail-label">' . __('clients.address') . ':</span>
                    <span>' . nl2br(htmlspecialchars($data['client']['address'])) . '</span>
                </div>';
        }
        
        $html .= '
            </div>
        </div>
        
        <!-- Invoice Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%">' . __('transactions.description') . '</th>
                    <th style="width: 15%">' . __('currency') . '</th>
                    <th style="width: 15%">' . __('amount') . '</th>
                    <th style="width: 20%">' . __('total') . '</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($data['items'] as $item) {
            $html .= '
            <tr>
                <td>' . htmlspecialchars($item['description']) . '</td>
                <td>' . htmlspecialchars($item['currency']) . '</td>
                <td class="amount-cell">' . number_format($item['amount'], 2) . '</td>
                <td class="amount-cell">' . $item['currency_symbol'] . ' ' . number_format($item['total'], 2) . '</td>
            </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
        
        <!-- Totals Section -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="total-label">' . __('transactions.subtotal') . ':</td>
                    <td class="total-amount">' . $this->formatMultiCurrencyAmount($data['totals']['subtotal']) . '</td>
                </tr>';
        
        if ($this->hasPayments($data['totals']['payments'])) {
            $html .= '
                <tr>
                    <td class="total-label">' . __('transactions.paid_amount') . ':</td>
                    <td class="total-amount positive">' . $this->formatMultiCurrencyAmount($data['totals']['payments']) . '</td>
                </tr>';
        }
        
        $balanceClass = $this->hasOutstandingBalance($data['totals']['balance']) ? 'negative' : 'positive';
        $html .= '
                <tr class="final-total">
                    <td class="total-label">' . __('transactions.balance_due') . ':</td>
                    <td class="total-amount ' . $balanceClass . '">' . $this->formatMultiCurrencyAmount($data['totals']['balance']) . '</td>
                </tr>
            </table>
        </div>
        
        <div class="clearfix"></div>
        
        <!-- Payment Information -->
        <div class="payment-info">
            <h4>' . __('invoice.payment_terms') . ':</h4>
            <p>' . htmlspecialchars($data['terms']['payment_terms']) . '</p>
            
            <div class="payment-methods">';
        
        if (!empty($data['company']['bank_details'])) {
            $html .= '
                <div class="payment-method">
                    <h5>' . __('invoice.bank_transfer') . ':</h5>
                    <p>' . nl2br(htmlspecialchars($data['company']['bank_details'])) . '</p>
                </div>';
        }
        
        $html .= '
                <div class="payment-method">
                    <h5>' . __('invoice.payment_deadline') . ':</h5>
                    <p>' . date('F d, Y', strtotime($data['due_date'])) . '</p>
                    <p><small>' . htmlspecialchars($data['terms']['late_fee']) . '</small></p>
                </div>
            </div>
        </div>';
        
        // Add notes if provided
        if (!empty($data['notes'])) {
            $html .= '
        <div class="notes-section">
            <h4>' . __('notes') . ':</h4>
            <p>' . nl2br(htmlspecialchars($data['notes'])) . '</p>
        </div>';
        }
        
        $html .= '
        
        <!-- Invoice Footer -->
        <div class="invoice-footer">
            <p><strong>' . __('invoice.thank_you') . '</strong></p>
            <p>' . __('invoice.computer_generated') . '</p>
            <hr style="border: 1px solid #dee2e6; margin: 15px 0;">
            <p>' . __('invoice.generated_on') . ' ' . date('F d, Y \a\t H:i:s') . '</p>
            <p>' . __('invoice.generated_by') . ': ' . htmlspecialchars($_SESSION['user_name'] ?? __('system')) . '</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber()
    {
        $year = date('Y');
        $month = date('m');
        
        $sql = "SELECT MAX(CAST(SUBSTRING(invoice_no, 10) AS UNSIGNED)) as max_no 
                FROM transactions 
                WHERE invoice_no LIKE ?";
        
        $stmt = $this->db->query($sql, ["INV-$year$month-%"]);
        $result = $stmt->fetch();
        
        $nextNo = ($result['max_no'] ?? 0) + 1;
        return sprintf("INV-%s%s-%04d", $year, $month, $nextNo);
    }
    
    /**
     * Get invoice items from transaction
     */
    private function getInvoiceItems($transaction)
    {
        $items = [];
        
        // Add goods amount
        if ($transaction['goods_amount_rmb'] > 0) {
            $items[] = [
                'description' => 'Goods Amount',
                'currency' => 'RMB',
                'currency_symbol' => '¥',
                'amount' => $transaction['goods_amount_rmb'],
                'total' => $transaction['goods_amount_rmb']
            ];
        }
        
        // Add commission
        if ($transaction['commission_rmb'] > 0) {
            $items[] = [
                'description' => 'Commission',
                'currency' => 'RMB',
                'currency_symbol' => '¥',
                'amount' => $transaction['commission_rmb'],
                'total' => $transaction['commission_rmb']
            ];
        }
        
        // Add shipping
        if ($transaction['shipping_usd'] > 0) {
            $items[] = [
                'description' => 'Shipping Charges',
                'currency' => 'USD',
                'currency_symbol' => '$',
                'amount' => $transaction['shipping_usd'],
                'total' => $transaction['shipping_usd']
            ];
        }
        
        return $items;
    }
    
    /**
     * Calculate invoice totals
     */
    private function calculateTotals($transaction)
    {
        return [
            'subtotal' => [
                'RMB' => $transaction['total_amount_rmb'],
                'USD' => $transaction['shipping_usd'],
                'SDG' => 0,
                'AED' => 0
            ],
            'payments' => [
                'RMB' => $transaction['payment_rmb'],
                'USD' => $transaction['payment_usd'],
                'SDG' => $transaction['payment_sdg'],
                'AED' => $transaction['payment_aed']
            ],
            'balance' => [
                'RMB' => $transaction['balance_rmb'],
                'USD' => $transaction['balance_usd'],
                'SDG' => $transaction['balance_sdg'],
                'AED' => $transaction['balance_aed']
            ]
        ];
    }
    
    /**
     * Format multi-currency amount
     */
    private function formatMultiCurrencyAmount($amounts)
    {
        $formatted = [];
        $symbols = [
            'RMB' => '¥',
            'USD' => '$',
            'SDG' => 'SDG',
            'AED' => 'AED'
        ];
        
        foreach ($amounts as $currency => $amount) {
            if ($amount > 0) {
                $formatted[] = $symbols[$currency] . ' ' . number_format($amount, 2);
            }
        }
        
        return implode(' / ', $formatted) ?: '0.00';
    }
    
    /**
     * Get client display name based on language
     */
    private function getClientDisplayName($client)
    {
        if (lang() === 'ar' && !empty($client['name_ar'])) {
            return $client['name_ar'];
        }
        return $client['name'];
    }
    
    /**
     * Get invoice status badge HTML
     */
    private function getInvoiceStatusBadge($data)
    {
        $status = $data['transaction']['status'];
        $hasBalance = $this->hasOutstandingBalance($data['totals']['balance']);
        
        if ($status !== 'approved') {
            $class = 'status-unpaid';
            $text = __('transactions.pending');
        } elseif ($hasBalance) {
            $class = 'status-unpaid';
            $text = __('messages.unpaid');
        } else {
            $class = 'status-paid';
            $text = __('messages.paid');
        }
        
        return '<span class="status-badge ' . $class . '">' . strtoupper($text) . '</span>';
    }
    
    /**
     * Check if there are any payments
     */
    private function hasPayments($payments)
    {
        foreach ($payments as $currency => $amount) {
            if ($amount > 0) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get company settings
     */
    private function getCompanySettings()
    {
        $sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        $settings = [
            'name' => 'China Office Accounting System',
            'address' => '',
            'phone' => '',
            'email' => '',
            'bank_details' => ''
        ];
        
        foreach ($results as $row) {
            $key = str_replace('company_', '', $row['setting_key']);
            $settings[$key] = $row['setting_value'];
        }
        
        return $settings;
    }
    
    /**
     * Generate receipt for payment
     */
    public function generateReceipt($paymentId, $format = 'pdf', $options = [])
    {
        try {
            // Get payment details
            $payment = $this->getPaymentDetails($paymentId);
            if (!$payment) {
                throw new \Exception(__('messages.payment_not_found'));
            }
            
            // Get related transaction and client
            $transaction = $this->transactionModel->getWithDetails($payment['transaction_id']);
            $client = $this->clientModel->find($payment['client_id']);
            
            // Prepare receipt data
            $receiptData = [
                'receipt_no' => $payment['receipt_no'] ?? $this->generateReceiptNumber(),
                'date' => $payment['payment_date'] ?? date('Y-m-d'),
                'payment' => $payment,
                'transaction' => $transaction,
                'client' => $client,
                'company' => $this->getCompanySettings(),
                'currency_symbols' => $this->getCurrencySymbols(),
                'language' => $options['language'] ?? lang()
            ];
            
            switch ($format) {
                case 'pdf':
                    return $this->generatePdfReceipt($receiptData, $options);
                case 'html':
                    return $this->generateHtmlReceipt($receiptData, $options);
                default:
                    throw new \Exception(__('messages.unsupported_format'));
            }
        } catch (\Exception $e) {
            error_log('Receipt generation error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate Excel invoice
     */
    private function generateExcelInvoice($data, $options = [])
    {
        // Use ExcelExportService for Excel generation
        $excelService = new \App\Services\ExcelExportService();
        
        // Convert invoice data to Excel format
        $excelData = [
            'invoice_no' => $data['invoice_no'],
            'client' => $data['client'],
            'date' => $data['date'],
            'items' => $data['items'],
            'totals' => $data['totals'],
            'company' => $data['company']
        ];
        
        return $excelService->exportInvoice($excelData, $options);
    }
    
    /**
     * Add watermark to invoice PDF
     */
    private function addInvoiceWatermark($mpdf, $data, $options)
    {
        $status = $data['transaction']['status'];
        $hasBalance = $this->hasOutstandingBalance($data['totals']['balance']);
        
        if (isset($options['watermark'])) {
            $watermarkText = $options['watermark'];
        } elseif ($status !== 'approved') {
            $watermarkText = strtoupper(__('transactions.pending'));
        } elseif ($hasBalance) {
            $watermarkText = strtoupper(__('messages.unpaid'));
        } elseif (isset($options['show_paid_watermark']) && $options['show_paid_watermark']) {
            $watermarkText = strtoupper(__('messages.paid'));
        } else {
            return; // No watermark needed
        }
        
        $mpdf->SetWatermarkText($watermarkText);
        $mpdf->showWatermarkText = true;
        $mpdf->watermark_font = 'Arial';
        $mpdf->watermarkTextAlpha = $options['watermark_opacity'] ?? 0.1;
    }
    
    /**
     * Extract client data from transaction
     */
    private function extractClientData($transaction)
    {
        return [
            'id' => $transaction['client_id'],
            'name' => $transaction['client_name'] ?? $transaction['name'],
            'name_ar' => $transaction['client_name_ar'] ?? $transaction['name_ar'] ?? '',
            'client_code' => $transaction['client_code'],
            'phone' => $transaction['client_phone'] ?? $transaction['phone'] ?? '',
            'email' => $transaction['client_email'] ?? $transaction['email'] ?? '',
            'address' => $transaction['client_address'] ?? $transaction['address'] ?? '',
            'contact_person' => $transaction['contact_person'] ?? ''
        ];
    }
    
    /**
     * Get currency symbols
     */
    private function getCurrencySymbols()
    {
        return [
            'RMB' => '¥',
            'USD' => '$',
            'SDG' => 'SDG',
            'AED' => 'AED',
            'EUR' => '€',
            'GBP' => '£',
            'SAR' => 'SAR'
        ];
    }
    
    /**
     * Get default payment terms
     */
    private function getDefaultTerms()
    {
        return [
            'payment_terms' => __('invoice.payment_terms_default'),
            'late_fee' => __('invoice.late_fee_terms'),
            'bank_details' => $this->getCompanySettings()['bank_details'] ?? ''
        ];
    }
    
    /**
     * Check if there's outstanding balance
     */
    private function hasOutstandingBalance($balances)
    {
        foreach ($balances as $currency => $amount) {
            if ($amount > 0) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Generate invoice filename
     */
    private function generateInvoiceFilename($invoiceNo, $extension = 'pdf')
    {
        $sanitizedNo = preg_replace('/[^A-Za-z0-9\-_]/', '_', $invoiceNo);
        return "invoice_{$sanitizedNo}_" . date('YmdHis') . ".{$extension}";
    }
    
    /**
     * Get invoice file path
     */
    private function getInvoiceFilePath($filename)
    {
        $dir = BASE_PATH . '/storage/invoices/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . $filename;
    }
    
    /**
     * Get download URL for file
     */
    private function getDownloadUrl($filename, $type = 'invoices')
    {
        return "/download/{$type}/" . urlencode($filename);
    }
    
    /**
     * Get invoice header for PDF
     */
    private function getInvoiceHeader($data)
    {
        $isRTL = ($data['language'] ?? lang()) === 'ar';
        $direction = $isRTL ? 'rtl' : 'ltr';
        
        return '
        <table width="100%" style="direction: ' . $direction . '; font-family: Arial; font-size: 10px;">
            <tr>
                <td width="50%" style="text-align: ' . ($isRTL ? 'right' : 'left') . ';">
                    <strong>' . htmlspecialchars($data['company']['name']) . '</strong>
                </td>
                <td width="50%" style="text-align: ' . ($isRTL ? 'left' : 'right') . ';">
                    ' . __('invoice.page') . ': {PAGENO}/{nbpg} | ' . __('date') . ': {DATE j-m-Y}
                </td>
            </tr>
        </table>
        <hr style="border: 1px solid #3498db; margin: 5px 0;">';
    }
    
    /**
     * Get invoice footer for PDF
     */
    private function getInvoiceFooter($data)
    {
        $isRTL = ($data['language'] ?? lang()) === 'ar';
        $direction = $isRTL ? 'rtl' : 'ltr';
        
        return '
        <hr style="border: 1px solid #ddd; margin: 5px 0;">
        <table width="100%" style="direction: ' . $direction . '; font-family: Arial; font-size: 8px; color: #666;">
            <tr>
                <td width="50%" style="text-align: ' . ($isRTL ? 'right' : 'left') . ';">
                    ' . htmlspecialchars($data['company']['name']) . ' - ' . __('invoice.confidential') . '
                </td>
                <td width="50%" style="text-align: ' . ($isRTL ? 'left' : 'right') . ';">
                    ' . __('invoice.generated') . ': {DATE j-m-Y H:i:s} | ' . htmlspecialchars($_SESSION['user_name'] ?? '-') . '
                </td>
            </tr>
        </table>';
    }
    
    /**
     * Get payment details
     */
    private function getPaymentDetails($paymentId)
    {
        $sql = "SELECT * FROM payments WHERE id = ?";
        $stmt = $this->db->query($sql, [$paymentId]);
        return $stmt->fetch();
    }
    
    /**
     * Generate receipt number
     */
    private function generateReceiptNumber()
    {
        $year = date('Y');
        $month = date('m');
        
        $sql = "SELECT MAX(CAST(SUBSTRING(receipt_no, 10) AS UNSIGNED)) as max_no 
                FROM payments 
                WHERE receipt_no LIKE ?";
        
        $stmt = $this->db->query($sql, ["REC-$year$month-%"]);
        $result = $stmt->fetch();
        
        $nextNo = ($result['max_no'] ?? 0) + 1;
        return sprintf("REC-%s%s-%04d", $year, $month, $nextNo);
    }
    
    /**
     * Generate PDF receipt
     */
    private function generatePdfReceipt($data, $options = [])
    {
        // Implementation similar to generatePdfInvoice but for receipts
        $html = $this->generateHtmlReceipt($data, $options);
        
        $isRTL = ($data['language'] ?? lang()) === 'ar';
        $config = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 11,
            'default_font' => 'Arial',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 15,
            'orientation' => 'P',
            'directionality' => $isRTL ? 'rtl' : 'ltr'
        ];
        
        $mpdf = new Mpdf($config);
        $mpdf->SetTitle(__('receipt') . ' #' . $data['receipt_no']);
        $mpdf->WriteHTML($html);
        
        $filename = "receipt_{$data['receipt_no']}_" . date('YmdHis') . '.pdf';
        $filepath = BASE_PATH . '/storage/receipts/' . $filename;
        
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        $mpdf->Output($filepath, 'F');
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'download_url' => $this->getDownloadUrl($filename, 'receipts')
        ];
    }
    
    /**
     * Generate HTML receipt
     */
    private function generateHtmlReceipt($data, $options = [])
    {
        $isRTL = ($data['language'] ?? lang()) === 'ar';
        $direction = $isRTL ? 'rtl' : 'ltr';
        
        $html = '<!DOCTYPE html>
<html dir="' . $direction . '">
<head>
    <meta charset="UTF-8">
    <title>' . __('receipt') . ' ' . $data['receipt_no'] . '</title>
    <link rel="stylesheet" href="/assets/css/print.css">
</head>
<body>
    <div class="print-container">
        <div class="print-header">
            <div class="company-name">' . htmlspecialchars($data['company']['name']) . '</div>
            <div class="report-title">' . __('receipt') . '</div>
            <div class="report-subtitle">' . __('receipt.payment_confirmation') . '</div>
        </div>
        
        <div class="client-info">
            <h3>' . __('receipt.received_from') . ':</h3>
            <p><strong>' . htmlspecialchars($data['client']['name']) . '</strong></p>
            <p>' . __('clients.client_code') . ': ' . htmlspecialchars($data['client']['client_code']) . '</p>
        </div>
        
        <div class="payment-details">
            <h3>' . __('receipt.payment_details') . ':</h3>
            <table class="table">
                <tr>
                    <th>' . __('receipt.receipt_no') . '</th>
                    <td>' . htmlspecialchars($data['receipt_no']) . '</td>
                </tr>
                <tr>
                    <th>' . __('date') . '</th>
                    <td>' . date('Y-m-d', strtotime($data['date'])) . '</td>
                </tr>
                <tr>
                    <th>' . __('amount') . '</th>
                    <td class="amount">' . $this->formatPaymentAmount($data['payment']) . '</td>
                </tr>
                <tr>
                    <th>' . __('transactions.payment_method') . '</th>
                    <td>' . htmlspecialchars($data['payment']['payment_method'] ?? __('payment.cash')) . '</td>
                </tr>
            </table>
        </div>
        
        <div class="receipt-footer">
            <p>' . __('receipt.thank_you') . '</p>
            <p><em>' . __('receipt.computer_generated') . '</em></p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Format payment amount for display
     */
    private function formatPaymentAmount($payment)
    {
        $currencies = $this->getCurrencySymbols();
        $amounts = [];
        
        foreach (['rmb', 'usd', 'sdg', 'aed'] as $currency) {
            $amount = $payment['amount_' . $currency] ?? 0;
            if ($amount > 0) {
                $symbol = $currencies[strtoupper($currency)];
                $amounts[] = $symbol . ' ' . number_format($amount, 2);
            }
        }
        
        return implode(' + ', $amounts) ?: '0.00';
    }
}