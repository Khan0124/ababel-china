<?php
// app/Views/reports/monthly.php
include __DIR__ . '/../layouts/header.php';
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h1><?= __('reports.monthly_report') ?> - <?= date('F Y', strtotime($month . '-01')) ?></h1>
        <div>
            <button class="btn btn-danger" onclick="window.print()">
                <i class="bi bi-printer"></i> <?= __('print') ?>
            </button>
            <button class="btn btn-success" onclick="exportToExcel('report-content', 'monthly-report-<?= $month ?>')">
                <i class="bi bi-file-excel"></i> <?= __('export') ?> Excel
            </button>
        </div>
    </div>
    
    <!-- Month Selection -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="/reports/monthly" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?= __('reports.month') ?></label>
                    <input type="month" name="month" class="form-control" value="<?= $month ?>" onchange="this.form.submit()">
                </div>
            </form>
        </div>
    </div>
    
    <div id="report-content">
        <!-- Report Header (for print) -->
        <div class="text-center mb-4 d-none d-print-block">
            <img src="/assets/images/logo.png" alt="Logo" style="max-height: 80px;" class="mb-3">
            <h2><?= __('company_name') ?></h2>
            <h3><?= __('reports.monthly_report') ?></h3>
            <p><?= date('F Y', strtotime($month . '-01')) ?></p>
        </div>
        
        <!-- Monthly Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6><?= __('transactions.title') ?></h6>
                        <h3><?= $monthlyStats['total_transactions'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6><?= __('clients.active') ?></h6>
                        <h3><?= $monthlyStats['active_clients'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6><?= __('total') ?> (RMB)</h6>
                        <h3>¥<?= number_format($monthlyStats['total_amount_rmb'], 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6><?= __('transactions.payment') ?> (RMB)</h6>
                        <h3>¥<?= number_format($monthlyStats['total_payments_rmb'], 0) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Financial Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?= __('reports.financial_summary') ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><?= __('transactions.goods_amount') ?></td>
                                <td class="text-end">¥<?= number_format($monthlyStats['total_goods_rmb'], 2) ?></td>
                            </tr>
                            <tr>
                                <td><?= __('transactions.commission') ?></td>
                                <td class="text-end">¥<?= number_format($monthlyStats['total_commission_rmb'], 2) ?></td>
                            </tr>
                            <tr class="table-secondary">
                                <th><?= __('total') ?> RMB</th>
                                <th class="text-end">¥<?= number_format($monthlyStats['total_amount_rmb'], 2) ?></th>
                            </tr>
                            <tr>
                                <td><?= __('transactions.payment') ?> RMB</td>
                                <td class="text-end text-success">¥<?= number_format($monthlyStats['total_payments_rmb'], 2) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><?= __('transactions.shipping') ?> USD</td>
                                <td class="text-end">$<?= number_format($monthlyStats['total_shipping_usd'], 2) ?></td>
                            </tr>
                            <tr>
                                <td><?= __('transactions.payment') ?> USD</td>
                                <td class="text-end text-success">$<?= number_format($monthlyStats['total_payments_usd'], 2) ?></td>
                            </tr>
                            <tr class="table-secondary">
                                <th><?= __('cashbox.movements') ?></th>
                                <th class="text-end"><?= $cashboxSummary['movements_count'] ?></th>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Clients -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?= __('reports.top_clients') ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?= __('clients.client_code') ?></th>
                                <th><?= __('clients.name') ?></th>
                                <th><?= __('transactions.count') ?></th>
                                <th><?= __('total') ?> (RMB)</th>
                                <th><?= __('transactions.payment') ?> (RMB)</th>
                                <th><?= __('percentage') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            $totalRevenue = $monthlyStats['total_amount_rmb'];
                            foreach ($topClients as $client): 
                                $percentage = $totalRevenue > 0 ? ($client['total_amount_rmb'] / $totalRevenue * 100) : 0;
                            ?>
                            <tr>
                                <td><?= $rank++ ?></td>
                                <td><?= $client['client_code'] ?></td>
                                <td>
                                    <?= lang() == 'ar' ? ($client['name_ar'] ?? $client['name']) : $client['name'] ?>
                                </td>
                                <td><?= $client['transaction_count'] ?></td>
                                <td class="text-end">¥<?= number_format($client['total_amount_rmb'], 2) ?></td>
                                <td class="text-end text-success">¥<?= number_format($client['total_payments_rmb'], 2) ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%">
                                            <?= number_format($percentage, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Cashbox Summary -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= __('cashbox.title') ?> <?= __('reports.summary') ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success"><?= __('cashbox.in') ?></h6>
                        <p>RMB: ¥<?= number_format($cashboxSummary['total_in_rmb'], 2) ?></p>
                        <p>USD: $<?= number_format($cashboxSummary['total_in_usd'], 2) ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-danger"><?= __('cashbox.out') ?></h6>
                        <p>RMB: ¥<?= number_format($cashboxSummary['total_out_rmb'], 2) ?></p>
                        <p>USD: $<?= number_format($cashboxSummary['total_out_usd'], 2) ?></p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-12">
                        <h6><?= __('reports.net_change') ?></h6>
                        <p class="<?= ($cashboxSummary['total_in_rmb'] - $cashboxSummary['total_out_rmb']) >= 0 ? 'text-success' : 'text-danger' ?>">
                            RMB: ¥<?= number_format($cashboxSummary['total_in_rmb'] - $cashboxSummary['total_out_rmb'], 2) ?>
                        </p>
                        <p class="<?= ($cashboxSummary['total_in_usd'] - $cashboxSummary['total_out_usd']) >= 0 ? 'text-success' : 'text-danger' ?>">
                            USD: $<?= number_format($cashboxSummary['total_in_usd'] - $cashboxSummary['total_out_usd'], 2) ?>
                        </p>
                    </div>
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

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        page-break-inside: avoid;
    }
    
    .progress {
        border: 1px solid #000;
    }
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>