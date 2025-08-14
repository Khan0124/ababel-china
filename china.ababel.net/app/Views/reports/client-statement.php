<?php
// app/Views/clients/statement.php
include __DIR__ . '/../layouts/header.php';

// Calculate totals
$totalTransactions = 0;
$totalPayments = 0;
$totalBalance = 0;

foreach ($transactions as $transaction) {
    $totalTransactions += $transaction['total_amount_rmb'];
    $totalPayments += $transaction['payment_rmb'];
    $totalBalance += $transaction['balance_rmb'];
}
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h1><?= __('clients.client_statement') ?></h1>
        <div>
            <button class="btn btn-danger" onclick="window.print()">
                <i class="bi bi-printer"></i> <?= __('print') ?>
            </button>
            <button class="btn btn-success" onclick="exportToExcel('statement-table', 'statement-<?= $client['client_code'] ?>')">
                <i class="bi bi-file-excel"></i> <?= __('export') ?> Excel
            </button>
            <a href="/clients" class="btn btn-secondary">
                <i class="bi bi-arrow-<?= isRTL() ? 'right' : 'left' ?>"></i> <?= __('back') ?>
            </a>
        </div>
    </div>
    
    <!-- Client Info -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h4><?= lang() == 'ar' ? ($client['name_ar'] ?? $client['name']) : $client['name'] ?></h4>
                    <p class="mb-1"><strong><?= __('clients.client_code') ?>:</strong> <?= $client['client_code'] ?></p>
                    <p class="mb-1"><strong><?= __('clients.phone') ?>:</strong> <?= $client['phone'] ?></p>
                    <?php if ($client['email']): ?>
                    <p class="mb-1"><strong><?= __('clients.email') ?>:</strong> <?= $client['email'] ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-1"><strong><?= __('reports.date_range') ?>:</strong></p>
                    <form method="GET" action="/clients/statement/<?= $client['id'] ?>" class="d-inline-flex gap-2">
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= $startDate ?>" onchange="this.form.submit()">
                        <span>-</span>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= $endDate ?>" onchange="this.form.submit()">
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6><?= __('transactions.total_amount') ?></h6>
                    <h4>¥<?= number_format($totalTransactions, 2) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6><?= __('transactions.payment') ?></h6>
                    <h4>¥<?= number_format($totalPayments, 2) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6><?= __('balance') ?></h6>
                    <h4>¥<?= number_format($totalBalance, 2) ?></h4>
                    <?php if ($totalBalance > 0): ?>
                        <button type="button" class="btn btn-light btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#paymentModal">
                            <i class="bi bi-credit-card me-1"></i>
                            <?= __('transactions.make_payment') ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6><?= __('clients.transaction_count') ?></h6>
                    <h4><?= count($transactions) ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><?= __('transactions.title') ?></h5>
        </div>
        <div class="card-body">
            <?php if (empty($transactions)): ?>
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> <?= __('messages.no_data_found') ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped" id="statement-table">
                        <thead>
                            <tr>
                                <th><?= __('date') ?></th>
                                <th><?= __('transactions.transaction_no') ?></th>
                                <th><?= __('transactions.type') ?></th>
                                <th><?= __('transactions.description') ?></th>
                                <th><?= __('transactions.invoice_no') ?></th>
                                <th><?= __('transactions.goods_amount') ?></th>
                                <th><?= __('transactions.commission') ?></th>
                                <th><?= __('total') ?></th>
                                <th><?= __('transactions.payment') ?></th>
                                <th><?= __('balance') ?></th>
                                <th><?= __('status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $runningBalance = 0;
                            foreach ($transactions as $transaction): 
                                $runningBalance += $transaction['balance_rmb'];
                            ?>
                            <tr>
                                <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                                <td>
                                    <a href="/transactions/view/<?= $transaction['id'] ?>">
                                        <?= $transaction['transaction_no'] ?>
                                    </a>
                                </td>
                                <td><?= $transaction['transaction_type_name'] ?></td>
                                <td><?= $transaction['description'] ?? '-' ?></td>
                                <td><?= $transaction['invoice_no'] ?? '-' ?></td>
                                <td class="text-end">¥<?= number_format($transaction['goods_amount_rmb'], 2) ?></td>
                                <td class="text-end">¥<?= number_format($transaction['commission_rmb'], 2) ?></td>
                                <td class="text-end">¥<?= number_format($transaction['total_amount_rmb'], 2) ?></td>
                                <td class="text-end text-success">¥<?= number_format($transaction['payment_rmb'], 2) ?></td>
                                <td class="text-end <?= $transaction['balance_rmb'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    ¥<?= number_format($transaction['balance_rmb'], 2) ?>
                                </td>
                                <td>
                                    <?php if ($transaction['status'] == 'approved'): ?>
                                        <span class="badge bg-success"><?= __('transactions.approved') ?></span>
                                    <?php elseif ($transaction['status'] == 'pending'): ?>
                                        <span class="badge bg-warning"><?= __('transactions.pending') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?= __('transactions.cancelled') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary">
                                <th colspan="5"><?= __('total') ?></th>
                                <th class="text-end">¥<?= number_format(array_sum(array_column($transactions, 'goods_amount_rmb')), 2) ?></th>
                                <th class="text-end">¥<?= number_format(array_sum(array_column($transactions, 'commission_rmb')), 2) ?></th>
                                <th class="text-end">¥<?= number_format($totalTransactions, 2) ?></th>
                                <th class="text-end">¥<?= number_format($totalPayments, 2) ?></th>
                                <th class="text-end <?= $totalBalance > 0 ? 'text-danger' : 'text-success' ?>">
                                    ¥<?= number_format($totalBalance, 2) ?>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- USD Section if any -->
                <?php 
                $hasUsd = false;
                foreach ($transactions as $t) {
                    if ($t['shipping_usd'] > 0 || $t['payment_usd'] > 0) {
                        $hasUsd = true;
                        break;
                    }
                }
                
                if ($hasUsd): 
                ?>
                <h6 class="mt-4"><?= __('transactions.shipping') ?> (USD)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th><?= __('date') ?></th>
                                <th><?= __('transactions.transaction_no') ?></th>
                                <th><?= __('transactions.shipping') ?></th>
                                <th><?= __('transactions.payment') ?></th>
                                <th><?= __('balance') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): 
                                if ($transaction['shipping_usd'] > 0 || $transaction['payment_usd'] > 0):
                            ?>
                            <tr>
                                <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                                <td><?= $transaction['transaction_no'] ?></td>
                                <td class="text-end">$<?= number_format($transaction['shipping_usd'], 2) ?></td>
                                <td class="text-end text-success">$<?= number_format($transaction['payment_usd'], 2) ?></td>
                                <td class="text-end">$<?= number_format($transaction['balance_usd'], 2) ?></td>
                            </tr>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary">
                                <th colspan="2"><?= __('total') ?></th>
                                <th class="text-end">$<?= number_format(array_sum(array_column($transactions, 'shipping_usd')), 2) ?></th>
                                <th class="text-end">$<?= number_format(array_sum(array_column($transactions, 'payment_usd')), 2) ?></th>
                                <th class="text-end">$<?= number_format(array_sum(array_column($transactions, 'balance_usd')), 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Print Header -->
    <div class="d-none d-print-block text-center mb-4">
        <img src="/assets/images/logo.png" alt="Logo" style="max-height: 80px;" class="mb-3">
        <h2><?= __('company_name') ?></h2>
        <h4><?= __('clients.client_statement') ?></h4>
        <p><?= __('date') ?>: <?= date('Y-m-d') ?></p>
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
    
    body {
        font-size: 12px;
    }
    
    .table {
        font-size: 11px;
    }
}
</style>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalLabel">
                    <i class="bi bi-credit-card me-2"></i>
                    <?= __('transactions.make_payment') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/clients/make-payment" id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                    
                    <!-- Client Info -->
                    <div class="alert alert-info">
                        <h6><i class="bi bi-person-circle me-2"></i><?= __('clients.client_info') ?></h6>
                        <strong><?= htmlspecialchars($client['name']) ?></strong> (<?= htmlspecialchars($client['client_code']) ?>)
                        <br>
                        <small><?= __('balance') ?>: ¥<?= number_format($client['balance_rmb'], 2) ?> | $<?= number_format($client['balance_usd'], 2) ?></small>
                    </div>
                    
                    <!-- Payment Amount -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="payment_amount" class="form-label">
                                <?= __('transactions.payment') ?> <?= __('amount') ?> *
                            </label>
                            <input type="number" 
                                   class="form-control" 
                                   id="payment_amount" 
                                   name="payment_amount" 
                                   step="0.01" 
                                   min="0.01"
                                   max="<?= max($client['balance_rmb'], $client['balance_usd']) ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="payment_currency" class="form-label">
                                <?= __('currency') ?> *
                            </label>
                            <select class="form-select" id="payment_currency" name="payment_currency" required>
                                <?php if ($client['balance_rmb'] > 0): ?>
                                    <option value="RMB">RMB (¥<?= number_format($client['balance_rmb'], 2) ?>)</option>
                                <?php endif; ?>
                                <?php if ($client['balance_usd'] > 0): ?>
                                    <option value="USD">USD ($<?= number_format($client['balance_usd'], 2) ?>)</option>
                                <?php endif; ?>
                                <?php if (isset($client['balance_sdg']) && $client['balance_sdg'] > 0): ?>
                                    <option value="SDG">SDG (<?= number_format($client['balance_sdg'], 2) ?>)</option>
                                <?php endif; ?>
                                <?php if (isset($client['balance_aed']) && $client['balance_aed'] > 0): ?>
                                    <option value="AED">AED (<?= number_format($client['balance_aed'], 2) ?>)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="payment_method" class="form-label">
                                <?= __('transactions.payment_method') ?>
                            </label>
                            <select class="form-select" id="payment_method" name="payment_method">
                                <option value="cash"><?= __('payment.cash') ?></option>
                                <option value="transfer"><?= __('payment.transfer') ?></option>
                                <option value="check"><?= __('payment.check') ?></option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="bank_name" class="form-label">
                                <?= __('transactions.bank_name') ?>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="bank_name" 
                                   name="bank_name" 
                                   placeholder="<?= __('transactions.bank_name') ?>">
                        </div>
                    </div>
                    
                    <!-- Payment Description -->
                    <div class="mb-3">
                        <label for="payment_description" class="form-label">
                            <?= __('transactions.description') ?>
                        </label>
                        <textarea class="form-control" 
                                  id="payment_description" 
                                  name="payment_description" 
                                  rows="3" 
                                  placeholder="<?= __('transactions.payment_description_hint') ?>"></textarea>
                    </div>
                    
                    <!-- Quick Payment Buttons -->
                    <div class="mb-3">
                        <label class="form-label"><?= __('transactions.quick_amounts') ?>:</label>
                        <div class="btn-group d-block" role="group">
                            <?php 
                            $maxBalance = max($client['balance_rmb'], $client['balance_usd']);
                            $quickAmounts = [
                                0.25 => '25%',
                                0.5 => '50%', 
                                0.75 => '75%',
                                1.0 => __('transactions.full_payment')
                            ];
                            ?>
                            <?php foreach ($quickAmounts as $percent => $label): ?>
                                <button type="button" 
                                        class="btn btn-outline-primary btn-sm me-2 mb-2"
                                        onclick="setQuickAmount(<?= $percent ?>)">
                                    <?= $label ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>
                        <?= __('transactions.process_payment') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Payment form JavaScript
