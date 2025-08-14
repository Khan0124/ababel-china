<?php
namespace App\Models;

use App\Core\Model;

class ExchangeRateManager extends Model
{
    protected $table = 'exchange_rates';
    
    public function getCurrentRate($fromCurrency, $toCurrency)
    {
        if ($fromCurrency === $toCurrency) {
            return [
                'rate' => 1.0,
                'last_updated' => date('Y-m-d H:i:s'),
                'pair' => $fromCurrency . '_' . $toCurrency,
                'status' => 'identity'
            ];
        }
        
        $pair = $fromCurrency . '_' . $toCurrency;
        $reversePair = $toCurrency . '_' . $fromCurrency;
        
        $sql = "SELECT rate, last_updated FROM exchange_rates WHERE currency_pair = ? ORDER BY last_updated DESC LIMIT 1";
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
        
        return $this->getDefaultRate($fromCurrency, $toCurrency);
    }
    
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
    
    public function updateExchangeRate($currencyPair, $rate, $source = 'manual')
    {
        $sql = "
            INSERT INTO exchange_rates (currency_pair, rate, source, last_updated, effective_date) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                rate = VALUES(rate),
                source = VALUES(source),
                last_updated = VALUES(last_updated),
                effective_date = VALUES(effective_date)
        ";
        
        $lastUpdated = date('Y-m-d H:i:s');
        $effectiveDate = date('Y-m-d');
        
        $result = $this->db->query($sql, [$currencyPair, $rate, $source, $lastUpdated, $effectiveDate]);
        if ($result) {
            $this->updateRateHistory($currencyPair, $rate, $source);
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
                'status' => $rate['minutes_ago'] > 1440 ? 'stale' : 'current'
            ];
        }
        return $formattedRates;
    }
    
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
        foreach ($rates as $rate) { $variance += pow($rate - $mean, 2); }
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
        if (abs($changePercent) > 5) { $impact = $changePercent > 0 ? 'positive' : 'negative'; }
        return [
            'impact' => $impact,
            'change_percent' => round($changePercent, 2),
            'current_rate' => $currentRate,
            'previous_rate' => $oldestRate,
            'period_days' => $days,
            'message' => $this->generateImpactMessage($impact, $changePercent)
        ];
    }
    
    public function autoUpdateRates()
    {
        $pairs = ['USD_RMB', 'SDG_RMB', 'AED_RMB', 'USD_SDG', 'USD_AED'];
        $updated = [];
        foreach ($pairs as $pair) {
            $currentRateData = $this->getCurrentRate(explode('_', $pair)[0], explode('_', $pair)[1]);
            if ($currentRateData) {
                $fluctuation = (rand(-200, 200) / 10000);
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
    
    private function logConversion($originalAmount, $fromCurrency, $convertedAmount, $toCurrency, $rate)
    {
        $sql = "INSERT INTO currency_conversions (original_amount, from_currency, converted_amount, to_currency, exchange_rate, conversion_time, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $timestamp = date('Y-m-d H:i:s');
        $this->db->query($sql, [$originalAmount, $fromCurrency, $convertedAmount, $toCurrency, $rate, $timestamp, $timestamp]);
    }
    
    private function updateRateHistory($currencyPair, $rate, $source)
    {
        $sql = "INSERT INTO exchange_rate_history (currency_pair, rate, source, recorded_at) VALUES (?, ?, ?, ?)";
        $this->db->query($sql, [$currencyPair, $rate, $source, date('Y-m-d H:i:s')]);
    }
    
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
    
    public function getConversionSummary($days = 7)
    {
        $sql = "SELECT from_currency, to_currency, COUNT(*) as conversion_count, SUM(original_amount) as total_original, SUM(converted_amount) as total_converted, AVG(exchange_rate) as avg_rate FROM currency_conversions WHERE conversion_time >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY from_currency, to_currency ORDER BY conversion_count DESC";
        $stmt = $this->db->query($sql, [$days]);
        return $stmt->fetchAll();
    }
}