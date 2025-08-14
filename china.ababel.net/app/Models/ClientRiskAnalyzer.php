<?php
namespace App\Models;

use App\Core\Model;

/**
 * Advanced Client Risk Assessment System
 * Immediate Implementation - Phase 1
 */
class ClientRiskAnalyzer extends Model
{
    protected $table = 'clients';
    
    /**
     * Calculate comprehensive risk score for a client
     */
    public function calculateRiskScore($clientId)
    {
        $client = $this->find($clientId);
        if (!$client) {
            return ['error' => 'Client not found'];
        }
        
        $factors = [
            'payment_history' => $this->analyzePaymentHistory($clientId),
            'transaction_volume' => $this->analyzeTransactionVolume($clientId),
            'credit_utilization' => $this->analyzeCreditUtilization($client),
            'account_age' => $this->analyzeAccountAge($client),
            'transaction_frequency' => $this->analyzeTransactionFrequency($clientId)
        ];
        
        $riskScore = $this->calculateWeightedRiskScore($factors);
        $riskLevel = $this->determineRiskLevel($riskScore);
        
        return [
            'client_id' => $clientId,
            'client_name' => $client['name'],
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'factors' => $factors,
            'recommendations' => $this->generateRecommendations($riskLevel, $factors),
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Analyze payment history patterns
     */
    private function analyzePaymentHistory($clientId)
    {
        $sql = "
            SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_transactions,
                AVG(DATEDIFF(approved_at, created_at)) as avg_approval_days,
                MAX(transaction_date) as last_transaction_date,
                AVG(total_amount_rmb) as avg_transaction_amount
            FROM transactions 
            WHERE client_id = ? 
            AND transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
        ";
        
        $stmt = $this->db->query($sql, [$clientId]);
        $history = $stmt->fetch();
        
        if (!$history || $history['total_transactions'] == 0) {
            return [
                'score' => 50, // Neutral for new clients
                'details' => 'No transaction history available',
                'total_transactions' => 0,
                'approval_rate' => 0
            ];
        }
        
        $approvalRate = ($history['approved_transactions'] / $history['total_transactions']) * 100;
        $avgApprovalDays = $history['avg_approval_days'] ?? 0;
        
        // Calculate score based on approval rate and speed
        $score = 100;
        if ($approvalRate < 80) $score -= 20;
        if ($approvalRate < 60) $score -= 30;
        if ($avgApprovalDays > 7) $score -= 10;
        if ($avgApprovalDays > 14) $score -= 20;
        
        return [
            'score' => max(0, $score),
            'approval_rate' => round($approvalRate, 1),
            'avg_approval_days' => round($avgApprovalDays, 1),
            'total_transactions' => $history['total_transactions'],
            'last_transaction' => $history['last_transaction_date']
        ];
    }
    
    /**
     * Analyze transaction volume trends
     */
    private function analyzeTransactionVolume($clientId)
    {
        $sql = "
            SELECT 
                DATE_FORMAT(transaction_date, '%Y-%m') as month,
                COUNT(*) as transaction_count,
                SUM(total_amount_rmb) as monthly_volume
            FROM transactions 
            WHERE client_id = ? 
            AND status = 'approved'
            AND transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
            ORDER BY month
        ";
        
        $stmt = $this->db->query($sql, [$clientId]);
        $monthlyData = $stmt->fetchAll();
        
        if (empty($monthlyData)) {
            return [
                'score' => 50,
                'trend' => 'stable',
                'monthly_avg' => 0,
                'volatility' => 'unknown'
            ];
        }
        
        $volumes = array_column($monthlyData, 'monthly_volume');
        $avgVolume = array_sum($volumes) / count($volumes);
        $volatility = $this->calculateVolatility($volumes);
        $trend = $this->calculateTrend($volumes);
        
        // Score based on consistency and growth
        $score = 70;
        if ($trend === 'growing') $score += 20;
        if ($trend === 'declining') $score -= 20;
        if ($volatility === 'high') $score -= 15;
        if ($volatility === 'low') $score += 10;
        
        return [
            'score' => max(0, min(100, $score)),
            'trend' => $trend,
            'monthly_avg' => round($avgVolume, 2),
            'volatility' => $volatility,
            'data_points' => count($monthlyData)
        ];
    }
    
    /**
     * Analyze credit utilization
     */
    private function analyzeCreditUtilization($client)
    {
        $creditLimit = $client['credit_limit'] ?? 0;
        $currentBalance = $client['balance_rmb'] ?? 0;
        
        if ($creditLimit <= 0) {
            return [
                'score' => 50,
                'utilization_rate' => 0,
                'available_credit' => 0,
                'status' => 'no_credit_limit'
            ];
        }
        
        $utilizationRate = (abs($currentBalance) / $creditLimit) * 100;
        
        // Score based on utilization rate
        $score = 100;
        if ($utilizationRate > 90) $score = 10;
        elseif ($utilizationRate > 80) $score = 30;
        elseif ($utilizationRate > 70) $score = 50;
        elseif ($utilizationRate > 50) $score = 70;
        elseif ($utilizationRate > 30) $score = 85;
        
        return [
            'score' => $score,
            'utilization_rate' => round($utilizationRate, 1),
            'available_credit' => $creditLimit - abs($currentBalance),
            'current_balance' => $currentBalance,
            'credit_limit' => $creditLimit
        ];
    }
    
    /**
     * Analyze account age factor
     */
    private function analyzeAccountAge($client)
    {
        $createdDate = new \DateTime($client['created_at']);
        $currentDate = new \DateTime();
        $daysDifference = $currentDate->diff($createdDate)->days;
        $monthsOld = $daysDifference / 30;
        
        // Older accounts are generally less risky
        $score = 50; // Base score for new accounts
        if ($monthsOld > 24) $score = 95;
        elseif ($monthsOld > 12) $score = 85;
        elseif ($monthsOld > 6) $score = 75;
        elseif ($monthsOld > 3) $score = 65;
        elseif ($monthsOld > 1) $score = 55;
        
        return [
            'score' => $score,
            'account_age_days' => $daysDifference,
            'account_age_months' => round($monthsOld, 1),
            'created_date' => $client['created_at']
        ];
    }
    
    /**
     * Analyze transaction frequency
     */
    private function analyzeTransactionFrequency($clientId)
    {
        $sql = "
            SELECT 
                COUNT(*) as total_transactions,
                DATEDIFF(MAX(transaction_date), MIN(transaction_date)) as active_days,
                COUNT(DISTINCT DATE_FORMAT(transaction_date, '%Y-%m')) as active_months
            FROM transactions 
            WHERE client_id = ? 
            AND status = 'approved'
            AND transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
        ";
        
        $stmt = $this->db->query($sql, [$clientId]);
        $frequency = $stmt->fetch();
        
        if (!$frequency || $frequency['total_transactions'] == 0) {
            return [
                'score' => 40,
                'frequency' => 'none',
                'transactions_per_month' => 0
            ];
        }
        
        $transactionsPerMonth = $frequency['active_months'] > 0 ? 
            $frequency['total_transactions'] / $frequency['active_months'] : 0;
        
        // Score based on consistent activity
        $score = 50;
        if ($transactionsPerMonth >= 10) $score = 90;
        elseif ($transactionsPerMonth >= 5) $score = 80;
        elseif ($transactionsPerMonth >= 2) $score = 70;
        elseif ($transactionsPerMonth >= 1) $score = 60;
        
        return [
            'score' => $score,
            'transactions_per_month' => round($transactionsPerMonth, 1),
            'active_months' => $frequency['active_months'],
            'total_transactions' => $frequency['total_transactions']
        ];
    }
    
    /**
     * Calculate weighted risk score
     */
    private function calculateWeightedRiskScore($factors)
    {
        $weights = [
            'payment_history' => 0.3,      // 30% - Most important
            'credit_utilization' => 0.25,  // 25% - Very important
            'transaction_volume' => 0.2,   // 20% - Important
            'account_age' => 0.15,         // 15% - Moderately important
            'transaction_frequency' => 0.1  // 10% - Least important
        ];
        
        $weightedScore = 0;
        $totalWeight = 0;
        
        foreach ($factors as $factor => $data) {
            if (isset($weights[$factor]) && isset($data['score'])) {
                $weightedScore += $data['score'] * $weights[$factor];
                $totalWeight += $weights[$factor];
            }
        }
        
        return $totalWeight > 0 ? round($weightedScore / $totalWeight, 1) : 50;
    }
    
    /**
     * Determine risk level from score
     */
    private function determineRiskLevel($score)
    {
        if ($score >= 80) return 'LOW';
        if ($score >= 60) return 'MEDIUM';
        return 'HIGH';
    }
    
    /**
     * Generate actionable recommendations
     */
    private function generateRecommendations($riskLevel, $factors)
    {
        $recommendations = [];
        
        switch ($riskLevel) {
            case 'HIGH':
                $recommendations[] = 'URGENT: Review all pending transactions carefully';
                $recommendations[] = 'Consider reducing credit limit or requiring additional collateral';
                $recommendations[] = 'Implement enhanced monitoring for this client';
                if ($factors['credit_utilization']['utilization_rate'] > 80) {
                    $recommendations[] = 'Credit utilization is critically high - immediate attention required';
                }
                break;
                
            case 'MEDIUM':
                $recommendations[] = 'Monitor client activity more closely';
                $recommendations[] = 'Consider discussing payment terms with client';
                if ($factors['payment_history']['approval_rate'] < 80) {
                    $recommendations[] = 'Payment history shows some concerns - follow up required';
                }
                break;
                
            case 'LOW':
                $recommendations[] = 'Client is performing well within acceptable parameters';
                $recommendations[] = 'Consider increasing credit limit if business needs justify';
                $recommendations[] = 'Maintain regular monitoring schedule';
                break;
        }
        
        return $recommendations;
    }
    
    /**
     * Calculate volatility of values
     */
    private function calculateVolatility($values)
    {
        if (count($values) < 2) return 'unknown';
        
        $mean = array_sum($values) / count($values);
        $variance = 0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $variance /= count($values);
        $stdDev = sqrt($variance);
        $coefficientOfVariation = $mean > 0 ? ($stdDev / $mean) * 100 : 0;
        
        if ($coefficientOfVariation > 50) return 'high';
        if ($coefficientOfVariation > 25) return 'medium';
        return 'low';
    }
    
    /**
     * Calculate trend direction
     */
    private function calculateTrend($values)
    {
        if (count($values) < 3) return 'stable';
        
        $n = count($values);
        $sumX = ($n * ($n + 1)) / 2;
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $values[$i];
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        
        if ($slope > ($sumY / $n) * 0.1) return 'growing';
        if ($slope < -($sumY / $n) * 0.1) return 'declining';
        return 'stable';
    }
    
    /**
     * Get risk assessment for all clients
     */
    public function getAllClientRiskScores($limit = 50)
    {
        $clients = $this->all(['status' => 'active'], 'name', $limit);
        $riskAssessments = [];
        
        foreach ($clients as $client) {
            $risk = $this->calculateRiskScore($client['id']);
            $riskAssessments[] = [
                'id' => $client['id'],
                'name' => $client['name'],
                'client_code' => $client['client_code'],
                'risk_score' => $risk['risk_score'],
                'risk_level' => $risk['risk_level'],
                'balance' => $client['balance_rmb']
            ];
        }
        
        // Sort by risk score (highest risk first)
        usort($riskAssessments, function($a, $b) {
            return $a['risk_score'] <=> $b['risk_score'];
        });
        
        return $riskAssessments;
    }
    
    /**
     * Update client risk assessment in database
     */
    public function updateClientRiskAssessment($clientId, $riskData)
    {
        // Store risk assessment in client_analytics table
        $sql = "
            INSERT INTO client_analytics (
                client_id, month_year, payment_score, risk_level, last_updated
            ) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                payment_score = VALUES(payment_score),
                risk_level = VALUES(risk_level),
                last_updated = VALUES(last_updated)
        ";
        
        $monthYear = date('Y-m');
        $paymentScore = $riskData['risk_score'];
        $riskLevel = $riskData['risk_level'];
        $lastUpdated = date('Y-m-d H:i:s');
        
        return $this->db->query($sql, [
            $clientId, $monthYear, $paymentScore, $riskLevel, $lastUpdated
        ]);
    }
}