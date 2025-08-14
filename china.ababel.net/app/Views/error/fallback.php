<?php
/**
 * Fallback error page with limited functionality
 */
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= isRTL() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'System Recovery Mode' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-warning">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="bi bi-exclamation-triangle"></i>
                Ababel Logistics - Recovery Mode
            </span>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 1.5rem;"></i>
                    <div>
                        <h5 class="alert-heading mb-1">System Recovery Mode</h5>
                        <p class="mb-0"><?= $error_message ?? 'The system is running with limited functionality due to technical difficulties.' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Quick Actions -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-lightning"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="/" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-house"></i> Dashboard
                            </a>
                            <a href="/transactions" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-currency-exchange"></i> Transactions
                            </a>
                            <a href="/clients" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-people"></i> Clients
                            </a>
                            <a href="/loadings" class="btn btn-outline-success btn-sm">
                                <i class="bi bi-box-seam"></i> Loadings
                            </a>
                        </div>
                        
                        <hr>
                        
                        <button class="btn btn-outline-danger btn-sm w-100" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Retry
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9">
                <?php if (!empty($clients)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-people-fill"></i> Recent Clients (Cached)
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($clients, 0, 10) as $client): ?>
                                    <tr>
                                        <td><?= h($client['client_code'] ?? '-') ?></td>
                                        <td><?= h($client['name'] ?? '-') ?></td>
                                        <td><?= h($client['phone'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge bg-<?= ($client['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>">
                                                <?= h($client['status'] ?? 'Unknown') ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($transactions)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-currency-exchange"></i> Recent Transactions (Cached)
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Transaction No</th>
                                        <th>Date</th>
                                        <th>Client</th>
                                        <th>Amount (RMB)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($transactions, 0, 10) as $transaction): ?>
                                    <tr>
                                        <td><?= h($transaction['transaction_no'] ?? '-') ?></td>
                                        <td><?= h($transaction['transaction_date'] ?? '-') ?></td>
                                        <td><?= h($transaction['client_name'] ?? '-') ?></td>
                                        <td><?= number_format($transaction['total_amount_rmb'] ?? 0, 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= ($transaction['status'] ?? '') === 'approved' ? 'success' : 'warning' ?>">
                                                <?= h($transaction['status'] ?? 'Unknown') ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- System Status -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-info-circle"></i> System Status
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Current Mode</h6>
                                <p class="text-warning"><i class="bi bi-shield-exclamation"></i> Recovery Mode</p>
                                
                                <h6>Available Features</h6>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-check-circle text-success"></i> View cached data</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Basic navigation</li>
                                    <li><i class="bi bi-x-circle text-danger"></i> Create/Edit operations</li>
                                    <li><i class="bi bi-x-circle text-danger"></i> Real-time data</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Recovery Time</h6>
                                <p><?= date('Y-m-d H:i:s') ?></p>
                                
                                <h6>Actions</h6>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary btn-sm" onclick="checkSystemStatus()">
                                        <i class="bi bi-arrow-clockwise"></i> Check Status
                                    </button>
                                    <a href="mailto:support@ababel.net" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-envelope"></i> Contact Support
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 2 minutes
        setTimeout(() => location.reload(), 120000);
        
        function checkSystemStatus() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Checking...';
            btn.disabled = true;
            
            // Simulate status check
            setTimeout(() => {
                fetch('/health-check')
                    .then(response => response.json())
                    .then(data => {
                        if (data.overall) {
                            location.reload();
                        } else {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            alert('System is still recovering. Please try again in a few minutes.');
                        }
                    })
                    .catch(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        alert('Unable to check system status. Please try again later.');
                    });
            }, 2000);
        }
    </script>
    
    <style>
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .alert {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</body>
</html>