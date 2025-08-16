<?php include '../layouts/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1><?= __('system_monitor') ?></h1>
            
            <!-- System Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5><?= __('active_users') ?></h5>
                            <h2><?= $stats['active_users'] ?></h2>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5><?= __('total_transactions') ?></h5>
                            <h2><?= number_format($stats['total_transactions']) ?></h2>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5><?= __('total_clients') ?></h5>
                            <h2><?= number_format($stats['total_clients']) ?></h2>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5><?= __('database_size') ?></h5>
                            <h2><?= $stats['database_size'] ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><?= __('system_information') ?></h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>PHP Version:</strong></td>
                                    <td><?= $stats['php_version'] ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Server Software:</strong></td>
                                    <td><?= $stats['server_software'] ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Memory Usage:</strong></td>
                                    <td><?= $stats['memory_usage']['current'] ?> (Peak: <?= $stats['memory_usage']['peak'] ?>)</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><?= __('disk_usage') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-2">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?= $stats['disk_usage']['percentage'] ?>%">
                                    <?= $stats['disk_usage']['percentage'] ?>%
                                </div>
                            </div>
                            <small>
                                <?= __('used') ?>: <?= $stats['disk_usage']['used'] ?> / 
                                <?= __('total') ?>: <?= $stats['disk_usage']['total'] ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><?= __('quick_actions') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-primary" onclick="clearCache()">
                                    <?= __('clear_cache') ?>
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="runBackup()">
                                    <?= __('run_backup') ?>
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="optimizeDatabase()">
                                    <?= __('optimize_database') ?>
                                </button>
                                <a href="/system/logs" class="btn btn-outline-info">
                                    <?= __('view_logs') ?>
                                </a>
                                <a href="/system/performance" class="btn btn-outline-secondary">
                                    <?= __('performance_metrics') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function clearCache() {
    if (confirm('<?= __('confirm_clear_cache') ?>')) {
        fetch('/system/clear-cache', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        }).then(() => {
            location.reload();
        });
    }
}

function runBackup() {
    if (confirm('<?= __('confirm_run_backup') ?>')) {
        fetch('/system/run-backup', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        }).then(() => {
            alert('<?= __('backup_started') ?>');
            setTimeout(() => location.reload(), 2000);
        });
    }
}

function optimizeDatabase() {
    if (confirm('<?= __('confirm_optimize_database') ?>')) {
        fetch('/system/optimize-database', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        }).then(() => {
            alert('<?= __('database_optimized') ?>');
            location.reload();
        });
    }
}
</script>

<?php include '../layouts/footer.php'; ?>