<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ExchangeRateManager;
use App\Core\Middleware\Auth;

class ExchangeRateController extends Controller
{
    private ExchangeRateManager $exchangeRateManager;
    
    public function __construct()
    {
        parent::__construct();
        Auth::check();
        $this->exchangeRateManager = new ExchangeRateManager();
    }
    
    public function index()
    {
        $data = [
            'title' => __('Exchange Rate Management'),
            'current_rates' => $this->exchangeRateManager->getAllCurrentRates(),
            'conversion_summary' => $this->exchangeRateManager->getConversionSummary(),
            'supported_currencies' => ['RMB', 'USD', 'SDG', 'AED']
        ];
        $majorPairs = ['USD_RMB', 'SDG_RMB', 'AED_RMB'];
        $volatility = [];
        foreach ($majorPairs as $pair) {
            $volatility[$pair] = $this->exchangeRateManager->calculateVolatility($pair, 30);
        }
        $data['volatility_analysis'] = $volatility;
        return $this->view('exchange_rates/index', $data);
    }
    
    public function getCurrentRate()
    {
        $fromCurrency = $_GET['from'] ?? '';
        $toCurrency = $_GET['to'] ?? '';
        if (empty($fromCurrency) || empty($toCurrency)) {
            return $this->json(['success' => false, 'error' => 'Missing currency parameters']);
        }
        $rateData = $this->exchangeRateManager->getCurrentRate($fromCurrency, $toCurrency);
        return $this->json(['success' => true, 'data' => $rateData]);
    }
    
    public function convertCurrency()
    {
        $amount = floatval($_POST['amount'] ?? 0);
        $fromCurrency = $_POST['from'] ?? '';
        $toCurrency = $_POST['to'] ?? '';
        if ($amount <= 0 || empty($fromCurrency) || empty($toCurrency)) {
            return $this->json(['success' => false, 'error' => 'Missing or invalid parameters']);
        }
        $conversion = $this->exchangeRateManager->convertCurrency($amount, $fromCurrency, $toCurrency);
        return $this->json(['success' => true, 'data' => $conversion]);
    }
    
    public function updateRate()
    {
        $currencyPair = $_POST['currency_pair'] ?? '';
        $rate = floatval($_POST['rate'] ?? 0);
        if (empty($currencyPair) || $rate <= 0) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters']);
        }
        $result = $this->exchangeRateManager->updateExchangeRate($currencyPair, $rate, 'manual');
        return $this->json($result);
    }
    
    public function autoUpdate()
    {
        $result = $this->exchangeRateManager->autoUpdateRates();
        return $this->json($result);
    }
    
    public function getRateHistory()
    {
        $currencyPair = $_GET['pair'] ?? '';
        $days = intval($_GET['days'] ?? 30);
        if (empty($currencyPair)) {
            return $this->json(['success' => false, 'error' => 'Missing currency pair']);
        }
        $history = $this->exchangeRateManager->getRateHistory($currencyPair, $days);
        $volatility = $this->exchangeRateManager->calculateVolatility($currencyPair, $days);
        $impact = $this->exchangeRateManager->getFinancialImpact($currencyPair, min($days, 30));
        return $this->json(['success' => true, 'data' => [
            'history' => $history,
            'volatility' => $volatility,
            'financial_impact' => $impact
        ]]);
    }
    
    public function initializeDefaults()
    {
        $result = $this->exchangeRateManager->initializeDefaultRates();
        return $this->json($result);
    }
    
    public function calculator()
    {
        $data = [
            'title' => __('Currency Converter'),
            'current_rates' => $this->exchangeRateManager->getAllCurrentRates(),
            'supported_currencies' => ['RMB', 'USD', 'SDG', 'AED']
        ];
        return $this->view('exchange_rates/calculator', $data);
    }
    
    public function getWidgetData()
    {
        $majorPairs = ['USD_RMB', 'SDG_RMB', 'AED_RMB', 'USD_SDG', 'USD_AED'];
        $widgetData = [];
        foreach ($majorPairs as $pair) {
            $rateData = $this->exchangeRateManager->getCurrentRate(explode('_', $pair)[0], explode('_', $pair)[1]);
            $impact = $this->exchangeRateManager->getFinancialImpact($pair, 1);
            $widgetData[$pair] = [
                'rate' => $rateData['rate'],
                'last_updated' => $rateData['last_updated'],
                'status' => $rateData['status'],
                'change_percent' => $impact['change_percent'],
                'trend' => $impact['impact']
            ];
        }
        return $this->json(['success' => true, 'data' => $widgetData, 'last_updated' => date('Y-m-d H:i:s')]);
    }
    
    public function generateReport()
    {
        $period = $_GET['period'] ?? 'monthly';
        $format = $_GET['format'] ?? 'html';
        $days = match ($period) {
            'weekly' => 7,
            'quarterly' => 90,
            'yearly' => 365,
            default => 30,
        };
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
                'current_rate' => $this->exchangeRateManager->getCurrentRate(explode('_', $pair)[0], explode('_', $pair)[1])
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
            return $this->json(['success' => true, 'data' => $data]);
        }
        return $this->view('exchange_rates/report', $data);
    }
}