<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware\Auth;
use App\Models\ExchangeRateManager;

class CashboxCurrencyController extends Controller
{
    private ExchangeRateManager $rates;

    public function __construct()
    {
        parent::__construct();
        Auth::check();
        $this->rates = new ExchangeRateManager();
    }

    public function index()
    {
        $this->view('cashbox/currency_conversion', [
            'title' => __('cashbox.title'),
            'current_rates' => $this->rates->getAllCurrentRates()
        ]);
    }

    public function convert()
    {
        $amount = floatval($_POST['amount'] ?? 0);
        $from = $_POST['from'] ?? 'RMB';
        $to = $_POST['to'] ?? 'USD';
        if ($amount <= 0) {
            return $this->json(['success' => false, 'error' => 'Invalid amount']);
        }
        $result = $this->rates->convertCurrency($amount, $from, $to);
        return $this->json(['success' => true, 'data' => $result]);
    }
}