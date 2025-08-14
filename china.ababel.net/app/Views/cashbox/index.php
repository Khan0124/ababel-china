<?php
// app/Views/cashbox/index.php
include __DIR__ . '/../layouts/header.php';
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= __('cashbox.title') ?></h1>
        <a href="/cashbox/movement" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> <?= __('cashbox.movement') ?>
        </a>
    </div>
    
    <!-- Current Balance Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title"><?= __('cashbox.current_balance') ?> (RMB)</h6>
                    <h3 class="mb-0">¥<?= number_format($currentBalance['balance_rmb'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title"><?= __('cashbox.current_balance') ?> (USD)</h6>
                    <h3 class="mb-0">$<?= number_format($currentBalance['balance_usd'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title"><?= __('cashbox.current_balance') ?> (SDG)</h6>
                    <h3 class="mb-0"><?= number_format($currentBalance['balance_sdg'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title"><?= __('cashbox.current_balance') ?> (AED)</h6>
                    <h3 class="mb-0"><?= number_format($currentBalance['balance_aed'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Today's Summary -->
    <?php if (!empty($todaySummary)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><?= __('cashbox.daily_summary') ?> - <?= date('Y-m-d') ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($todaySummary as $summary): ?>
                <div class="col-md-3">
                    <div class="text-center">
                        <h6><?= __('cashbox.' . $summary['movement_type']) ?> - <?= __('cashbox.' . str_replace('_', '_', $summary['category'])) ?></h6>
                        <p class="mb-1"><?= __('total') ?>: <?= $summary['count'] ?></p>
                        <?php if ($summary['total_rmb'] > 0): ?>
                            <p class="mb-0 text-<?= $summary['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                ¥<?= number_format($summary['total_rmb'], 2) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($summary['total_usd'] > 0): ?>
                            <p class="mb-0 text-<?= $summary['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                $<?= number_format($summary['total_usd'], 2) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="/cashbox" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><?= __('from') ?></label>
                    <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('to') ?></label>
                    <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('cashbox.category') ?></label>
                    <select name="category" class="form-select">
                        <option value=""><?= __('all') ?></option>
                        <option value="office_transfer" <?= $selectedCategory == 'office_transfer' ? 'selected' : '' ?>>
                            <?= __('cashbox.office_transfer') ?>
                        </option>
                        <option value="customer_transfer" <?= $selectedCategory == 'customer_transfer' ? 'selected' : '' ?>>
                            <?= __('cashbox.customer_transfer') ?>
                        </option>
                        <option value="shipping_transfer" <?= $selectedCategory == 'shipping_transfer' ? 'selected' : '' ?>>
                            <?= __('cashbox.shipping_transfer') ?>
                        </option>
                        <option value="factory_payment" <?= $selectedCategory == 'factory_payment' ? 'selected' : '' ?>>
                            <?= __('cashbox.factory_payment') ?>
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> <?= __('filter') ?>
                        </button>
                        <a href="/cashbox" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> <?= __('cancel') ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Movements Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= __('cashbox.movements') ?></h5>
            <div>
                <button class="btn btn-success btn-sm" onclick="exportToExcel('movements-table', 'cashbox-movements')">
                    <i class="bi bi-file-excel"></i> <?= __('export') ?> Excel
                </button>
                <button class="btn btn-danger btn-sm" onclick="window.print()">
                    <i class="bi bi-file-pdf"></i> <?= __('print') ?>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="movements-table">
                    <thead>
                        <tr>
                            <th><?= __('date') ?></th>
                            <th><?= __('cashbox.movement_type') ?></th>
                            <th><?= __('cashbox.category') ?></th>
                            <th><?= __('transactions.description') ?></th>
                            <th>RMB</th>
                            <th>USD</th>
                            <th><?= __('cashbox.bank_name') ?></th>
                            <th><?= __('cashbox.receipt_no') ?></th>
                            <th><?= __('balance') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $movement): ?>
                        <tr>
                            <td><?= date('Y-m-d', strtotime($movement['movement_date'])) ?></td>
                            <td>
                                <span class="badge bg-<?= $movement['movement_type'] == 'in' ? 'success' : ($movement['movement_type'] == 'out' ? 'danger' : 'info') ?>">
                                    <?= __('cashbox.' . $movement['movement_type']) ?>
                                </span>
                            </td>
                            <td><?= __('cashbox.' . $movement['category']) ?></td>
                            <td>
                                <?= htmlspecialchars($movement['description'] ?? '') ?>
                                <?php if ($movement['transaction_no']): ?>
                                    <br><small class="text-muted">
                                        <?= __('transactions.transaction_no') ?>: <?= $movement['transaction_no'] ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-<?= $movement['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                <?php if ($movement['amount_rmb'] != 0): ?>
                                    <?= $movement['movement_type'] == 'in' ? '+' : '-' ?>¥<?= number_format(abs($movement['amount_rmb']), 2) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-<?= $movement['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                <?php if ($movement['amount_usd'] != 0): ?>
                                    <?= $movement['movement_type'] == 'in' ? '+' : '-' ?>$<?= number_format(abs($movement['amount_usd']), 2) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($movement['bank_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($movement['receipt_no'] ?? '-') ?></td>
                            <td>
                                <?php if (isset($movement['balance_after_rmb'])): ?>
                                    <small>¥<?= number_format($movement['balance_after_rmb'], 2) ?></small>
                                <?php endif; ?>
                                <?php if (isset($movement['balance_after_usd'])): ?>
                                    <br><small>$<?= number_format($movement['balance_after_usd'], 2) ?></small>
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

<style>
@media print {
    .btn, .form-control, .form-select, .card-header .btn-sm {
        display: none !important;
    }
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>