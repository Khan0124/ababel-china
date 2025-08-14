<?php
// bin/sync-loadings.php
#!/usr/bin/env php
<?php
/**
 * CLI script for syncing loadings to Port Sudan
 * Usage: php bin/sync-loadings.php [--all] [--loading-id=123] [--retry-failed]
 */

// Set up environment
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/app/Core/bootstrap.php';

use App\Services\SyncService;
use App\Models\Loading;

// Parse command line arguments
$options = getopt('', ['all', 'loading-id:', 'retry-failed', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

try {
    $syncService = new SyncService();
    
    if (isset($options['loading-id'])) {
        // Sync specific loading
        $loadingId = intval($options['loading-id']);
        echo "Syncing loading ID: $loadingId\n";
        
        $result = $syncService->syncLoadingToPortSudan($loadingId);
        
        if ($result['success']) {
            echo "✅ Success: " . $result['message'] . "\n";
        } else {
            echo "❌ Failed: " . $result['message'] . "\n";
            exit(1);
        }
        
    } elseif (isset($options['retry-failed'])) {
        // Retry all failed syncs
        echo "Retrying failed synchronizations...\n";
        
        $failedLoadings = getFailedSyncLoadings();
        $retryCount = 0;
        $successCount = 0;
        
        foreach ($failedLoadings as $loading) {
            $retryCount++;
            echo "Retrying loading {$loading['id']} ({$retryCount}/" . count($failedLoadings) . ")... ";
            
            try {
                $result = $syncService->retryFailedSync($loading['id']);
                if ($result['success']) {
                    echo "✅ Success\n";
                    $successCount++;
                } else {
                    echo "❌ Failed: " . $result['message'] . "\n";
                }
            } catch (Exception $e) {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\nRetry Summary:\n";
        echo "- Total retries: $retryCount\n";
        echo "- Successful: $successCount\n";
        echo "- Failed: " . ($retryCount - $successCount) . "\n";
        
    } elseif (isset($options['all'])) {
        // Sync all pending loadings
        echo "Syncing all pending Port Sudan loadings...\n";
        
        $results = $syncService->syncPendingLoadings();
        $successCount = 0;
        $failCount = 0;
        
        foreach ($results as $result) {
            $status = $result['status'] === 'success' ? '✅' : '❌';
            echo "{$status} Loading {$result['loading_id']}: {$result['message']}\n";
            
            if ($result['status'] === 'success') {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        
        echo "\nSync Summary:\n";
        echo "- Total loadings: " . count($results) . "\n";
        echo "- Successful: $successCount\n";
        echo "- Failed: $failCount\n";
        
    } else {
        // Show status by default
        showSyncStatus($syncService);
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

function showHelp()
{
    echo "Loading Synchronization Tool\n";
    echo "============================\n\n";
    echo "Usage: php bin/sync-loadings.php [options]\n\n";
    echo "Options:\n";
    echo "  --all                    Sync all pending Port Sudan loadings\n";
    echo "  --loading-id=ID          Sync specific loading by ID\n";
    echo "  --retry-failed           Retry all previously failed syncs\n";
    echo "  --help                   Show this help message\n\n";
    echo "Examples:\n";
    echo "  php bin/sync-loadings.php --all\n";
    echo "  php bin/sync-loadings.php --loading-id=123\n";
    echo "  php bin/sync-loadings.php --retry-failed\n";
}

function showSyncStatus($syncService)
{
    echo "Port Sudan Sync Status\n";
    echo "=====================\n\n";
    
    // Get statistics for last 7 days
    $stats = $syncService->getSyncStatistics(date('Y-m-d', strtotime('-7 days')));
    
    echo "Last 7 Days Statistics:\n";
    echo "- Total sync attempts: " . ($stats['total_attempts'] ?? 0) . "\n";
    echo "- Successful syncs: " . ($stats['successful'] ?? 0) . "\n";
    echo "- Failed syncs: " . ($stats['failed'] ?? 0) . "\n";
    echo "- Success rate: " . number_format($stats['success_rate'] ?? 0, 1) . "%\n";
    echo "- Unique loadings: " . ($stats['unique_loadings'] ?? 0) . "\n\n";
    
    // Show pending loadings
    $loadingModel = new Loading();
    $pendingLoadings = $loadingModel->getPendingPortSudanSync();
    
    echo "Pending Port Sudan Syncs: " . count($pendingLoadings) . "\n";
    
    if (!empty($pendingLoadings)) {
        echo "\nPending Loadings:\n";
        foreach (array_slice($pendingLoadings, 0, 10) as $loading) {
            echo "- ID: {$loading['id']}, Container: {$loading['container_no']}, Date: {$loading['shipping_date']}\n";
        }
        
        if (count($pendingLoadings) > 10) {
            echo "... and " . (count($pendingLoadings) - 10) . " more\n";
        }
        
        echo "\nRun with --all to sync all pending loadings\n";
    }
}

function getFailedSyncLoadings()
{
    $db = \App\Core\Database::getInstance();
    
    $sql = "SELECT DISTINCT l.* 
            FROM loadings l 
            INNER JOIN api_sync_log asl ON l.id = asl.china_loading_id 
            WHERE l.office = 'port_sudan' 
            AND asl.response_code >= 400
            AND asl.id = (
                SELECT MAX(id) FROM api_sync_log 
                WHERE china_loading_id = l.id
            )
            ORDER BY l.created_at DESC";
    
    $stmt = $db->query($sql);
    return $stmt->fetchAll();
}

/**
 * Cron job setup instructions:
 * 
 * Add to crontab to run every 5 minutes:
 * */5 * * * * /usr/bin/php /path/to/your/project/bin/sync-loadings.php --all >> /var/log/ababel-sync.log 2>&1
 * 
 * Add to crontab to retry failed syncs daily:
 * 0 2 * * * /usr/bin/php /path/to/your/project/bin/sync-loadings.php --retry-failed >> /var/log/ababel-sync.log 2>&1
 */