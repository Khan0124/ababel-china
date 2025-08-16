<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\CashboxCurrencyConverter;
use App\Core\Security\CSRF;
use App\Core\Middleware\Auth;

/**
 * Cashbox Currency Conversion Controller
 * Handles currency exchanges within the cashbox system
 */
class CashboxCurrencyController extends Controller
{
    private $converter;
    
    public function __construct()
    {
        parent::__construct();
        $this->converter = new CashboxCurrencyConverter();
        
        // Check authentication for all methods
        Auth::check();
    }
    
    /**
     * Display currency conversion interface
     */
    public function index()
    {
        $data = [
            'title' => __('Cashbox Currency Exchange'),
            'cashbox_balances' => $this->converter->getAllCashboxBalances(),
            'conversion_history' => $this->converter->getConversionHistory(10),
            'conversion_summary' => $this->converter->getConversionSummary(7),
            'supported_currencies' => ['RMB', 'USD', 'SDG', 'AED']
        ];
        
        return $this->view('cashbox/currency_conversion', $data);
    }
    
    /**
     * Get conversion preview (AJAX)
     */
    public function getConversionPreview()
    {
        $fromCurrency = $_GET['from'] ?? '';
        $toCurrency = $_GET['to'] ?? '';
        $amount = floatval($_GET['amount'] ?? 0);
        
        if (empty($fromCurrency) || empty($toCurrency) || $amount <= 0) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Missing or invalid parameters'
            ]);
        }
        
        $preview = $this->converter->getConversionPreview($fromCurrency, $toCurrency, $amount);
        return $this->jsonResponse($preview);
    }
    
    /**
     * Execute currency conversion
     */
    public function executeConversion()
    {
        // CSRF validation
        if (!isset($_POST['_csrf_token']) || !CSRF::verify($_POST['_csrf_token'])) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid security token'
            ]);
        }
        
        $fromCurrency = $_POST['from_currency'] ?? '';
        $toCurrency = $_POST['to_currency'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $description = $_POST['description'] ?? '';
        
        // Validate inputs
        if (empty($fromCurrency) || empty($toCurrency) || $amount <= 0) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Please provide valid conversion details'
            ]);
        }
        
        // Execute conversion
        $result = $this->converter->convertCashboxCurrency(
            $fromCurrency, 
            $toCurrency, 
            $amount, 
            $description
        );
        
        // Log activity if successful
        if ($result['success']) {
            $this->logActivity(
                'currency_conversion',
                "Converted {$amount} {$fromCurrency} to {$result['conversion_data']['converted_amount']} {$toCurrency}",
                [
                    'from_currency' => $fromCurrency,
                    'to_currency' => $toCurrency,
                    'amount' => $amount,
                    'converted_amount' => $result['conversion_data']['converted_amount'],
                    'exchange_rate' => $result['conversion_data']['exchange_rate']
                ]
            );
        }
        
        return $this->jsonResponse($result);
    }
    
    /**
     * Get conversion history (AJAX)
     */
    public function getConversionHistory()
    {
        $limit = intval($_GET['limit'] ?? 20);
        $history = $this->converter->getConversionHistory($limit);
        
        return $this->jsonResponse([
            'success' => true,
            'data' => $history
        ]);
    }
    
    /**
     * Get cashbox balances (AJAX)
     */
    public function getCashboxBalances()
    {
        $balances = $this->converter->getAllCashboxBalances();
        
        return $this->jsonResponse([
            'success' => true,
            'data' => $balances
        ]);
    }
    
    /**
     * Generate conversion report
     */
    public function generateReport()
    {
        $period = $_GET['period'] ?? 'monthly';
        $format = $_GET['format'] ?? 'html';
        
        $days = 30;
        switch ($period) {
            case 'weekly':
                $days = 7;
                break;
            case 'quarterly':
                $days = 90;
                break;
            case 'yearly':
                $days = 365;
                break;
        }
        
        $data = [
            'title' => 'Cashbox Currency Conversion Report - ' . ucfirst($period),
            'period' => $period,
            'days' => $days,
            'conversion_history' => $this->converter->getConversionHistory(100),
            'conversion_summary' => $this->converter->getConversionSummary($days),
            'current_balances' => $this->converter->getAllCashboxBalances(),
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($format === 'json') {
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        }
        
        return $this->view('cashbox/conversion_report', $data);
    }
    
    /**
     * Validate conversion parameters (AJAX)
     */
    public function validateConversion()
    {
        $fromCurrency = $_GET['from'] ?? '';
        $toCurrency = $_GET['to'] ?? '';
        $amount = floatval($_GET['amount'] ?? 0);
        
        $validation = $this->converter->validateConversionRequest($fromCurrency, $toCurrency, $amount);
        
        return $this->jsonResponse([
            'success' => $validation['valid'],
            'errors' => $validation['errors'] ?? [],
            'valid' => $validation['valid']
        ]);
    }
}