<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Loading;

class SyncService
{
    private $db;
    private $loadingModel;
    private $portSudanApiUrl;
    private $apiKey;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->loadingModel = new Loading();
        
        // Load settings from database
        $this->loadSettings();
    }
    
    /**
     * Load API settings from database
     */
    private function loadSettings()
    {
        // Get API URL from settings
        $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'port_sudan_api_url'");
        $result = $stmt->fetch();
        $this->portSudanApiUrl = $result ? $result['setting_value'] : 'https://ababel.net/app/api/china_sync.php';
        
        // Get API Key from settings
        $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'port_sudan_api_key'");
        $result = $stmt->fetch();
        $this->apiKey = $result ? $result['setting_value'] : 'AB@1234X-China2Port!';
    }
    
    /**
     * Sync all pending loadings to Port Sudan
     */
    public function syncPendingLoadings()
    {
        $pendingLoadings = $this->loadingModel->getPendingPortSudanSync();
        $results = [];
        
        foreach ($pendingLoadings as $loading) {
            try {
                $result = $this->syncLoadingToPortSudan($loading);
                $results[] = [
                    'loading_id' => $loading['id'],
                    'status' => 'success',
                    'message' => $result['message']
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'loading_id' => $loading['id'],
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                
                // Update sync attempts
                $this->db->query(
                    "UPDATE loadings SET sync_attempts = sync_attempts + 1, 
                     last_sync_at = NOW() WHERE id = ?",
                    [$loading['id']]
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Sync single loading to Port Sudan
     */
    public function syncLoadingToPortSudan($loading)
    {
        if (!is_array($loading)) {
            $loading = $this->loadingModel->find($loading);
            if (!$loading) {
                throw new \Exception('Loading not found');
            }
        }
        
        // Prepare data for Port Sudan API
        $syncData = [
            'china_loading_id' => $loading['id'],
            'entry_date' => $loading['shipping_date'],
            'code' => $loading['client_code'],
            'client_name' => $loading['client_name'],
            'loading_number' => $loading['loading_no'],
            'carton_count' => intval($loading['cartons_count']),
            'container_number' => $loading['container_no'],
            'bill_number' => $loading['claim_number'] ?? '',
            'category' => $loading['item_description'] ?: 'General Cargo',
            'carrier' => 'TBD',
            'expected_arrival' => date('Y-m-d', strtotime($loading['shipping_date'] . ' +30 days')),
            'ship_name' => 'TBD',
            'custom_station' => 'Port Sudan',
            'office' => 'بورتسودان'
        ];
        
        // Make API call to create container using action parameter
        $response = $this->makeApiCall([
            'action' => 'create_container',
            'data' => $syncData
        ], 'POST');
        
        // Log sync attempt
        $this->logSyncAttempt('create_container', $loading['id'], null, $syncData, $response);
        
        if ($response['success']) {
            // Update loading with sync status and Port Sudan ID
            $this->db->query(
                "UPDATE loadings SET 
                    sync_status = 'synced',
                    port_sudan_id = ?,
                    last_sync_at = NOW(),
                    sync_attempts = sync_attempts + 1
                WHERE id = ?",
                [$response['container_id'] ?? null, $loading['id']]
            );
            
            // Log in loading_sync_log
            $this->db->query(
                "INSERT INTO loading_sync_log (loading_id, action, status, request_data, response_data) 
                VALUES (?, 'create', 'success', ?, ?)",
                [$loading['id'], json_encode($syncData), json_encode($response)]
            );
            
            return [
                'success' => true,
                'message' => 'Loading synced successfully to Port Sudan',
                'port_sudan_container_id' => $response['container_id'] ?? null
            ];
        } else {
            // Update sync status to failed
            $this->db->query(
                "UPDATE loadings SET 
                    sync_status = 'failed',
                    last_sync_at = NOW(),
                    sync_attempts = sync_attempts + 1
                WHERE id = ?",
                [$loading['id']]
            );
            
            throw new \Exception('Port Sudan sync failed: ' . ($response['error'] ?? 'Unknown error'));
        }
    }
    
    /**
     * Make API call to Port Sudan with action parameter
     */
    private function makeApiCall($requestData, $method = 'POST')
    {
        // Base URL without endpoint
        $url = $this->portSudanApiUrl;
        
        $ch = curl_init();
        
        // Common CURL options
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-API-Key: ' . $this->apiKey
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ];
        
        // Method-specific options
        if ($method === 'POST') {
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($requestData);
        } elseif ($method === 'PUT') {
            $curlOptions[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($requestData);
        } elseif ($method === 'DELETE') {
            $curlOptions[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($requestData);
        } elseif ($method === 'GET' && !empty($requestData)) {
            $url .= '?' . http_build_query($requestData);
            $curlOptions[CURLOPT_URL] = $url;
        }
        
        curl_setopt_array($ch, $curlOptions);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("CURL Error: " . $error);
        }
        
        $decodedResponse = json_decode($response, true);
        
        // Handle API key errors specifically
        if (isset($decodedResponse['error']) && strpos($decodedResponse['error'], 'Invalid API key') !== false) {
            throw new \Exception("Port Sudan API rejected our API key. Please verify the key in system settings.");
        }
        
        if ($httpCode >= 400) {
            $errorMsg = "HTTP Error: $httpCode\n";
            $errorMsg .= "URL: $url\n";
            $errorMsg .= "Method: $method\n";
            $errorMsg .= "Request: " . json_encode($requestData) . "\n";
            $errorMsg .= "Response: $response";
            throw new \Exception($errorMsg);
        }
        
        // Return standardized response
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $response,
            'data' => $decodedResponse
        ] + ($decodedResponse ?: []);
    }
    
    /**
     * Log sync attempt to api_sync_log table
     */
    private function logSyncAttempt($action, $chinaLoadingId, $containerId, $requestData, $response)
    {
        try {
            $this->db->query(
                "INSERT INTO api_sync_log (
                    endpoint, method, china_loading_id, container_id, 
                    request_data, response_code, response_data, 
                    ip_address, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $this->portSudanApiUrl, // Log the base URL
                    'POST',
                    $chinaLoadingId,
                    $containerId,
                    json_encode(['action' => $action, 'data' => $requestData]),
                    $response['http_code'] ?? 0,
                    json_encode($response),
                    $_SERVER['REMOTE_ADDR'] ?? 'system'
                ]
            );
        } catch (\Exception $e) {
            error_log("Failed to log sync attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Update loading in Port Sudan
     */
    public function updateLoadingInPortSudan($loadingId, $updateData)
    {
        $loading = $this->loadingModel->find($loadingId);
        if (!$loading || $loading['office'] !== 'port_sudan') {
            return false;
        }
        
        // Add china_loading_id to update data
        $syncData = array_merge(['china_loading_id' => $loadingId], $updateData);
        
        $response = $this->makeApiCall([
            'action' => 'update_container',
            'data' => $syncData
        ], 'POST');
        $this->logSyncAttempt('update_container', $loadingId, null, $syncData, $response);
        
        return $response['success'] ?? false;
    }
    
    /**
     * Sync BOL to Port Sudan
     */
    public function syncBolToPortSudan($loadingId, $bolData)
    {
        $loading = $this->loadingModel->find($loadingId);
        if (!$loading || $loading['office'] !== 'port_sudan') {
            return false;
        }
        
        $syncData = array_merge(
            ['china_loading_id' => $loadingId],
            $bolData
        );
        
        $response = $this->makeApiCall([
            'action' => 'update_bol',
            'data' => $syncData
        ], 'POST');
        $this->logSyncAttempt('update_bol', $loadingId, null, $syncData, $response);
        
        return $response['success'] ?? false;
    }
    
    /**
     * Delete loading from Port Sudan
     */
    public function deleteLoadingFromPortSudan($loadingId)
    {
        $syncData = ['china_loading_id' => $loadingId];
        
        $response = $this->makeApiCall([
            'action' => 'delete_container',
            'data' => $syncData
        ], 'POST');
        $this->logSyncAttempt('delete_container', $loadingId, null, $syncData, $response);
        
        // Update sync status if deletion successful
        if ($response['success'] ?? false) {
            $this->db->query(
                "UPDATE loadings SET sync_status = NULL, port_sudan_id = NULL WHERE id = ?",
                [$loadingId]
            );
        }
        
        return $response['success'] ?? false;
    }
    
    /**
     * Get loading sync status
     */
    public function getLoadingSyncStatus($loadingId)
    {
        $stmt = $this->db->query(
            "SELECT * FROM api_sync_log 
             WHERE china_loading_id = ? 
             ORDER BY created_at DESC 
             LIMIT 10", 
            [$loadingId]
        );
        return $stmt->fetchAll();
    }
    
    /**
     * Retry failed sync
     */
    public function retryFailedSync($loadingId)
    {
        $loading = $this->loadingModel->find($loadingId);
        if (!$loading) {
            throw new \Exception('Loading not found');
        }
        
        if ($loading['office'] === 'port_sudan') {
            // Reset sync status to trigger retry
            $this->db->query(
                "UPDATE loadings SET sync_status = 'pending' WHERE id = ?",
                [$loadingId]
            );
            
            return $this->syncLoadingToPortSudan($loading);
        }
        
        return ['success' => false, 'message' => 'Loading is not assigned to Port Sudan'];
    }
    
    /**
     * Get sync statistics
     */
    public function getSyncStatistics($dateFrom = null, $dateTo = null)
    {
        $whereClause = "WHERE endpoint LIKE '/china_sync%'";
        $params = [];
        
        if ($dateFrom) {
            $whereClause .= " AND DATE(created_at) >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereClause .= " AND DATE(created_at) <= ?";
            $params[] = $dateTo;
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_attempts,
                    COUNT(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 END) as successful,
                    COUNT(CASE WHEN response_code >= 400 THEN 1 END) as failed,
                    COUNT(DISTINCT china_loading_id) as unique_loadings,
                    AVG(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 ELSE 0 END) * 100 as success_rate
                FROM api_sync_log 
                {$whereClause}";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Handle Port Sudan webhook
     */
    public function handlePortSudanWebhook($data)
    {
        if (!isset($data['action']) || !isset($data['data'])) {
            throw new \Exception('Invalid webhook data');
        }
        
        switch ($data['action']) {
            case 'container_status_updated':
                return $this->handleContainerStatusUpdate($data['data']);
            case 'bol_issued':
                return $this->handleBolIssued($data['data']);
            case 'container_arrived':
                return $this->handleContainerArrived($data['data']);
            default:
                throw new \Exception('Unknown webhook action: ' . $data['action']);
        }
    }
    
    /**
     * Handle container status update from Port Sudan
     */
    private function handleContainerStatusUpdate($data)
    {
        if (!isset($data['china_loading_id'])) {
            throw new \Exception('Missing china_loading_id in webhook data');
        }
        
        $loadingId = $data['china_loading_id'];
        $loading = $this->loadingModel->find($loadingId);
        
        if (!$loading) {
            throw new \Exception('Loading not found: ' . $loadingId);
        }
        
        $updateData = [];
        if (isset($data['status'])) {
            $updateData['status'] = $this->mapPortSudanStatus($data['status']);
        }
        
        if (!empty($updateData)) {
            $this->loadingModel->update($loadingId, $updateData);
        }
        
        return ['success' => true, 'message' => 'Status updated successfully'];
    }
    
    /**
     * Handle BOL issued notification
     */
    private function handleBolIssued($data)
    {
        if (!isset($data['china_loading_id'])) {
            throw new \Exception('Missing china_loading_id in webhook data');
        }
        
        $loadingId = $data['china_loading_id'];
        
        // Log BOL issued event
        $this->db->query(
            "INSERT INTO loading_sync_log (loading_id, action, status, request_data) 
            VALUES (?, 'bol_issued', 'success', ?)",
            [$loadingId, json_encode($data)]
        );
        
        return ['success' => true, 'message' => 'BOL status recorded'];
    }
    
    /**
     * Handle container arrived notification
     */
    private function handleContainerArrived($data)
    {
        if (!isset($data['china_loading_id'])) {
            throw new \Exception('Missing china_loading_id in webhook data');
        }
        
        $loadingId = $data['china_loading_id'];
        $updateData = [
            'status' => 'arrived',
            'arrival_date' => $data['arrival_date'] ?? date('Y-m-d')
        ];
        
        $this->loadingModel->update($loadingId, $updateData);
        
        return ['success' => true, 'message' => 'Arrival status updated'];
    }
    
    /**
     * Map Port Sudan status to China status
     */
    private function mapPortSudanStatus($portSudanStatus)
    {
        $statusMap = [
            'At Port' => 'arrived',
            'Customs Cleared' => 'cleared',
            'Delivered' => 'cleared',
            'Empty Returned' => 'cleared'
        ];
        
        return $statusMap[$portSudanStatus] ?? 'pending';
    }
}
