<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware\Auth;
use App\Core\Security\CSRF;
use PDO;

/**
 * Simple Exchange Rate Controller
 * Direct solution without complex Model dependencies
 */
class SimpleExchangeRateController extends Controller
{
    private $pdo;
    
    public function __construct()
    {
        parent::__construct();
        
        // Direct database connection
        $this->pdo = new PDO(
            'mysql:host=localhost;dbname=china_ababel;charset=utf8mb4', 
            'china_ababel', 
            'Khan@70990100'
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check authentication for all methods
        Auth::check();
    }
    
    /**
     * Update exchange rate - DIRECT SOLUTION
     */
    public function updateRate()
    {
        try {
            // CSRF validation
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
            
            // Parse currency pair
            $currencies = explode('_', $currencyPair);
            if (count($currencies) !== 2) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => 'Invalid currency pair format'
                ]);
            }
            
            $fromCurrency = $currencies[0];
            $toCurrency = $currencies[1];
            $lastUpdated = date('Y-m-d H:i:s');
            $effectiveDate = date('Y-m-d');
            
            // Check if record exists
            $checkStmt = $this->pdo->prepare("SELECT id FROM exchange_rates WHERE currency_pair = ?");
            $checkStmt->execute([$currencyPair]);
            $exists = $checkStmt->fetch();
            
            if ($exists) {
                // Update existing record
                $updateStmt = $this->pdo->prepare("
                    UPDATE exchange_rates 
                    SET rate = ?, 
                        source = 'manual', 
                        last_updated = ?, 
                        effective_date = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE currency_pair = ?
                ");
                $result = $updateStmt->execute([$rate, $lastUpdated, $effectiveDate, $currencyPair]);
            } else {
                // Insert new record
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO exchange_rates 
                    (from_currency, to_currency, currency_pair, rate, source, last_updated, effective_date) 
                    VALUES (?, ?, ?, ?, 'manual', ?, ?)
                ");
                $result = $insertStmt->execute([$fromCurrency, $toCurrency, $currencyPair, $rate, $lastUpdated, $effectiveDate]);
            }
            
            if ($result) {
                return $this->jsonResponse([
                    'success' => true,
                    'currency_pair' => $currencyPair,
                    'rate' => $rate,
                    'source' => 'manual',
                    'updated_at' => $lastUpdated,
                    'message' => 'Exchange rate updated successfully'
                ]);
            } else {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => 'Failed to update exchange rate'
                ]);
            }
            
        } catch (\Exception $e) {
            error_log("Exchange rate update error: " . $e->getMessage());
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Database error occurred'
            ]);
        }
    }
}
?>