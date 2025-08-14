<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware\Auth;
use App\Models\CashboxCurrencyConverter;

class CashboxCurrencyController extends Controller
{
    private CashboxCurrencyConverter $converter;

    public function __construct()
    {
        parent::__construct();
        Auth::check();
        $this->converter = new CashboxCurrencyConverter();
    }

    public function index()
    {
        return $this->view('cashbox/currency_conversion', [
            'title' => __('cashbox.title'),
            'cashbox_balances' => $this->converter->getAllCashboxBalances(),
            'conversion_history' => $this->converter->getConversionHistory(10),
            'conversion_summary' => $this->converter->getConversionSummary(7),
            'supported_currencies' => ['RMB', 'USD', 'SDG', 'AED'],
        ]);
    }

    public function getConversionPreview()
    {
        $fromCurrency = $_GET['from'] ?? '';
        $toCurrency = $_GET['to'] ?? '';
        $amount = floatval($_GET['amount'] ?? 0);
        if (empty($fromCurrency) || empty($toCurrency) || $amount <= 0) {
            return $this->json(['success' => false, 'error' => 'Missing or invalid parameters']);
        }
        $preview = $this->converter->getConversionPreview($fromCurrency, $toCurrency, $amount);
        return $this->json($preview);
    }

    public function executeConversion()
    {
        $fromCurrency = $_POST['from_currency'] ?? '';
        $toCurrency = $_POST['to_currency'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $description = $_POST['description'] ?? '';
        if (empty($fromCurrency) || empty($toCurrency) || $amount <= 0) {
            return $this->json(['success' => false, 'error' => 'Please provide valid conversion details']);
        }
        $result = $this->converter->convertCashboxCurrency($fromCurrency, $toCurrency, $amount, $description);
        return $this->json($result);
    }

    public function getConversionHistory()
    {
        $limit = intval($_GET['limit'] ?? 20);
        $history = $this->converter->getConversionHistory($limit);
        return $this->json(['success' => true, 'data' => $history]);
    }

    public function getCashboxBalances()
    {
        $balances = $this->converter->getAllCashboxBalances();
        return $this->json(['success' => true, 'data' => $balances]);
    }

    public function generateReport()
    {
        $period = $_GET['period'] ?? 'monthly';
        $format = $_GET['format'] ?? 'html';
        $days = 30;
        switch ($period) {
            case 'weekly': $days = 7; break;
            case 'quarterly': $days = 90; break;
            case 'yearly': $days = 365; break;
        }
        $data = [
            'title' => 'Cashbox Currency Conversion Report - ' . ucfirst($period),
            'period' => $period,
            'days' => $days,
            'conversion_history' => $this->converter->getConversionHistory(100),
            'conversion_summary' => $this->converter->getConversionSummary($days),
            'current_balances' => $this->converter->getAllCashboxBalances(),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        if ($format === 'json') {
            return $this->json(['success' => true, 'data' => $data]);
        }
        return $this->view('cashbox/conversion_report', $data);
    }

    public function validateConversion()
    {
        $fromCurrency = $_GET['from'] ?? '';
        $toCurrency = $_GET['to'] ?? '';
        $amount = floatval($_GET['amount'] ?? 0);
        $validation = $this->converter->validateConversionRequest($fromCurrency, $toCurrency, $amount);
        return $this->json(['success' => $validation['valid'], 'errors' => $validation['errors'] ?? [], 'valid' => $validation['valid']]);
    }
}