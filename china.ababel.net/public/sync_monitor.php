<?php
/**
 * Sync Monitor Dashboard
 * Place this file in China system to monitor sync status
 */

session_start();
$config = require __DIR__ . '/../config/database.php';

// Get database connection
$db = new mysqli("localhost", "china_ababel", "Khan@70990100", "china_ababel");

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}


// Get statistics
function getSyncStats($db) {
    $stats = [
        'total_port_sudan' => 0,
        'synced' => 0,
        'pending' => 0,
        'failed' => 0,
        'success_rate' => 0
    ];
    
    // Total loadings for Port Sudan
    $result = $db->query("SELECT COUNT(*) as count FROM loadings WHERE office = 'port_sudan'");
    if ($row = $result->fetch_assoc()) {
        $stats['total_port_sudan'] = $row['count'];
    }
    
    // Sync status breakdown
    $result = $db->query("
        SELECT sync_status, COUNT(*) as count 
        FROM loadings 
        WHERE office = 'port_sudan' 
        GROUP BY sync_status
    ");
    while ($row = $result->fetch_assoc()) {
        if ($row['sync_status'] == 'synced') $stats['synced'] = $row['count'];
        if ($row['sync_status'] == 'pending') $stats['pending'] = $row['count'];
        if ($row['sync_status'] == 'failed') $stats['failed'] = $row['count'];
    }
    
    // Calculate success rate
    if ($stats['total_port_sudan'] > 0) {
        $stats['success_rate'] = round(($stats['synced'] / $stats['total_port_sudan']) * 100, 2);
    }
    
    return $stats;
}

// Get recent sync activities
function getRecentActivities($db, $limit = 20) {
    $activities = [];
    
    $sql = "
        SELECT 
            l.id,
            l.loading_no,
            l.container_no,
            l.client_name,
            l.sync_status,
            l.sync_attempts,
            l.last_sync_at,
            l.created_at,
            CASE 
                WHEN l.sync_status = 'synced' THEN 'success'
                WHEN l.sync_status = 'failed' AND l.sync_attempts >= 3 THEN 'danger'
                WHEN l.sync_status = 'failed' THEN 'warning'
                ELSE 'info'
            END as status_class
        FROM loadings l
        WHERE l.office = 'port_sudan'
        ORDER BY 
            CASE WHEN l.sync_status = 'failed' THEN 0 ELSE 1 END,
            l.created_at DESC
        LIMIT ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    return $activities;
}

// Handle retry action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['retry_sync'])) {
    $loadingId = intval($_POST['loading_id']);
    
    // Get loading data
    $stmt = $db->prepare("SELECT * FROM loadings WHERE id = ? AND office = 'port_sudan'");
    $stmt->bind_param("i", $loadingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($loading = $result->fetch_assoc()) {
        // Include required files for SyncService
        require_once __DIR__ . '/../app/Core/Database.php';
        require_once __DIR__ . '/../app/Core/Model.php'; 
        require_once __DIR__ . '/../app/Models/Loading.php';
        require_once __DIR__ . '/../app/Services/SyncService.php';
        
        // Call sync service
        $syncService = new \App\Services\SyncService();
        
        try {
            $syncResult = $syncService->syncLoadingToPortSudan($loading);
            if ($syncResult['success']) {
                $_SESSION['success_message'] = "Loading {$loading['loading_no']} synced successfully!";
            } else {
                $_SESSION['error_message'] = "Sync failed: " . $syncResult['message'];
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Sync error: " . $e->getMessage();
        }
    }
    
    header("Location: sync_monitor.php");
    exit;
}

$stats = getSyncStats($db);
$activities = getRecentActivities($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>China-Port Sudan Sync Monitor</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        /* Header */
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .header h1 { 
            font-size: 24px; 
            margin-bottom: 10px;
            color: #1a1a1a;
        }
        .header p { color: #666; }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-card.success .value { color: #4caf50; }
        .stat-card.warning .value { color: #ff9800; }
        .stat-card.danger .value { color: #f44336; }
        .stat-card.info .value { color: #2196f3; }
        
        /* Table */
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-header h2 { font-size: 20px; }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: #666;
            border-bottom: 2px solid #eee;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        tr:hover { background: #f8f9fa; }
        
        /* Status badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge.success { background: #e8f5e9; color: #2e7d32; }
        .badge.warning { background: #fff3e0; color: #ef6c00; }
        .badge.danger { background: #ffebee; color: #c62828; }
        .badge.info { background: #e3f2fd; color: #1565c0; }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #2196f3;
            color: white;
        }
        .btn-primary:hover { background: #1976d2; }
        .btn-sm { padding: 4px 12px; font-size: 12px; }
        
        /* Messages */
        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; }
        
        /* Auto refresh */
        .refresh-info {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 10px 20px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 China-Port Sudan Sync Monitor</h1>
            <p>Real-time monitoring of container synchronization status</p>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                ✓ <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                ✗ <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card info">
                <h3>Total Port Sudan Loadings</h3>
                <div class="value"><?php echo number_format($stats['total_port_sudan']); ?></div>
            </div>
            
            <div class="stat-card success">
                <h3>Successfully Synced</h3>
                <div class="value"><?php echo number_format($stats['synced']); ?></div>
                <small><?php echo $stats['success_rate']; ?>% success rate</small>
            </div>
            
            <div class="stat-card warning">
                <h3>Pending Sync</h3>
                <div class="value"><?php echo number_format($stats['pending']); ?></div>
            </div>
            
            <div class="stat-card danger">
                <h3>Failed Syncs</h3>
                <div class="value"><?php echo number_format($stats['failed']); ?></div>
            </div>
        </div>
        
        <div class="table-container">
            <div class="table-header">
                <h2>Recent Activities</h2>
                <button class="btn btn-primary btn-sm" onclick="location.reload()">↻ Refresh</button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Loading No</th>
                        <th>Container</th>
                        <th>Client</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Last Sync</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td><?php echo $activity['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($activity['loading_no']); ?></strong></td>
                        <td><?php echo htmlspecialchars($activity['container_no']); ?></td>
                        <td><?php echo htmlspecialchars($activity['client_name']); ?></td>
                        <td>
                            <span class="badge <?php echo $activity['status_class']; ?>">
                                <?php echo ucfirst($activity['sync_status']); ?>
                            </span>
                        </td>
                        <td><?php echo $activity['sync_attempts']; ?></td>
                        <td>
                            <?php 
                            if ($activity['last_sync_at']) {
                                echo date('Y-m-d H:i', strtotime($activity['last_sync_at']));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($activity['created_at'])); ?></td>
                        <td>
                            <?php if ($activity['sync_status'] != 'synced'): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="loading_id" value="<?php echo $activity['id']; ?>">
                                <button type="submit" name="retry_sync" class="btn btn-primary btn-sm">
                                    Retry Sync
                                </button>
                            </form>
                            <?php else: ?>
                            <span style="color: #4caf50;">✓ Synced</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="refresh-info">
        <span id="countdown">30</span>s until auto-refresh
    </div>
    
    <script>
        // Auto refresh every 30 seconds
        let countdown = 30;
        const countdownEl = document.getElementById('countdown');
        
        setInterval(() => {
            countdown--;
            countdownEl.textContent = countdown;
            
            if (countdown <= 0) {
                location.reload();
            }
        }, 1000);
        
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
<?php
$db->close();
?>