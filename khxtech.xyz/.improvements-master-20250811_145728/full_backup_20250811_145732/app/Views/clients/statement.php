<?php
// app/Views/clients/statement.php
include __DIR__ . '/../layouts/header.php';

// Calculate totals
$totalTransactions = 0;
$totalPayments = 0;

foreach ($transactions as $transaction) {
    $totalTransactions += $transaction['total_amount_rmb'];
    $totalPayments += $transaction['payment_rmb'];
}

// Use client's actual current balance instead of summing transaction balances
$totalBalance = $client['balance_rmb'];
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
                    <h6><?= getBalanceType($totalBalance) == 'debt' ? __('outstanding_debt') : __('balance') ?></h6>
                    <h4 class="<?= getBalanceType($totalBalance) == 'debt' ? 'text-danger' : 'text-success' ?>">
                        ¥<?= number_format(abs($totalBalance), 2) ?>
                        <?php if (getBalanceType($totalBalance) == 'debt'): ?>
                            <small>(<?= __('debt') ?>)</small>
                        <?php elseif (getBalanceType($totalBalance) == 'credit'): ?>
                            <small>(<?= __('credit') ?>)</small>
                        <?php endif; ?>
                    </h4>
                    <?php if (getOutstandingAmount($totalBalance) > 0): ?>
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
                                <th>
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
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
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $runningBalance = 0;
                            foreach ($transactions as $transaction): 
                                $runningBalance += $transaction['balance_rmb'];
                            ?>
                            <tr data-transaction-id="<?= $transaction['id'] ?>" data-balance="<?= getOutstandingAmount($transaction['balance_rmb']) ?>">
                                <td>
                                    <?php if (getOutstandingAmount($transaction['balance_rmb']) > 0): ?>
                                        <input type="checkbox" class="form-check-input transaction-select" 
                                               value="<?= $transaction['id'] ?>" 
                                               data-balance="<?= getOutstandingAmount($transaction['balance_rmb']) ?>">
                                    <?php endif; ?>
                                </td>
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
                                <td class="text-end <?= getBalanceType($transaction['balance_rmb']) == 'debt' ? 'text-danger' : 'text-success' ?>">
                                    ¥<?= number_format(abs($transaction['balance_rmb']), 2) ?>
                                    <?php if (getBalanceType($transaction['balance_rmb']) == 'debt'): ?>
                                        <small>(<?= __('debt') ?>)</small>
                                    <?php endif; ?>
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
                                <td>
                                    <?php if (getOutstandingAmount($transaction['balance_rmb']) > 0): ?>
                                        <button type="button" class="btn btn-sm btn-primary pay-single" 
                                                data-transaction-id="<?= $transaction['id'] ?>"
                                                data-balance="<?= getOutstandingAmount($transaction['balance_rmb']) ?>"
                                                data-transaction-no="<?= $transaction['transaction_no'] ?>">
                                            <i class="bi bi-credit-card"></i> <?= __('transactions.pay') ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary">
                                <th></th>
                                <th colspan="5"><?= __('total') ?></th>
                                <th class="text-end">¥<?= number_format(array_sum(array_column($transactions, 'goods_amount_rmb')), 2) ?></th>
                                <th class="text-end">¥<?= number_format(array_sum(array_column($transactions, 'commission_rmb')), 2) ?></th>
                                <th class="text-end">¥<?= number_format($totalTransactions, 2) ?></th>
                                <th class="text-end">¥<?= number_format($totalPayments, 2) ?></th>
                                <th class="text-end <?= $totalBalance < 0 ? 'text-danger' : 'text-success' ?>">
                                    ¥<?= number_format(abs($totalBalance), 2) ?>
                                    <?php if ($totalBalance < 0): ?>
                                        <small>(<?= __('debt') ?>)</small>
                                    <?php endif; ?>
                                </th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Bulk Payment Actions -->
                <div class="card mt-3" id="bulkPaymentCard" style="display: none;">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-credit-card-2-front me-2"></i>
                            <?= __('transactions.bulk_payment') ?>
                        </h6>
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <span class="me-3"><?= __('selected_items') ?>: <strong id="selectedCount">0</strong></span>
                                    <span><?= __('total_amount') ?>: <strong id="selectedTotal">¥0.00</strong></span>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="clearSelection">
                                    <?= __('clear_selection') ?>
                                </button>
                                <button type="button" class="btn btn-success" id="paySelected">
                                    <i class="bi bi-credit-card me-1"></i>
                                    <?= __('transactions.pay_selected') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Multi-Currency Sections -->
                <?php 
                // Check for different currencies
                $currencies = [
                    'USD' => ['symbol' => '$', 'fields' => ['shipping_usd', 'payment_usd', 'balance_usd'], 'name' => 'USD'],
                    'SDG' => ['symbol' => 'SDG ', 'fields' => ['shipping_sdg', 'payment_sdg', 'balance_sdg'], 'name' => 'SDG'],
                    'AED' => ['symbol' => 'AED ', 'fields' => ['shipping_aed', 'payment_aed', 'balance_aed'], 'name' => 'AED']
                ];
                
                foreach ($currencies as $currencyCode => $currencyInfo):
                    $hasTransactions = false;
                    foreach ($transactions as $t) {
                        foreach ($currencyInfo['fields'] as $field) {
                            if (isset($t[$field]) && $t[$field] > 0) {
                                $hasTransactions = true;
                                break 2;
                            }
                        }
                    }
                    
                    if ($hasTransactions): 
                ?>
                <h6 class="mt-4"><?= __('transactions.amounts') ?> (<?= $currencyInfo['name'] ?>)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th><?= __('date') ?></th>
                                <th><?= __('transactions.transaction_no') ?></th>
                                <th><?= __('transactions.shipping') ?></th>
                                <th><?= __('transactions.payment') ?></th>
                                <th><?= __('balance') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): 
                                $hasAmount = false;
                                foreach ($currencyInfo['fields'] as $field) {
                                    if (isset($transaction[$field]) && $transaction[$field] > 0) {
                                        $hasAmount = true;
                                        break;
                                    }
                                }
                                if ($hasAmount):
                            ?>
                            <tr>
                                <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                                <td><?= $transaction['transaction_no'] ?></td>
                                <td class="text-end">
                                    <?= $currencyInfo['symbol'] . number_format($transaction[$currencyInfo['fields'][0]] ?? 0, 2) ?>
                                </td>
                                <td class="text-end text-success">
                                    <?= $currencyInfo['symbol'] . number_format($transaction[$currencyInfo['fields'][1]] ?? 0, 2) ?>
                                </td>
                                <td class="text-end <?= getBalanceType($transaction[$currencyInfo['fields'][2]] ?? 0) == 'debt' ? 'text-danger' : 'text-success' ?>">
                                    <?= $currencyInfo['symbol'] . number_format(abs($transaction[$currencyInfo['fields'][2]] ?? 0), 2) ?>
                                    <?php $bal = $transaction[$currencyInfo['fields'][2]] ?? 0; ?>
                                    <?php if (getBalanceType($bal) == 'debt'): ?>
                                        <small>(<?= __('debt') ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (getOutstandingAmount($transaction[$currencyInfo['fields'][2]] ?? 0) > 0): ?>
                                        <button type="button" class="btn btn-sm btn-primary pay-single" 
                                                data-transaction-id="<?= $transaction['id'] ?>"
                                                data-balance="<?= getOutstandingAmount($transaction[$currencyInfo['fields'][2]]) ?>"
                                                data-currency="<?= $currencyCode ?>"
                                                data-transaction-no="<?= $transaction['transaction_no'] ?>">
                                            <i class="bi bi-credit-card"></i> <?= __('transactions.pay') ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary">
                                <th colspan="2"><?= __('total') ?></th>
                                <th class="text-end">
                                    <?= $currencyInfo['symbol'] . number_format(array_sum(array_column($transactions, $currencyInfo['fields'][0])), 2) ?>
                                </th>
                                <th class="text-end">
                                    <?= $currencyInfo['symbol'] . number_format(array_sum(array_column($transactions, $currencyInfo['fields'][1])), 2) ?>
                                </th>
                                <th class="text-end">
                                    <?= $currencyInfo['symbol'] . number_format(array_sum(array_column($transactions, $currencyInfo['fields'][2])), 2) ?>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php 
                    endif;
                endforeach; 
                ?>
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
                    <input type="hidden" name="transaction_ids" id="transaction_ids" value="">
                    <input type="hidden" name="payment_type" id="payment_type" value="full">
                    
                    <!-- Client Info -->
                    <div class="alert alert-info">
                        <h6><i class="bi bi-person-circle me-2"></i><?= __('clients.client_info') ?></h6>
                        <strong><?= htmlspecialchars($client['name']) ?></strong> (<?= htmlspecialchars($client['client_code']) ?>)
                        <br>
                        <small><?= __('balance') ?>: <?= formatBalance($client['balance_rmb'], 'RMB') ?> | <?= formatBalance($client['balance_usd'], 'USD') ?></small>
                        <div id="selected-transactions-info" style="display: none;">
                            <small class="text-primary"><strong><?= __('selected_transactions') ?>:</strong> <span id="selected-trans-list"></span></small>
                        </div>
                    </div>
                    
                    <!-- Payment Type Selection -->
                    <div class="mb-3">
                        <label class="form-label"><?= __('transactions.payment_type') ?> *</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_type_radio" id="fullPayment" value="full" checked>
                            <label class="form-check-label" for="fullPayment">
                                <?= __('transactions.full_payment') ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_type_radio" id="partialPayment" value="partial">
                            <label class="form-check-label" for="partialPayment">
                                <?= __('transactions.partial_payment') ?>
                            </label>
                        </div>
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
                                   max="<?= max(getOutstandingAmount($client['balance_rmb']), getOutstandingAmount($client['balance_usd']), getOutstandingAmount($client['balance_sdg']), getOutstandingAmount($client['balance_aed'])) ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="payment_currency" class="form-label">
                                <?= __('currency') ?> *
                            </label>
                            <select class="form-select" id="payment_currency" name="payment_currency" required>
                                <option value="RMB" <?= (getOutstandingAmount($client['balance_rmb'] ?? 0) > 0) ? 'selected' : '' ?>>
                                    RMB (<?= formatBalance($client['balance_rmb'] ?? 0, 'RMB') ?>)
                                </option>
                                <option value="USD" <?= (getOutstandingAmount($client['balance_usd'] ?? 0) > 0) ? 'selected' : '' ?>>
                                    USD (<?= formatBalance($client['balance_usd'] ?? 0, 'USD') ?>)
                                </option>
                                <option value="SDG" <?= (getOutstandingAmount($client['balance_sdg'] ?? 0) > 0) ? 'selected' : '' ?>>
                                    SDG (<?= formatBalance($client['balance_sdg'] ?? 0, 'SDG') ?>)
                                </option>
                                <option value="AED" <?= (getOutstandingAmount($client['balance_aed'] ?? 0) > 0) ? 'selected' : '' ?>>
                                    AED (<?= formatBalance($client['balance_aed'] ?? 0, 'AED') ?>)
                                </option>
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
// Enhanced Payment form JavaScript
let selectedTransactions = [];
let isPartialPayment = false;

// Handle select all checkbox
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.transaction-select');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectedTransactions();
});