function setQuickAmount(percentage) {
    const currency = document.getElementById('payment_currency').value;
    const client = <?= json_encode($client) ?>;
    
    let balance = 0;
    switch(currency) {
        case 'RMB':
            balance = parseFloat(client.balance_rmb || 0);
            break;
        case 'USD':
            balance = parseFloat(client.balance_usd || 0);
            break;
        case 'SDG':
            balance = parseFloat(client.balance_sdg || 0);
            break;
        case 'AED':
            balance = parseFloat(client.balance_aed || 0);
            break;
    }
    
    const amount = (balance * percentage);
    document.getElementById('payment_amount').value = amount.toFixed(2);
}

// Update max amount when currency changes
document.getElementById('payment_currency').addEventListener('change', function() {
    const currency = this.value;
    const client = <?= json_encode($client) ?>;
    const amountInput = document.getElementById('payment_amount');
    
    let maxBalance = 0;
    switch(currency) {
        case 'RMB':
            maxBalance = parseFloat(client.balance_rmb || 0);
            break;
        case 'USD':
            maxBalance = parseFloat(client.balance_usd || 0);
            break;
        case 'SDG':
            maxBalance = parseFloat(client.balance_sdg || 0);
            break;
        case 'AED':
            maxBalance = parseFloat(client.balance_aed || 0);
            break;
    }
    
    amountInput.max = maxBalance;
    amountInput.placeholder = 'Max: ' + maxBalance.toFixed(2);
});

// Auto-generate description based on payment details
document.getElementById('payment_amount').addEventListener('input', function() {
    const amount = this.value;
    const currency = document.getElementById('payment_currency').value;
    const client = <?= json_encode($client) ?>;
    const description = `Payment of ${amount} ${currency} from ${client.name} (${client.client_code})`;
    
    if (!document.getElementById('payment_description').value) {
        document.getElementById('payment_description').value = description;
    }
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>