<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ExchangeRateManager;
use App\Core\Middleware\Auth;
use PDO;

/**
 * Calculator Controller - Clean implementation for currency calculator
 */
class CalculatorController extends Controller
{
    private $pdo;
    private $exchangeRateManager;
    
    public function __construct()
    {
        parent::__construct();
        
        // Direct database connection for reliability
        $this->pdo = new PDO(
            'mysql:host=localhost;dbname=china_ababel;charset=utf8mb4', 
            'china_ababel', 
            'Khan@70990100'
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->exchangeRateManager = new ExchangeRateManager();
        
        // Check authentication
        Auth::check();
    }
    
    /**
     * Display calculator page
     */
    public function index()
    {
        $data = [
            'title' => __('Currency Calculator'),
            'current_rates' => $this->getCurrentRatesClean(),
            'supported_currencies' => ['RMB', 'USD', 'SDG', 'AED']
        ];
        
        return $this->view('exchange_rates/calculator', $data);
    }
    
    /**
     * Convert currency - CLEAN implementation
     */
    public function convert()
    {
        try {
            $amount = $_POST['amount'] ?? 0;
            $fromCurrency = $_POST['from'] ?? '';
            $toCurrency = $_POST['to'] ?? '';
            
            // Validation
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
            
            if ($fromCurrency === $toCurrency) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => 'Cannot convert same currency'
                ]);
            }
            
            // Get exchange rate
            $rate = $this->getExchangeRate($fromCurrency, $toCurrency);
            if (!$rate) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => 'Exchange rate not available'
                ]);
            }
            
            // Calculate conversion
            $convertedAmount = $amount * $rate['rate'];
            
            // Log conversion safely (without failing if log fails)
            try {
                $this->logConversionSafe($amount, $fromCurrency, $convertedAmount, $toCurrency, $rate['rate']);
            } catch (Exception $e) {
                // Log error but don't fail the conversion
                error_log("Conversion logging failed: " . $e->getMessage());
            }
            
            // Return successful conversion
            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'amount' => round($convertedAmount, 2),
                    'rate' => $rate['rate'],
                    'original_amount' => $amount,
                    'from_currency' => $fromCurrency,
                    'to_currency' => $toCurrency,
                    'conversion_time' => date('Y-m-d H:i:s'),
                    'rate_last_updated' => $rate['last_updated'] ?? date('Y-m-d H:i:s'),
                    'rate_status' => $rate['source'] ?? 'manual'
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Currency conversion error: " . $e->getMessage());
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Conversion failed'
            ]);
        }
    }
    
    /**
     * Get exchange rate between two currencies
     */
    private function getExchangeRate($fromCurrency, $toCurrency)
    {
        try {
            // Direct rate
            $stmt = $this->pdo->prepare("
                SELECT rate, source, last_updated 
                FROM exchange_rates 
                WHERE currency_pair = ? AND is_active = 1 
                ORDER BY updated_at DESC LIMIT 1
            ");
            $stmt->execute([$fromCurrency . '_' . $toCurrency]);
            $rate = $stmt->fetch();
            
            if ($rate) {
                return [
                    'rate' => floatval($rate['rate']),
                    'source' => $rate['source'],
                    'last_updated' => $rate['last_updated']
                ];
            }
            
            // Try reverse rate
            $stmt->execute([$toCurrency . '_' . $fromCurrency]);
            $reverseRate = $stmt->fetch();
            
            if ($reverseRate) {
                return [
                    'rate' => 1 / floatval($reverseRate['rate']),
                    'source' => $reverseRate['source'],
                    'last_updated' => $reverseRate['last_updated']
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Exchange rate retrieval error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get current rates for display - clean implementation
     */
    private function getCurrentRatesClean()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT currency_pair, rate, source, last_updated,
                       TIMESTAMPDIFF(MINUTE, last_updated, NOW()) as minutes_ago
                FROM exchange_rates 
                WHERE is_active = 1 
                ORDER BY currency_pair
            ");
            
            $rates = [];
            while ($row = $stmt->fetch()) {
                $rates[$row['currency_pair']] = [
                    'rate' => floatval($row['rate']),
                    'source' => $row['source'],
                    'last_updated' => $row['last_updated'],
                    'minutes_ago' => $row['minutes_ago'],
                    'status' => $row['minutes_ago'] > 1440 ? 'stale' : 'fresh' // 24 hours
                ];
            }
            
            return $rates;
            
        } catch (Exception $e) {
            error_log("Current rates retrieval error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log conversion safely - won't fail if database issues occur
     */
    private function logConversionSafe($originalAmount, $fromCurrency, $convertedAmount, $toCurrency, $rate)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO currency_conversions (
                    from_currency, to_currency, amount_from, amount_to, 
                    original_amount, converted_amount, exchange_rate, 
                    conversion_time, conversion_type, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'automatic', 'completed', ?, ?)
            ");
            
            $timestamp = date('Y-m-d H:i:s');
            $userId = $_SESSION['user_id'] ?? 1;
            
            $stmt->execute([
                $fromCurrency, $toCurrency, $originalAmount, $convertedAmount,
                $originalAmount, $convertedAmount, $rate, 
                $timestamp, $userId, $timestamp
            ]);
            
        } catch (Exception $e) {
            // Just log the error, don't throw it
            error_log("Conversion logging failed: " . $e->getMessage());
        }
    }
}
?>