// Handle individual transaction selection
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('transaction-select')) {
        updateSelectedTransactions();
    }
});

// Update selected transactions
function updateSelectedTransactions() {
    const selected = document.querySelectorAll('.transaction-select:checked');
    selectedTransactions = [];
    let totalAmount = 0;
    
    selected.forEach(checkbox => {
        const transactionId = checkbox.value;
        const balance = parseFloat(checkbox.dataset.balance);
        selectedTransactions.push({id: transactionId, balance: balance});
        totalAmount += balance;
    });
    
    document.getElementById('selectedCount').textContent = selectedTransactions.length;
    document.getElementById('selectedTotal').textContent = '¥' + totalAmount.toFixed(2);
    
    const bulkCard = document.getElementById('bulkPaymentCard');
    if (selectedTransactions.length > 0) {
        bulkCard.style.display = 'block';
    } else {
        bulkCard.style.display = 'none';
    }
}

// Clear selection
document.getElementById('clearSelection').addEventListener('click', function() {
    document.querySelectorAll('.transaction-select').forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAll').checked = false;
    updateSelectedTransactions();
});

// Pay selected transactions
document.getElementById('paySelected').addEventListener('click', function() {
    if (selectedTransactions.length === 0) {
        alert('<?= __('messages.select_transactions_first') ?>');
        return;
    }
    
    openPaymentModal(selectedTransactions);
});

