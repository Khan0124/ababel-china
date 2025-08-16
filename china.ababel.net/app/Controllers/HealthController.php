<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\ErrorRecovery;

class HealthController extends Controller
{
    public function check()
    {
        $recovery = ErrorRecovery::getInstance();
        $status = $recovery->healthCheck();
        
        // Set appropriate HTTP status code
        if (!$status['overall']) {
            http_response_code(503); // Service Unavailable
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status['overall'] ? 'healthy' : 'degraded',
            'timestamp' => date('Y-m-d H:i:s'),
            'components' => $status,
            'version' => '1.0.0'
        ]);
        exit;
    }
    
    public function maintenance()
    {
        $recovery = ErrorRecovery::getInstance();
        $reason = $_POST['reason'] ?? 'Scheduled maintenance';
        
        // Check if user has admin privileges (simplified check)
        if (!isset($_SESSION['user_id']) || !$this->isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        $recovery->emergencyShutdown($reason);
    }
    
    private function isAdmin()
    {
        // Simplified admin check - in production, implement proper role checking
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}