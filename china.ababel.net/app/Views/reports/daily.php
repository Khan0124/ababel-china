<?php
// app/Views/reports/daily.php
include __DIR__ . '/../layouts/header.php';
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h1><?= __('reports.daily_report') ?> - <?= date('Y-m-d', strtotime($date)) ?></h1>
        <div>
            <button class="btn btn-danger" onclick="window.print()">
                <i class="bi bi-printer"></i> <?= __('print') ?>
            </button>
            <button class="btn btn-success" onclick="exportToExcel('report-content', 'daily-report-<?= $date ?>')">
                <i class="bi bi-file-excel"></i> <?= __('export') ?> Excel
            </button>
        </div>
    </div>
    
    <!-- Date Selection -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="/reports/daily" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?= __('date') ?></label>
                    <input type="date" name="date" class="form-control" value="<?= $date ?>" onchange="this.form.submit()">
                </div>
            </form>
        </div>
    </div>
    
    <div id="report-content">
        <!-- Report Header (for print) -->
        <div class="text-center mb-4 d-none d-print-block">
            <h2><?= __('company_name') ?></h2>
            <h3><?= __('reports.daily_report') ?></h3>
            <p><?= __('date') ?>: <?= date('Y-m-d', strtotime($date)) ?></p>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6><?= __('transactions.title') ?></h6>
                        <h3><?= $dailyTotals['transactions_count'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6><?= __('transactions.goods_amount') ?> (RMB)</h6>
                        <h3>¥<?= number_format($dailyTotals['total_goods_rmb'], 2) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6><?= __('transactions.payment') ?> (RMB)</h6>
                        <h3>¥<?= number_format($dailyTotals['total_payments_rmb'], 2) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6><?= __('cashbox.movements') ?></h6>
                        <h3><?= count($movements) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transactions Table -->
        <?php if (!empty($transactions)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?= __('transactions.title') ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th><?= __('transactions.transaction_no') ?></th>
                                <th><?= __('clients.name') ?></th>
                                <th><?= __('transactions.invoice_no') ?></th>
                                <th><?= __('transactions.goods_amount') ?></th>
                                <th><?= __('transactions.commission') ?></th>
                                <th><?= __('total') ?></th>
                                <th><?= __('transactions.payment') ?></th>
                                <th><?= __('balance') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?= $transaction['transaction_no'] ?></td>
                                <td><?= $transaction['client_name'] ?? '-' ?></td>
                                <td><?= $transaction['invoice_no'] ?? '-' ?></td>
                                <td>¥<?= number_format($transaction['goods_amount_rmb'], 2) ?></td>
                                <td>¥<?= number_format($transaction['commission_rmb'], 2) ?></td>
                                <td>¥<?= number_format($transaction['total_amount_rmb'], 2) ?></td>
                                <td>¥<?= number_format($transaction['payment_rmb'], 2) ?></td>
                                <td>¥<?= number_format($transaction['balance_rmb'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary">
                                <th colspan="3"><?= __('total') ?></th>
                                <th>¥<?= number_format($dailyTotals['total_goods_rmb'], 2) ?></th>
                                <th>¥<?= number_format($dailyTotals['total_commission_rmb'], 2) ?></th>
                                <th>¥<?= number_format($dailyTotals['total_goods_rmb'] + $dailyTotals['total_commission_rmb'], 2) ?></th>
                                <th>¥<?= number_format($dailyTotals['total_payments_rmb'], 2) ?></th>
                                <th>-</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Cashbox Movements -->
        <?php if (!empty($movements)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?= __('cashbox.movements') ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th><?= __('cashbox.movement_type') ?></th>
                                <th><?= __('cashbox.category') ?></th>
                                <th><?= __('transactions.description') ?></th>
                                <th>RMB</th>
                                <th>USD</th>
                                <th><?= __('cashbox.receipt_no') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?= $movement['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                        <?= __('cashbox.' . $movement['movement_type']) ?>
                                    </span>
                                </td>
                                <td><?= __('cashbox.' . $movement['category']) ?></td>
                                <td><?= $movement['description'] ?? '-' ?></td>
                                <td class="text-<?= $movement['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                    <?= $movement['movement_type'] == 'in' ? '+' : '-' ?>¥<?= number_format($movement['amount_rmb'], 2) ?>
                                </td>
                                <td class="text-<?= $movement['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                    <?php if ($movement['amount_usd'] > 0): ?>
                                        <?= $movement['movement_type'] == 'in' ? '+' : '-' ?>$<?= number_format($movement['amount_usd'], 2) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= $movement['receipt_no'] ?? '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary">
                                <th colspan="3"><?= __('total') ?></th>
                                <th>
                                    <div class="text-success">+¥<?= number_format($dailyTotals['cashbox_in_rmb'], 2) ?></div>
                                    <div class="text-danger">-¥<?= number_format($dailyTotals['cashbox_out_rmb'], 2) ?></div>
                                </th>
                                <th>
                                    <div class="text-success">+$<?= number_format($dailyTotals['cashbox_in_usd'], 2) ?></div>
                                    <div class="text-danger">-$<?= number_format($dailyTotals['cashbox_out_usd'], 2) ?></div>
                                </th>
                                <th>-</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
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
    
    .table {
        font-size: 12px;
    }
    
    body {
        font-size: 14px;
    }
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>