// Pay single transaction
document.addEventListener('click', function(e) {
    if (e.target.closest('.pay-single')) {
        const button = e.target.closest('.pay-single');
        const transactionId = button.dataset.transactionId;
        const balance = parseFloat(button.dataset.balance);
        const transactionNo = button.dataset.transactionNo;
        const currency = button.dataset.currency || 'RMB';
        
        openPaymentModal([{id: transactionId, balance: balance, no: transactionNo, currency: currency}]);
    }
});

// Open payment modal
function openPaymentModal(transactions) {
    // Reset form
    document.getElementById('paymentForm').reset();
    
    // Set transaction IDs
    const ids = transactions.map(t => t.id).join(',');
    document.getElementById('transaction_ids').value = ids;
    
    // Update modal content
    if (transactions.length > 1) {
        document.getElementById('selected-transactions-info').style.display = 'block';
        const transactionsList = transactions.map(t => t.no || t.id).join(', ');
        document.getElementById('selected-trans-list').textContent = transactionsList;
        
        // Set total amount for bulk payment
        const totalAmount = transactions.reduce((sum, t) => sum + t.balance, 0);
        document.getElementById('payment_amount').value = totalAmount.toFixed(2);
    } else {
        document.getElementById('selected-transactions-info').style.display = 'none';
        document.getElementById('payment_amount').value = transactions[0].balance.toFixed(2);
        
        // Set currency if specified for single transaction
        if (transactions[0].currency) {
            const currencySelect = document.getElementById('payment_currency');
            const option = currencySelect.querySelector(`option[value="${transactions[0].currency}"]`);
            if (option) {
                currencySelect.value = transactions[0].currency;
            }
        }
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

// Handle payment type change
document.addEventListener('change', function(e) {
    if (e.target.name === 'payment_type_radio') {
        const paymentType = e.target.value;
        document.getElementById('payment_type').value = paymentType;
        isPartialPayment = paymentType === 'partial';
        
        const amountInput = document.getElementById('payment_amount');
        if (paymentType === 'full') {
            // Calculate total for selected transactions
            const totalAmount = selectedTransactions.reduce((sum, t) => sum + t.balance, 0);
            amountInput.value = totalAmount.toFixed(2);
            amountInput.readOnly = true;
        } else {
            amountInput.readOnly = false;
            amountInput.value = '';
            amountInput.focus();
        }
        
        updateCurrencyLimits();
    }
});

// Quick amount setting
function setQuickAmount(percentage) {
    const currency = document.getElementById('payment_currency').value;
    const client = <?= json_encode($client) ?>;
    
    let balance = 0;
    if (selectedTransactions.length > 0) {
        balance = selectedTransactions.reduce((sum, t) => sum + t.balance, 0);
    } else {
        switch(currency) {
            case 'RMB':
                balance = Math.max(0, parseFloat(client.balance_rmb || 0));
                break;
            case 'USD':
                balance = Math.max(0, parseFloat(client.balance_usd || 0));
                break;
            case 'SDG':
                balance = Math.max(0, parseFloat(client.balance_sdg || 0));
                break;
            case 'AED':
                balance = Math.max(0, parseFloat(client.balance_aed || 0));
                break;
        }
    }
    
    const amount = (balance * percentage);
    document.getElementById('payment_amount').value = amount.toFixed(2);
}

// Update currency limits
function updateCurrencyLimits() {
    const currency = document.getElementById('payment_currency').value;
    const client = <?= json_encode($client) ?>;
    const amountInput = document.getElementById('payment_amount');
    
    let maxBalance = 0;
    if (selectedTransactions.length > 0) {
        maxBalance = selectedTransactions.reduce((sum, t) => sum + t.balance, 0);
    } else {
        switch(currency) {
            case 'RMB':
                maxBalance = Math.max(0, parseFloat(client.balance_rmb || 0));
                break;
            case 'USD':
                maxBalance = Math.max(0, parseFloat(client.balance_usd || 0));
                break;
            case 'SDG':
                maxBalance = Math.max(0, parseFloat(client.balance_sdg || 0));
                break;
            case 'AED':
                maxBalance = Math.max(0, parseFloat(client.balance_aed || 0));
                break;
        }
    }
    
    amountInput.max = maxBalance;
    amountInput.placeholder = 'Max: ' + maxBalance.toFixed(2);
}

// Currency change handler
document.getElementById('payment_currency').addEventListener('change', updateCurrencyLimits);

// Auto-generate description
document.getElementById('payment_amount').addEventListener('input', function() {
    const amount = this.value;
    const currency = document.getElementById('payment_currency').value;
    const client = <?= json_encode($client) ?>;
    
    let description = '';
    if (selectedTransactions.length > 1) {
        description = `Bulk payment of ${amount} ${currency} for ${selectedTransactions.length} transactions from ${client.name} (${client.client_code})`;
    } else {
        description = `Payment of ${amount} ${currency} from ${client.name} (${client.client_code})`;
    }
    
    if (!document.getElementById('payment_description').value) {
        document.getElementById('payment_description').value = description;
    }
});

// Form validation
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const amount = parseFloat(document.getElementById('payment_amount').value);
    const maxAmount = parseFloat(document.getElementById('payment_amount').max);
    
    if (amount <= 0) {
        e.preventDefault();
        alert('<?= __('messages.invalid_payment_amount') ?>');
        return;
    }
    
    if (amount > maxAmount) {
        e.preventDefault();
        alert('<?= __('messages.payment_exceeds_balance') ?>');
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-spinner-border spinner-border-sm me-1"></i> <?= __('processing') ?>';
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>