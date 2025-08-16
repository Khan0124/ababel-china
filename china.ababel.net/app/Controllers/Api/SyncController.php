<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Services\SyncService;

class SyncController extends Controller
{
    private $syncService;
    
    public function __construct()
    {
        parent::__construct();
        $this->syncService = new SyncService();
        header('Content-Type: application/json');
    }
    
    public function retry($loadingId)
    {
        try {
            if (!$loadingId || !is_numeric($loadingId)) {
                throw new \Exception('Invalid loading ID');
            }
            
            $result = $this->syncService->retryFailedSync($loadingId);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'loading_id' => $loadingId
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function status($loadingId)
    {
        try {
            if (!$loadingId || !is_numeric($loadingId)) {
                throw new \Exception('Invalid loading ID');
            }
            
            $syncLog = $this->syncService->getLoadingSyncStatus($loadingId);
            $lastSync = $syncLog[0] ?? null;
            
            $status = 'pending';
            if ($lastSync) {
                $status = $lastSync['response_code'] >= 200 && $lastSync['response_code'] < 300 ? 'success' : 'failed';
            }
            
            echo json_encode([
                'success' => true,
                'loading_id' => $loadingId,
                'sync_status' => $status,
                'last_sync' => $lastSync ? $lastSync['created_at'] : null,
                'sync_attempts' => count($syncLog),
                'sync_log' => $syncLog
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function syncAll()
    {
        try {
            $results = $this->syncService->syncPendingLoadings();
            
            $successCount = count(array_filter($results, function($r) { 
                return $r['status'] === 'success'; 
            }));
            $totalCount = count($results);
            
            echo json_encode([
                'success' => true,
                'message' => "Synced $successCount of $totalCount loadings successfully",
                'total' => $totalCount,
                'successful' => $successCount,
                'failed' => $totalCount - $successCount,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function statistics()
    {
        try {
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $stats = $this->syncService->getSyncStatistics($dateFrom, $dateTo);
            
            echo json_encode([
                'success' => true,
                'statistics' => $stats,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function webhook()
    {
        try {
            $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
            $validApiKey = config('webhook_api_key', 'AB@1234X-China2Port!');
            
            if ($apiKey !== $validApiKey) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid API key']);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new \Exception('Invalid JSON data');
            }
            
            $result = $this->syncService->handlePortSudanWebhook($input);
            
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function testConnection()
    {
        try {
            $testData = [
                'test' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'source' => 'china_system'
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => config('port_sudan_api_url') . '/test',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($testData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-API-Key: ' . config('port_sudan_api_key')
                ],
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("Connection error: $error");
            }
            
            $responseData = json_decode($response, true);
            
            echo json_encode([
                'success' => $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'response' => $responseData,
                'message' => $httpCode >= 200 && $httpCode < 300 ? 'Connection successful' : 'Connection failed'
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function syncLoading($loadingId)
    {
        try {
            if (!$loadingId || !is_numeric($loadingId)) {
                throw new \Exception('Invalid loading ID');
            }
            
            error_log("Starting manual sync for loading: $loadingId");
            $result = $this->syncService->syncLoadingToPortSudan($loadingId);
            
            echo json_encode([
                'success' => $result['success'],
                'message' => $result['message'],
                'loading_id' => $loadingId,
                'port_sudan_container_id' => $result['port_sudan_container_id'] ?? null
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function updateBol($loadingId)
    {
        try {
            if (!$loadingId || !is_numeric($loadingId)) {
                throw new \Exception('Invalid loading ID');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['bill_of_lading_status'])) {
                throw new \Exception('BOL status is required');
            }
            
            $bolData = [
                'bill_of_lading_status' => $input['bill_of_lading_status'],
                'bill_of_lading_date' => $input['bill_of_lading_date'] ?? date('Y-m-d')
            ];
            
            if (isset($input['bill_of_lading_file'])) {
                $bolData['bill_of_lading_file'] = $input['bill_of_lading_file'];
            }
            
            $loadingModel = new \App\Models\Loading();
            $loadingModel->updateBolStatus(
                $loadingId, 
                $bolData['bill_of_lading_status'], 
                $bolData['bill_of_lading_date'], 
                $bolData['bill_of_lading_file'] ?? null
            );
            
            $syncResult = $this->syncService->syncBolToPortSudan($loadingId, $bolData);
            
            echo json_encode([
                'success' => true,
                'message' => 'BOL status updated and synced successfully',
                'loading_id' => $loadingId,
                'sync_success' => $syncResult
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}