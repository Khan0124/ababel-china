<?php
// app/Views/reports/cashbox.php
include __DIR__ . '/../layouts/header.php';
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h1><?= __('reports.cashbox_report') ?></h1>
        <div>
            <button class="btn btn-danger" onclick="window.print()">
                <i class="bi bi-printer"></i> <?= __('print') ?>
            </button>
            <button class="btn btn-success" onclick="exportToExcel('report-content', 'cashbox-report')">
                <i class="bi bi-file-excel"></i> <?= __('export') ?> Excel
            </button>
        </div>
    </div>
    
    <!-- Date Range Filter -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="/reports/cashbox" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?= __('from') ?></label>
                    <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= __('to') ?></label>
                    <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> <?= __('reports.generate') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="report-content">
        <!-- Report Header (for print) -->
        <div class="text-center mb-4 d-none d-print-block">
            <img src="/assets/images/logo.png" alt="Logo" style="max-height: 80px;" class="mb-3">
            <h2><?= __('company_name') ?></h2>
            <h3><?= __('reports.cashbox_report') ?></h3>
            <p><?= __('reports.date_range') ?>: <?= $startDate ?> - <?= $endDate ?></p>
        </div>
        
        <!-- Current Balance -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= __('cashbox.current_balance') ?></h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h4>RMB</h4>
                        <h3 class="<?= $currentBalance['balance_rmb'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            ¥<?= number_format($currentBalance['balance_rmb'] ?? 0, 2) ?>
                        </h3>
                    </div>
                    <div class="col-md-3">
                        <h4>USD</h4>
                        <h3 class="<?= $currentBalance['balance_usd'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            $<?= number_format($currentBalance['balance_usd'] ?? 0, 2) ?>
                        </h3>
                    </div>
                    <div class="col-md-3">
                        <h4>SDG</h4>
                        <h3 class="<?= $currentBalance['balance_sdg'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($currentBalance['balance_sdg'] ?? 0, 2) ?>
                        </h3>
                    </div>
                    <div class="col-md-3">
                        <h4>AED</h4>
                        <h3 class="<?= $currentBalance['balance_aed'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($currentBalance['balance_aed'] ?? 0, 2) ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Movement Summary by Category -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?= __('reports.movement_by_category') ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?= __('cashbox.category') ?></th>
                                <th><?= __('cashbox.movement_type') ?></th>
                                <th><?= __('reports.transactions') ?></th>
                                <th>RMB</th>
                                <th>USD</th>
                                <th>SDG</th>
                                <th>AED</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalIn = ['rmb' => 0, 'usd' => 0, 'sdg' => 0, 'aed' => 0];
                            $totalOut = ['rmb' => 0, 'usd' => 0, 'sdg' => 0, 'aed' => 0];
                            
                            foreach ($categorySummary as $row): 
                                if ($row['movement_type'] == 'in') {
                                    $totalIn['rmb'] += $row['total_rmb'];
                                    $totalIn['usd'] += $row['total_usd'];
                                    $totalIn['sdg'] += $row['total_sdg'];
                                    $totalIn['aed'] += $row['total_aed'];
                                } else {
                                    $totalOut['rmb'] += $row['total_rmb'];
                                    $totalOut['usd'] += $row['total_usd'];
                                    $totalOut['sdg'] += $row['total_sdg'];
                                    $totalOut['aed'] += $row['total_aed'];
                                }
                            ?>
                            <tr>
                                <td><?= __('cashbox.' . $row['category']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $row['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                        <?= __('cashbox.' . $row['movement_type']) ?>
                                    </span>
                                </td>
                                <td><?= $row['count'] ?></td>
                                <td class="text-end <?= $row['movement_type'] == 'in' ? 'text-success' : 'text-danger' ?>">
                                    <?= $row['movement_type'] == 'in' ? '+' : '-' ?>¥<?= number_format(abs($row['total_rmb']), 2) ?>
                                </td>
                                <td class="text-end <?= $row['movement_type'] == 'in' ? 'text-success' : 'text-danger' ?>">
                                    <?php if ($row['total_usd'] != 0): ?>
                                        <?= $row['movement_type'] == 'in' ? '+' : '-' ?>$<?= number_format(abs($row['total_usd']), 2) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end <?= $row['movement_type'] == 'in' ? 'text-success' : 'text-danger' ?>">
                                    <?php if ($row['total_sdg'] != 0): ?>
                                        <?= $row['movement_type'] == 'in' ? '+' : '-' ?><?= number_format(abs($row['total_sdg']), 2) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end <?= $row['movement_type'] == 'in' ? 'text-success' : 'text-danger' ?>">
                                    <?php if ($row['total_aed'] != 0): ?>
                                        <?= $row['movement_type'] == 'in' ? '+' : '-' ?><?= number_format(abs($row['total_aed']), 2) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-success">
                                <th colspan="3"><?= __('reports.total_in') ?></th>
                                <th class="text-end">+¥<?= number_format($totalIn['rmb'], 2) ?></th>
                                <th class="text-end">+$<?= number_format($totalIn['usd'], 2) ?></th>
                                <th class="text-end">+<?= number_format($totalIn['sdg'], 2) ?></th>
                                <th class="text-end">+<?= number_format($totalIn['aed'], 2) ?></th>
                            </tr>
                            <tr class="table-danger">
                                <th colspan="3"><?= __('reports.total_out') ?></th>
                                <th class="text-end">-¥<?= number_format($totalOut['rmb'], 2) ?></th>
                                <th class="text-end">-$<?= number_format($totalOut['usd'], 2) ?></th>
                                <th class="text-end">-<?= number_format($totalOut['sdg'], 2) ?></th>
                                <th class="text-end">-<?= number_format($totalOut['aed'], 2) ?></th>
                            </tr>
                            <tr class="table-primary">
                                <th colspan="3"><?= __('reports.net_change') ?></th>
                                <th class="text-end">¥<?= number_format($totalIn['rmb'] - $totalOut['rmb'], 2) ?></th>
                                <th class="text-end">$<?= number_format($totalIn['usd'] - $totalOut['usd'], 2) ?></th>
                                <th class="text-end"><?= number_format($totalIn['sdg'] - $totalOut['sdg'], 2) ?></th>
                                <th class="text-end"><?= number_format($totalIn['aed'] - $totalOut['aed'], 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Daily Cash Flow Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?= __('reports.daily_cash_flow') ?></h5>
            </div>
            <div class="card-body">
                <canvas id="cashflowChart" height="80"></canvas>
            </div>
        </div>
        
        <!-- Daily Balance Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= __('reports.daily_balance_changes') ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th><?= __('date') ?></th>
                                <th><?= __('reports.change') ?> (RMB)</th>
                                <th><?= __('reports.change') ?> (USD)</th>
                                <th><?= __('reports.running_balance') ?> (RMB)</th>
                                <th><?= __('reports.running_balance') ?> (USD)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $runningRMB = 0;
                            $runningUSD = 0;
                            foreach ($dailyBalances as $day): 
                                $runningRMB += $day['daily_change_rmb'];
                                $runningUSD += $day['daily_change_usd'];
                            ?>
                            <tr>
                                <td><?= $day['movement_date'] ?></td>
                                <td class="<?= $day['daily_change_rmb'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $day['daily_change_rmb'] >= 0 ? '+' : '' ?><?= number_format($day['daily_change_rmb'], 2) ?>
                                </td>
                                <td class="<?= $day['daily_change_usd'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $day['daily_change_usd'] >= 0 ? '+' : '' ?><?= number_format($day['daily_change_usd'], 2) ?>
                                </td>
                                <td>¥<?= number_format($runningRMB, 2) ?></td>
                                <td>$<?= number_format($runningUSD, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Report Footer -->
        <div class="mt-4 text-center">
            <p class="text-muted">
                <?= __('reports.generated') ?>: <?= date('Y-m-d H:i:s') ?> | 
                <?= __('nav.profile') ?>: <?= $_SESSION['user_name'] ?? '-' ?>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Cash flow chart
const ctx = document.getElementById('cashflowChart').getContext('2d');
const dailyData = <?= json_encode($dailyBalances) ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: dailyData.map(d => d.movement_date),
        datasets: [{
            label: 'RMB',
            data: dailyData.map(d => d.daily_change_rmb),
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            tension: 0.1
        }, {
            label: 'USD',
            data: dailyData.map(d => d.daily_change_usd),
            borderColor: 'rgb(54, 162, 235)',
            backgroundColor: 'rgba(54, 162, 235, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        page-break-inside: avoid;
    }
    
    #cashflowChart {
        max-height: 300px !important;
    }
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>