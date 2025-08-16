<?php
namespace App\Models;

use App\Core\Model;

/**
 * Multi-Currency Exchange Rate Management System
 * Immediate Implementation - Phase 1
 */
class ExchangeRateManager extends Model
{
    protected $table = 'exchange_rates';
    
    /**
     * Get current exchange rate between two currencies
     */
    public function getCurrentRate($fromCurrency, $toCurrency)
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }
        
        $pair = $fromCurrency . '_' . $toCurrency;
        $reversePair = $toCurrency . '_' . $fromCurrency;
        
        $sql = "
            SELECT rate, last_updated 
            FROM exchange_rates 
            WHERE currency_pair = ? 
            ORDER BY last_updated DESC 
            LIMIT 1
        ";
        
        $stmt = $this->db->query($sql, [$pair]);
        $rate = $stmt->fetch();
        
        if ($rate) {
            return [
                'rate' => floatval($rate['rate']),
                'last_updated' => $rate['last_updated'],
                'pair' => $pair,
                'status' => 'direct'
            ];
        }
        
        // Try reverse pair
        $stmt = $this->db->query($sql, [$reversePair]);
        $reverseRate = $stmt->fetch();
        
        if ($reverseRate) {
            return [
                'rate' => 1 / floatval($reverseRate['rate']),
                'last_updated' => $reverseRate['last_updated'],
                'pair' => $reversePair,
                'status' => 'inverse'
            ];
        }
        
        // Try cross-currency calculation via RMB
        if ($fromCurrency !== 'RMB' && $toCurrency !== 'RMB') {
            $fromToRmb = $this->getCurrentRate($fromCurrency, 'RMB');
            $rmbToTo = $this->getCurrentRate('RMB', $toCurrency);
            
            if ($fromToRmb && $rmbToTo) {
                return [
                    'rate' => $fromToRmb['rate'] * $rmbToTo['rate'],
                    'last_updated' => min($fromToRmb['last_updated'], $rmbToTo['last_updated']),
                    'pair' => $pair,
                    'status' => 'cross_rmb'
                ];
            }
        }
        
        // Return default rates if no data available
        return $this->getDefaultRate($fromCurrency, $toCurrency);
    }
    
    /**
     * Convert amount from one currency to another
     */
    public function convertCurrency($amount, $fromCurrency, $toCurrency)
    {
        if ($fromCurrency === $toCurrency) {
            return [
                'amount' => $amount,
                'rate' => 1.0,
                'original_amount' => $amount,
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'conversion_time' => date('Y-m-d H:i:s')
            ];
        }
        
        $rateData = $this->getCurrentRate($fromCurrency, $toCurrency);
        $convertedAmount = $amount * $rateData['rate'];
        
        // Log conversion for audit trail
        $this->logConversion($amount, $fromCurrency, $convertedAmount, $toCurrency, $rateData['rate']);
        
        return [
            'amount' => round($convertedAmount, 2),
            'rate' => $rateData['rate'],
            'original_amount' => $amount,
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'conversion_time' => date('Y-m-d H:i:s'),
            'rate_last_updated' => $rateData['last_updated'],
            'rate_status' => $rateData['status']
        ];
    }
    
    /**
     * Update exchange rate for a currency pair
     */
    public function updateExchangeRate($currencyPair, $rate, $source = 'manual')
    {
        // Parse currency pair to get from and to currencies
        $currencies = explode('_', $currencyPair);
        if (count($currencies) !== 2) {
            return ['success' => false, 'error' => 'Invalid currency pair format'];
        }
        
        $fromCurrency = $currencies[0];
        $toCurrency = $currencies[1];
        $lastUpdated = date('Y-m-d H:i:s');
        $effectiveDate = date('Y-m-d');
        
        // Check if rate exists
        $checkSql = "SELECT id FROM exchange_rates WHERE currency_pair = ?";
        $stmt = $this->db->query($checkSql, [$currencyPair]);
        $existingRate = $stmt->fetch();
        
        if ($existingRate) {
            // Update existing rate
            $updateSql = "
                UPDATE exchange_rates 
                SET rate = ?, 
                    source = ?, 
                    last_updated = ?, 
                    effective_date = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE currency_pair = ?
            ";
            $result = $this->db->query($updateSql, [$rate, $source, $lastUpdated, $effectiveDate, $currencyPair]);
        } else {
            // Insert new rate - use INSERT IGNORE to handle duplicates gracefully
            $insertSql = "
                INSERT IGNORE INTO exchange_rates 
                (from_currency, to_currency, currency_pair, rate, source, last_updated, effective_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";
            $result = $this->db->query($insertSql, [$fromCurrency, $toCurrency, $currencyPair, $rate, $source, $lastUpdated, $effectiveDate]);
            
            // If INSERT IGNORE didn't work (record exists), try update instead
            if ($result && $this->db->lastInsertId() == 0) {
                $updateSql = "
                    UPDATE exchange_rates 
                    SET rate = ?, 
                        source = ?, 
                        last_updated = ?, 
                        effective_date = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE currency_pair = ?
                ";
                $result = $this->db->query($updateSql, [$rate, $source, $lastUpdated, $effectiveDate, $currencyPair]);
            }
        }
        
        if ($result) {
            // Update rate history if method exists
            if (method_exists($this, 'updateRateHistory')) {
                $this->updateRateHistory($currencyPair, $rate, $source);
            }
            
            return [
                'success' => true,
                'currency_pair' => $currencyPair,
                'rate' => $rate,
                'source' => $source,
                'updated_at' => $lastUpdated
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to update exchange rate'];
    }
    
    /**
     * Get all current exchange rates
     */
    public function getAllCurrentRates()
    {
        $sql = "
            SELECT DISTINCT 
                currency_pair,
                rate,
                source,
                last_updated,
                TIMESTAMPDIFF(MINUTE, last_updated, NOW()) as minutes_ago
            FROM exchange_rates er1
            WHERE last_updated = (
                SELECT MAX(last_updated) 
                FROM exchange_rates er2 
                WHERE er2.currency_pair = er1.currency_pair
            )
            ORDER BY currency_pair
        ";
        
        $stmt = $this->db->query($sql);
        $rates = $stmt->fetchAll();
        
        $formattedRates = [];
        foreach ($rates as $rate) {
            $currencies = explode('_', $rate['currency_pair']);
            $formattedRates[$rate['currency_pair']] = [
                'from_currency' => $currencies[0] ?? '',
                'to_currency' => $currencies[1] ?? '',
                'rate' => floatval($rate['rate']),
                'source' => $rate['source'],
                'last_updated' => $rate['last_updated'],
                'minutes_ago' => $rate['minutes_ago'],
                'status' => $rate['minutes_ago'] > 1440 ? 'stale' : 'current' // 24 hours
            ];
        }
        
        return $formattedRates;
    }
    
    /**
     * Initialize default exchange rates
     */
    public function initializeDefaultRates()
    {
        $defaultRates = [
            'USD_RMB' => 7.25,
            'SDG_RMB' => 0.012,
            'AED_RMB' => 1.97,
            'USD_SDG' => 604.17,
            'USD_AED' => 3.68,
            'RMB_SDG' => 83.33,
            'RMB_AED' => 0.51,
            'SDG_AED' => 0.0061,
        ];
        
        $initialized = [];
        
        foreach ($defaultRates as $pair => $rate) {
            $result = $this->updateExchangeRate($pair, $rate, 'system_default');
            if ($result['success']) {
                $initialized[] = $pair;
            }
        }
        
        return [
            'success' => true,
            'initialized_pairs' => count($initialized),
            'pairs' => $initialized,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get exchange rate history for a currency pair
     */
    public function getRateHistory($currencyPair, $days = 30)
    {
        $sql = "
            SELECT 
                rate,
                source,
                last_updated,
                DATE(last_updated) as date
            FROM exchange_rates 
            WHERE currency_pair = ? 
            AND last_updated >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY last_updated DESC
        ";
        
        $stmt = $this->db->query($sql, [$currencyPair, $days]);
        return $stmt->fetchAll();
    }
    
    /**
     * Calculate currency volatility
     */
    public function calculateVolatility($currencyPair, $days = 30)
    {
        $history = $this->getRateHistory($currencyPair, $days);
        
        if (count($history) < 2) {
            return [
                'volatility' => 0,
                'status' => 'insufficient_data',
                'data_points' => count($history)
            ];
        }
        
        $rates = array_column($history, 'rate');
        $mean = array_sum($rates) / count($rates);
        
        $variance = 0;
        foreach ($rates as $rate) {
            $variance += pow($rate - $mean, 2);
        }
        
        $variance /= count($rates);
        $stdDev = sqrt($variance);
        $volatility = ($stdDev / $mean) * 100;
        
        $status = 'stable';
        if ($volatility > 5) $status = 'high';
        elseif ($volatility > 2) $status = 'medium';
        
        return [
            'volatility' => round($volatility, 2),
            'status' => $status,
            'std_deviation' => round($stdDev, 4),
            'mean_rate' => round($mean, 4),
            'data_points' => count($history),
            'period_days' => $days
        ];
    }
    
    /**
     * Get financial impact of rate changes
     */
    public function getFinancialImpact($currencyPair, $days = 7)
    {
        $history = $this->getRateHistory($currencyPair, $days);
        
        if (count($history) < 2) {
            return [
                'impact' => 'neutral',
                'change_percent' => 0,
                'message' => 'Insufficient historical data'
            ];
        }
        
        $currentRate = $history[0]['rate'];
        $oldestRate = end($history)['rate'];
        
        $changePercent = (($currentRate - $oldestRate) / $oldestRate) * 100;
        
        $impact = 'neutral';
        if (abs($changePercent) > 5) {
            $impact = $changePercent > 0 ? 'positive' : 'negative';
        }
        
        return [
            'impact' => $impact,
            'change_percent' => round($changePercent, 2),
            'current_rate' => $currentRate,
            'previous_rate' => $oldestRate,
            'period_days' => $days,
            'message' => $this->generateImpactMessage($impact, $changePercent)
        ];
    }
    
    /**
     * Auto-update rates from external sources (simulated for immediate implementation)
     */
    public function autoUpdateRates()
    {
        // Simulate external API calls with reasonable rate fluctuations
        $pairs = ['USD_RMB', 'SDG_RMB', 'AED_RMB', 'USD_SDG', 'USD_AED'];
        $updated = [];
        
        foreach ($pairs as $pair) {
            $currentRateData = $this->getCurrentRate(
                explode('_', $pair)[0], 
                explode('_', $pair)[1]
            );
            
            if ($currentRateData) {
                // Simulate 0.5% to 2% fluctuation
                $fluctuation = (rand(-200, 200) / 10000); // -2% to +2%
                $newRate = $currentRateData['rate'] * (1 + $fluctuation);
                
                $result = $this->updateExchangeRate($pair, $newRate, 'auto_update');
                if ($result['success']) {
                    $updated[] = [
                        'pair' => $pair,
                        'old_rate' => $currentRateData['rate'],
                        'new_rate' => $newRate,
                        'change_percent' => round($fluctuation * 100, 3)
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'updated_pairs' => count($updated),
            'updates' => $updated,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get default rate for currency pair
     */
    private function getDefaultRate($fromCurrency, $toCurrency)
    {
        $defaultRates = [
            'USD_RMB' => 7.25, 'RMB_USD' => 0.138,
            'SDG_RMB' => 0.012, 'RMB_SDG' => 83.33,
            'AED_RMB' => 1.97, 'RMB_AED' => 0.51,
            'USD_SDG' => 604.17, 'SDG_USD' => 0.00165,
            'USD_AED' => 3.68, 'AED_USD' => 0.27,
            'SDG_AED' => 0.0061, 'AED_SDG' => 164.26
        ];
        
        $pair = $fromCurrency . '_' . $toCurrency;
        $rate = $defaultRates[$pair] ?? 1.0;
        
        return [
            'rate' => $rate,
            'last_updated' => date('Y-m-d H:i:s'),
            'pair' => $pair,
            'status' => 'default'
        ];
    }
    
    /**
     * Log currency conversion for audit trail
     */
    private function logConversion($originalAmount, $fromCurrency, $convertedAmount, $toCurrency, $rate)
    {
        $sql = "
            INSERT INTO currency_conversions (
                from_currency, to_currency, amount_from, amount_to, 
                original_amount, converted_amount, exchange_rate, 
                conversion_time, conversion_type, status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'automatic', 'completed', ?, ?)
        ";
        
        $timestamp = date('Y-m-d H:i:s');
        $userId = $_SESSION['user_id'] ?? 1; // Default to admin if no session
        
        $this->db->query($sql, [
            $fromCurrency, $toCurrency, $originalAmount, $convertedAmount,
            $originalAmount, $convertedAmount, $rate, 
            $timestamp, $userId, $timestamp
        ]);
    }
    
    /**
     * Update rate history table
     */
    private function updateRateHistory($currencyPair, $rate, $source)
    {
        $sql = "
            INSERT INTO exchange_rate_history (
                currency_pair, rate, source, recorded_at
            ) VALUES (?, ?, ?, ?)
        ";
        
        $this->db->query($sql, [$currencyPair, $rate, $source, date('Y-m-d H:i:s')]);
    }
    
    /**
     * Generate impact message
     */
    private function generateImpactMessage($impact, $changePercent)
    {
        switch ($impact) {
            case 'positive':
                return "Rate increased by " . abs($changePercent) . "% - Favorable for conversions TO this currency";
            case 'negative':
                return "Rate decreased by " . abs($changePercent) . "% - Less favorable for conversions TO this currency";
            default:
                return "Rate remained stable with minimal fluctuation";
        }
    }
    
    /**
     * Get currency conversion summary for dashboard
     */
    public function getConversionSummary($days = 7)
    {
        $sql = "
            SELECT 
                from_currency,
                to_currency,
                COUNT(*) as conversion_count,
                SUM(original_amount) as total_original,
                SUM(converted_amount) as total_converted,
                AVG(exchange_rate) as avg_rate
            FROM currency_conversions
            WHERE conversion_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY from_currency, to_currency
            ORDER BY conversion_count DESC
        ";
        
        $stmt = $this->db->query($sql, [$days]);
        return $stmt->fetchAll();
    }
}