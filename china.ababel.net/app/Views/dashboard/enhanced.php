<?php
/**
 * Enhanced Dashboard with Real-time KPIs and Analytics
 * Immediate Implementation - Phase 1
 */
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= isRTL() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - Ababel Logistics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-card { transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-5px); }
        .metric-value { font-size: 2.5rem; font-weight: bold; }
        .metric-change { font-size: 0.9rem; }
        .risk-low { color: #198754; }
        .risk-medium { color: #fd7e14; }
        .risk-high { color: #dc3545; }
        .chart-container { height: 300px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header with Real-time Status -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0"><?= __('Enhanced Business Dashboard') ?></h1>
                        <small class="text-muted">Real-time Business Intelligence | Last Updated: <span id="lastUpdate"><?= date('H:i:s') ?></span></small>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-success me-2">
                            <i class="bi bi-circle-fill"></i> System Health: <?= $overview['system_health'] ?>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshDashboard()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Overview Cards -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center">
                        <div class="text-primary">
                            <i class="bi bi-people-fill" style="font-size: 2rem;"></i>
                        </div>
                        <div class="metric-value text-primary"><?= number_format($overview['total_clients']) ?></div>
                        <div class="text-muted">Active Clients</div>
                        <div class="metric-change text-success">
                            <i class="bi bi-arrow-up"></i> +<?= $overview['client_growth'] ?> this month
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center">
                        <div class="text-success">
                            <i class="bi bi-currency-exchange" style="font-size: 2rem;"></i>
                        </div>
                        <div class="metric-value text-success"><?= number_format($overview['total_transactions']) ?></div>
                        <div class="text-muted">Total Transactions</div>
                        <div class="metric-change text-warning">
                            <i class="bi bi-clock"></i> <?= $overview['pending_transactions'] ?> pending
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center">
                        <div class="text-info">
                            <i class="bi bi-wallet2" style="font-size: 2rem;"></i>
                        </div>
                        <div class="metric-value text-info">¥<?= number_format($financial_metrics['current_balance']['balance_rmb'] ?? 0, 0) ?></div>
                        <div class="text-muted">Cash Balance (RMB)</div>
                        <div class="metric-change <?= ($financial_metrics['cash_flow_trend'] == 'positive') ? 'text-success' : 'text-danger' ?>">
                            <i class="bi bi-graph-up"></i> <?= ucfirst($financial_metrics['cash_flow_trend']) ?> trend
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center">
                        <div class="text-warning">
                            <i class="bi bi-speedometer2" style="font-size: 2rem;"></i>
                        </div>
                        <div class="metric-value text-warning"><?= $performance_indicators['transaction_velocity'] ?></div>
                        <div class="text-muted">Trans/Day Avg</div>
                        <div class="metric-change text-info">
                            <i class="bi bi-lightning"></i> <?= $performance_indicators['processing_efficiency'] ?>% efficiency
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center">
                        <div class="text-danger">
                            <i class="bi bi-shield-exclamation" style="font-size: 2rem;"></i>
                        </div>
                        <div class="metric-value risk-<?= strtolower($risk_analysis['overall_risk_level']) ?>"><?= $risk_analysis['overall_risk_level'] ?></div>
                        <div class="text-muted">Risk Level</div>
                        <div class="metric-change text-muted">
                            <i class="bi bi-person-x"></i> <?= $risk_analysis['high_risk_clients'] ?> high-risk clients
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center">
                        <div class="text-secondary">
                            <i class="bi bi-graph-up-arrow" style="font-size: 2rem;"></i>
                        </div>
                        <div class="metric-value text-secondary">¥<?= number_format($financial_metrics['monthly_revenue'], 0) ?></div>
                        <div class="text-muted">Monthly Revenue</div>
                        <div class="metric-change text-success">
                            <i class="bi bi-percent"></i> <?= $financial_metrics['profit_margin'] ?>% margin
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Transaction Volume Trends</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="chartPeriod" id="week" autocomplete="off" checked>
                            <label class="btn btn-outline-primary" for="week">7D</label>
                            <input type="radio" class="btn-check" name="chartPeriod" id="month" autocomplete="off">
                            <label class="btn btn-outline-primary" for="month">30D</label>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="transactionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Currency Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="currencyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client Analytics & Risk Assessment Row -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Top Performing Clients</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Transactions</th>
                                        <th>Volume (RMB)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($client_analytics['top_clients'], 0, 5) as $client): ?>
                                    <tr>
                                        <td>
                                            <strong><?= h($client['name'] ?? 'N/A') ?></strong><br>
                                            <small class="text-muted"><?= h($client['client_code'] ?? '') ?></small>
                                        </td>
                                        <td><span class="badge bg-primary"><?= $client['transaction_count'] ?? 0 ?></span></td>
                                        <td>¥<?= number_format($client['total_volume'] ?? 0, 0) ?></td>
                                        <td>
                                            <?php 
                                            $balance = $client['balance_rmb'] ?? 0;
                                            if ($balance > 0): ?>
                                                <span class="badge bg-success">Credit: ¥<?= number_format($balance, 0) ?></span>
                                            <?php elseif ($balance < 0): ?>
                                                <span class="badge bg-warning">Debt: ¥<?= number_format(abs($balance), 0) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Balanced</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Risk Assessment Dashboard</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="text-center">
                                    <div class="display-6 risk-<?= strtolower($risk_analysis['overall_risk_level']) ?>">
                                        <?= $risk_analysis['credit_utilization'] ?>%
                                    </div>
                                    <small class="text-muted">Credit Utilization</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-center">
                                    <div class="display-6 text-danger">
                                        <?= $risk_analysis['high_risk_clients'] ?>
                                    </div>
                                    <small class="text-muted">High Risk Clients</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="progress mb-3" style="height: 10px;">
                            <div class="progress-bar 
                                <?= $risk_analysis['credit_utilization'] > 80 ? 'bg-danger' : 
                                   ($risk_analysis['credit_utilization'] > 60 ? 'bg-warning' : 'bg-success') ?>" 
                                 style="width: <?= min($risk_analysis['credit_utilization'], 100) ?>%">
                            </div>
                        </div>

                        <div class="alert alert-<?= $risk_analysis['overall_risk_level'] == 'HIGH' ? 'danger' : 
                                                 ($risk_analysis['overall_risk_level'] == 'MEDIUM' ? 'warning' : 'success') ?> mb-0">
                            <strong>Risk Status:</strong> <?= $risk_analysis['overall_risk_level'] ?><br>
                            <small>
                                <?php if ($risk_analysis['overall_risk_level'] == 'HIGH'): ?>
                                    Immediate attention required. Review high-risk client accounts.
                                <?php elseif ($risk_analysis['overall_risk_level'] == 'MEDIUM'): ?>
                                    Monitor closely. Consider credit limit adjustments.
                                <?php else: ?>
                                    Portfolio is performing well within acceptable risk parameters.
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-time Exchange Rates & Recent Activities -->
        <div class="row mb-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-currency-exchange"></i> Live Exchange Rates</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($currency_rates as $pair => $rate): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong><?= str_replace('_', '/', $pair) ?></strong>
                                <br><small class="text-muted"><?= $rate['date'] ?></small>
                            </div>
                            <div class="text-end">
                                <div class="h5 mb-0"><?= number_format($rate['rate'], 4) ?></div>
                            </div>
                        </div>
                        <hr class="my-2">
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <button class="btn btn-sm btn-outline-primary" onclick="updateRates()">
                                <i class="bi bi-arrow-clockwise"></i> Update Rates
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-activity"></i> Recent System Activities</h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="d-flex align-items-start mb-3">
                            <div class="flex-shrink-0">
                                <div class="bg-<?= $activity['status'] == 'approved' ? 'success' : 'warning' ?> rounded-circle p-2 text-white">
                                    <i class="bi bi-<?= $activity['type'] == 'transaction' ? 'currency-exchange' : 'activity' ?>"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-semibold"><?= h($activity['title']) ?></div>
                                <div class="text-muted small"><?= h($activity['description']) ?></div>
                                <div class="text-muted small">
                                    <i class="bi bi-clock"></i> <?= date('M j, H:i', strtotime($activity['timestamp'])) ?>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="badge bg-<?= $activity['status'] == 'approved' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($activity['status']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Metrics Footer -->
        <div class="row">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="h4 text-success"><?= $performance_indicators['system_uptime'] ?>%</div>
                                <small class="text-muted">System Uptime</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h4 text-primary"><?= $performance_indicators['processing_efficiency'] ?>%</div>
                                <small class="text-muted">Processing Efficiency</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h4 text-info"><?= $performance_indicators['user_satisfaction'] ?>%</div>
                                <small class="text-muted">User Satisfaction</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h4 text-<?= $performance_indicators['error_rate'] < 1 ? 'success' : 'warning' ?>"><?= $performance_indicators['error_rate'] ?>%</div>
                                <small class="text-muted">Error Rate</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Charts
        const transactionCtx = document.getElementById('transactionChart').getContext('2d');
        const transactionChart = new Chart(transactionCtx, {
            type: 'line',
            data: {
                labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'],
                datasets: [{
                    label: 'Transaction Volume (RMB)',
                    data: [12000, 19000, 15000, 25000, 22000, 30000, 28000],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        const currencyCtx = document.getElementById('currencyChart').getContext('2d');
        const currencyChart = new Chart(currencyCtx, {
            type: 'doughnut',
            data: {
                labels: ['RMB', 'USD', 'SDG', 'AED'],
                datasets: [{
                    data: [65, 20, 10, 5],
                    backgroundColor: ['#dc3545', '#0d6efd', '#198754', '#fd7e14']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Auto-refresh dashboard every 5 minutes
        setInterval(refreshDashboard, 300000);

        function refreshDashboard() {
            document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
            // In real implementation, fetch new data via AJAX
        }

        function updateRates() {
            // Simulate rate update
            alert('Exchange rates updated successfully!');
        }
    </script>
</body>
</html>