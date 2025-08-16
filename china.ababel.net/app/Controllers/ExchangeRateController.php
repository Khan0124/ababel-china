<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ExchangeRateManager;
use App\Core\Middleware\Auth;
use App\Core\Security\CSRF;

/**
 * Exchange Rate Management Controller
 * Handles currency conversion and rate management
 */
class ExchangeRateController extends Controller
{
    private $exchangeRateManager;
    
    public function __construct()
    {
        parent::__construct();
        $this->exchangeRateManager = new ExchangeRateManager();
        
        // Check authentication for all methods
        Auth::check();
    }
    
    /**
     * Display exchange rate management dashboard
     */
    public function index()
    {
        $data = [
            'title' => __('Exchange Rate Management'),
            'current_rates' => $this->exchangeRateManager->getAllCurrentRates(),
            'conversion_summary' => $this->exchangeRateManager->getConversionSummary(),
            'supported_currencies' => ['RMB', 'USD', 'SDG', 'AED']
        ];
        
        // Calculate volatility for major pairs
        $majorPairs = ['USD_RMB', 'SDG_RMB', 'AED_RMB'];
        $volatility = [];
        
        foreach ($majorPairs as $pair) {
            $volatility[$pair] = $this->exchangeRateManager->calculateVolatility($pair, 30);
        }
        
        $data['volatility_analysis'] = $volatility;
        
        return $this->view('exchange_rates/index', $data);
    }
    
    /**
     * Get current exchange rate via AJAX
     */
    public function getCurrentRate()
    {
        $fromCurrency = $_GET['from'] ?? '';
        $toCurrency = $_GET['to'] ?? '';
        
        if (empty($fromCurrency) || empty($toCurrency)) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Missing currency parameters'
            ]);
        }
        
        $rateData = $this->exchangeRateManager->getCurrentRate($fromCurrency, $toCurrency);
        
        return $this->jsonResponse([
            'success' => true,
            'data' => $rateData
        ]);
    }
    
    /**
     * Convert currency amount via AJAX
     */
    public function convertCurrency()
    {
        $amount = $_POST['amount'] ?? 0;
        $fromCurrency = $_POST['from'] ?? '';
        $toCurrency = $_POST['to'] ?? '';
        
        if (empty($amount) || empty($fromCurrency) || empty($toCurrency)) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Missing conversion parameters'
            ]);
        }
        
        $amount = floatval($amount);
        if ($amount <= 0) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid amount'
            ]);
        }
        
        $conversion = $this->exchangeRateManager->convertCurrency($amount, $fromCurrency, $toCurrency);
        
        return $this->jsonResponse([
            'success' => true,
            'data' => $conversion
        ]);
    }
    
    /**
     * Update exchange rate manually
     */
    public function updateRate()
    {
        if (!isset($_POST['_csrf_token']) || !CSRF::verify($_POST['_csrf_token'])) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid CSRF token'
            ]);
        }
        
        $currencyPair = $_POST['currency_pair'] ?? '';
        $rate = $_POST['rate'] ?? 0;
        
        if (empty($currencyPair) || empty($rate)) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Missing required parameters'
            ]);
        }
        
        $rate = floatval($rate);
        if ($rate <= 0) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid exchange rate'
            ]);
        }
        
        $result = $this->exchangeRateManager->updateExchangeRate($currencyPair, $rate, 'manual');
        
        return $this->jsonResponse($result);
    }
    
    /**
     * Auto-update all exchange rates
     */
    public function autoUpdate()
    {
        // Handle both JSON and form data
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if (!isset($input['_csrf_token']) || !CSRF::verify($input['_csrf_token'])) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid CSRF token'
            ]);
        }
        
        $result = $this->exchangeRateManager->autoUpdateRates();
        
        return $this->jsonResponse($result);
    }
    
    /**
     * Get rate history for a currency pair
     */
    public function getRateHistory()
    {
        $currencyPair = $_GET['pair'] ?? '';
        $days = intval($_GET['days'] ?? 30);
        
        if (empty($currencyPair)) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Missing currency pair'
            ]);
        }
        
        $history = $this->exchangeRateManager->getRateHistory($currencyPair, $days);
        $volatility = $this->exchangeRateManager->calculateVolatility($currencyPair, $days);
        $impact = $this->exchangeRateManager->getFinancialImpact($currencyPair, min($days, 30));
        
        return $this->jsonResponse([
            'success' => true,
            'data' => [
                'history' => $history,
                'volatility' => $volatility,
                'financial_impact' => $impact
            ]
        ]);
    }
    
    /**
     * Initialize default exchange rates
     */
    public function initializeDefaults()
    {
        // Handle both JSON and form data
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if (!isset($input['_csrf_token']) || !CSRF::verify($input['_csrf_token'])) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid CSRF token'
            ]);
        }
        
        $result = $this->exchangeRateManager->initializeDefaultRates();
        
        return $this->jsonResponse($result);
    }
    
    /**
     * Get conversion calculator view
     */
    public function calculator()
    {
        $data = [
            'title' => __('Currency Converter'),
            'current_rates' => $this->exchangeRateManager->getAllCurrentRates(),
            'supported_currencies' => ['RMB', 'USD', 'SDG', 'AED']
        ];
        
        return $this->view('exchange_rates/calculator', $data);
    }
    
    /**
     * Get exchange rate widget data for dashboard
     */
    public function getWidgetData()
    {
        $majorPairs = ['USD_RMB', 'SDG_RMB', 'AED_RMB', 'USD_SDG', 'USD_AED'];
        $widgetData = [];
        
        foreach ($majorPairs as $pair) {
            $rateData = $this->exchangeRateManager->getCurrentRate(
                explode('_', $pair)[0], 
                explode('_', $pair)[1]
            );
            
            $impact = $this->exchangeRateManager->getFinancialImpact($pair, 1);
            
            $widgetData[$pair] = [
                'rate' => $rateData['rate'],
                'last_updated' => $rateData['last_updated'],
                'status' => $rateData['status'],
                'change_percent' => $impact['change_percent'],
                'trend' => $impact['impact']
            ];
        }
        
        return $this->jsonResponse([
            'success' => true,
            'data' => $widgetData,
            'last_updated' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Generate exchange rate report
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
        
        $majorPairs = ['USD_RMB', 'SDG_RMB', 'AED_RMB', 'USD_SDG', 'USD_AED'];
        $reportData = [];
        
        foreach ($majorPairs as $pair) {
            $history = $this->exchangeRateManager->getRateHistory($pair, $days);
            $volatility = $this->exchangeRateManager->calculateVolatility($pair, $days);
            $impact = $this->exchangeRateManager->getFinancialImpact($pair, $days);
            
            $reportData[$pair] = [
                'history' => $history,
                'volatility' => $volatility,
                'impact' => $impact,
                'current_rate' => $this->exchangeRateManager->getCurrentRate(
                    explode('_', $pair)[0], 
                    explode('_', $pair)[1]
                )
            ];
        }
        
        $data = [
            'title' => __('Exchange Rate Report') . ' - ' . ucfirst($period),
            'period' => $period,
            'days' => $days,
            'report_data' => $reportData,
            'generated_at' => date('Y-m-d H:i:s'),
            'conversion_summary' => $this->exchangeRateManager->getConversionSummary($days)
        ];
        
        if ($format === 'json') {
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        }
        
        return $this->view('exchange_rates/report', $data);
    }
}