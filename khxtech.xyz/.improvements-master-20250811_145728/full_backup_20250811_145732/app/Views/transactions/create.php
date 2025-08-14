<?php
// app/Views/transactions/create.php
include __DIR__ . '/../layouts/header.php';

// Get transaction types
$db = \App\Core\Database::getInstance();
$stmt = $db->query("SELECT * FROM transaction_types ORDER BY name");
$transactionTypes = $stmt->fetchAll();

// Get exchange rates
$stmt = $db->query("SELECT * FROM settings WHERE setting_key LIKE 'exchange_rate_%'");
$ratesRaw = $stmt->fetchAll();
$exchangeRates = [];
foreach ($ratesRaw as $rate) {
    $exchangeRates[$rate['setting_key']] = $rate['setting_value'];
}

// Calculate exchange rates
$usdToRmb = floatval($exchangeRates['exchange_rate_usd_rmb'] ?? 7.20);
$sdgToRmb = floatval($exchangeRates['exchange_rate_sdg_rmb'] ?? 0.012);
$aedToRmb = floatval($exchangeRates['exchange_rate_aed_rmb'] ?? 1.96);
$usdToSdg = $sdgToRmb > 0 ? $usdToRmb / $sdgToRmb : 600;
$usdToAed = $aedToRmb > 0 ? $usdToRmb / $aedToRmb : 3.67;

// Get banks list from settings (for autocomplete)
$banksStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'banks_list'");
$banksResult = $banksStmt->fetch();
$banksList = $banksResult ? explode(',', $banksResult['setting_value']) : [];
$banksList = array_map('trim', $banksList);
?>

<div class="col-md-12 p-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><?= __('transactions.add_new') ?></h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="/transactions/create" id="transaction-form">
                        <!-- Basic Information -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="transaction_date" class="form-label">
                                    <?= __('transactions.transaction_date') ?> *
                                </label>
                                <input type="date" 
                                       class="form-control" 
                                       id="transaction_date" 
                                       name="transaction_date" 
                                       value="<?= $data['transaction_date'] ?? date('Y-m-d') ?>" 
                                       required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="client_id" class="form-label">
                                    <?= __('transactions.client') ?> *
                                </label>
                                <select class="form-select" id="client_id" name="client_id" required>
                                    <option value=""><?= __('search') ?>...</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>" <?= ($data['client_id'] ?? '') == $client['id'] ? 'selected' : '' ?>>
                                        <?= lang() == 'ar' ? ($client['name_ar'] ?? $client['name']) : $client['name'] ?> 
                                        (<?= $client['client_code'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="transaction_type_id" class="form-label">
                                    <?= __('transactions.type') ?> *
                                </label>
                                <select class="form-select" id="transaction_type_id" name="transaction_type_id" required>
                                    <option value=""><?= __('search') ?>...</option>
                                    <?php foreach ($transactionTypes as $type): ?>
                                    <option value="<?= $type['id'] ?>" <?= ($data['transaction_type_id'] ?? '') == $type['id'] ? 'selected' : '' ?>>
                                        <?= lang() == 'ar' ? ($type['name_ar'] ?? $type['name']) : $type['name'] ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="invoice_no" class="form-label">
                                    <?= __('transactions.invoice_no') ?>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="invoice_no" 
                                       name="invoice_no" 
                                       value="<?= $data['invoice_no'] ?? '' ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="loading_no" class="form-label">
                                    <?= __('transactions.loading_no') ?>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="loading_no" 
                                       name="loading_no" 
                                       value="<?= $data['loading_no'] ?? '' ?>">
                            </div>
                            
                            <div class="col-md-3 position-relative">
                                <label for="bank_name" class="form-label">
                                    <?= __('cashbox.bank_name') ?>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="bank_name" 
                                       name="bank_name" 
                                       value="<?= $data['bank_name'] ?? '' ?>"
                                       placeholder="<?= __('messages.type_or_select_bank') ?>"
                                       autocomplete="off">
                                <div id="bank-autocomplete" class="autocomplete-dropdown"></div>
                                <small class="form-text text-muted"><?= __('messages.bank_autocomplete_hint') ?></small>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="description" class="form-label">
                                    <?= __('transactions.description') ?>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="description" 
                                       name="description" 
                                       value="<?= $data['description'] ?? '' ?>">
                            </div>
                        </div>
                        
                        <!-- Amounts Section -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="mb-0"><?= __('amount') ?> (RMB)</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label for="goods_amount_rmb" class="form-label">
                                            <?= __('transactions.goods_amount') ?>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">¥</span>
                                            <input type="number" 
                                                   class="form-control calculate-total" 
                                                   id="goods_amount_rmb" 
                                                   name="goods_amount_rmb" 
                                                   step="0.01" 
                                                   min="0"
                                                   value="<?= $data['goods_amount_rmb'] ?? '0' ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="commission_rmb" class="form-label">
                                            <?= __('transactions.commission') ?>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">¥</span>
                                            <input type="number" 
                                                   class="form-control calculate-total" 
                                                   id="commission_rmb" 
                                                   name="commission_rmb" 
                                                   step="0.01" 
                                                   min="0"
                                                   value="<?= $data['commission_rmb'] ?? '0' ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="total_amount_rmb" class="form-label">
                                            <?= __('transactions.total_amount') ?>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">¥</span>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="total_amount_rmb" 
                                                   name="total_amount_rmb" 
                                                   step="0.01" 
                                                   readonly
                                                   value="<?= $data['total_amount_rmb'] ?? '0' ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="payment_rmb" class="form-label">
                                            <?= __('transactions.payment') ?>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">¥</span>
                                            <input type="number" 
                                                   class="form-control calculate-balance" 
                                                   id="payment_rmb" 
                                                   name="payment_rmb" 
                                                   step="0.01" 
                                                   min="0"
                                                   value="<?= $data['payment_rmb'] ?? '0' ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-3 offset-md-9">
                                        <label for="balance_rmb" class="form-label">
                                            <?= __('balance') ?>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">¥</span>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="balance_rmb" 
                                                   name="balance_rmb" 
                                                   step="0.01" 
                                                   readonly
                                                   value="<?= $data['balance_rmb'] ?? '0' ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-3">
                                        <label for="payment_sdg" class="form-label">
                                            <?= __('transactions.payment') ?> (SDG)
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">SDG</span>
                                            <input type="number" 
                                                   class="form-control currency-convert" 
                                                   id="payment_sdg" 
                                                   name="payment_sdg" 
                                                   data-currency="SDG"
                                                   step="0.01" 
                                                   min="0"
                                                   value="<?= $data['payment_sdg'] ?? '0' ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="exchange_rate" class="form-label">
                                            <?= __('settings.exchange_rates') ?>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">1 USD =</span>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="exchange_rate_usd_sdg" 
                                                   step="0.01" 
                                                   value="<?= $exchangeRate ?? '600' ?>"
                                                   readonly>
                                            <span class="input-group-text">SDG</span>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="payment_aed" class="form-label">
                                            <?= __('transactions.payment') ?> (AED)
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">AED</span>
                                            <input type="number" 
                                                   class="form-control currency-convert" 
                                                   id="payment_aed" 
                                                   name="payment_aed" 
                                                   data-currency="AED"
                                                   step="0.01" 
                                                   min="0"
                                                   value="<?= $data['payment_aed'] ?? '0' ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="exchange_rate_usd_aed" class="form-label">
                                            USD → AED
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">1 USD =</span>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="exchange_rate_usd_aed" 
                                                   step="0.01" 
                                                   value="<?= number_format($usdToAed, 2) ?>"
                                                   readonly>
                                            <span class="input-group-text">AED</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- USD Section -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="mb-0"><?= __('transactions.shipping') ?> (USD)</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label for="shipping_usd" class="form-label">
                                            <?= __('transactions.shipping') ?>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="shipping_usd" 
                                                   name="shipping_usd" 
                                                   step="0.01" 
                                                   min="0"
                                                   value="<?= $data['shipping_usd'] ?? '0' ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="payment_usd" class="form-label">
                                            <?= __('transactions.payment') ?>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="payment_usd" 
                                                   name="payment_usd" 
                                                   step="0.01" 
                                                   min="0"
                                                   value="<?= $data['payment_usd'] ?? '0' ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="balance_usd" class="form-label">
                                            <?= __('balance') ?>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="balance_usd" 
                                                   name="balance_usd" 
                                                   step="0.01" 
                                                   readonly
                                                   value="<?= $data['balance_usd'] ?? '0' ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-4 offset-md-8">
                                        <label for="balance_aed" class="form-label">
                                            <?= __('balance') ?> (AED)
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">AED</span>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="balance_aed" 
                                                   name="balance_aed" 
                                                   step="0.01" 
                                                   readonly
                                                   value="<?= $data['balance_aed'] ?? '0' ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Multi-Currency Payment Section -->
<div class="row mb-3">
    <div class="col-md-4">
        <label for="payment_currency" class="form-label">
            <?= __('transactions.payment_currency') ?> <span class="text-danger">*</span>
        </label>
        <select name="payment_currency" id="payment_currency" class="form-select" required>
            <option value="RMB">RMB (¥)</option>
            <option value="USD">USD ($)</option>
            <option value="SDG">SDG</option>
            <option value="AED">AED</option>
        </select>
    </div>
    
    <div class="col-md-4">
        <label for="payment_amount" class="form-label">
            <?= __('transactions.payment_amount') ?>
        </label>
        <input type="number" 
               class="form-control" 
               id="payment_amount" 
               name="payment_amount" 
               step="0.01" 
               min="0">
    </div>
    
    <div class="col-md-4">
        <label class="form-label"><?= __('settings.exchange_rates') ?></label>
        <div class="small text-muted">
            <div>1 USD = <?= $rates['exchange_rate_usd_rmb'] ?? '200' ?> RMB</div>
            <div>1 SDG = <?= $rates['exchange_rate_sdg_rmb'] ?? '0.012' ?> RMB</div>
            <div>1 AED = <?= $rates['exchange_rate_aed_rmb'] ?? '1.96' ?> RMB</div>
        </div>
    </div>
</div>

<!-- Claim Number Search Section -->
<div class="row mb-3">
    <div class="col-md-6">
        <label for="claim_number" class="form-label">
            <?= __('loadings.claim_number') ?>
        </label>
        <div class="input-group">
            <input type="text" 
                   class="form-control" 
                   id="claim_number" 
                   name="claim_number" 
                   placeholder="<?= __('transactions.search_by_claim') ?>">
            <button class="btn btn-outline-secondary" type="button" onclick="searchClaim()">
                <i class="bi bi-search"></i> <?= __('search') ?>
            </button>
        </div>
    </div>
    
    <div class="col-md-6" id="claimInfo" style="display: none;">
        <div class="alert alert-info mb-0">
            <small id="claimDetails"></small>
        </div>
    </div>
</div>

                        <!-- Cashbox Effect -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="affects_cashbox" 
                                               name="affects_cashbox" 
                                               value="1"
                                               <?= ($data['affects_cashbox'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="affects_cashbox">
                                            <?= __('transactions.affects_cashbox') ?>
                                        </label>
                                    </div>
                                </h5>
                            </div>
                            <div class="card-body" id="cashbox-section" style="display: none;">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label for="cashbox_type" class="form-label">
                                            <?= __('cashbox.movement_type') ?>
                                        </label>
                                        <select class="form-select" id="cashbox_type" name="cashbox_type">
                                            <option value="in"><?= __('cashbox.in') ?></option>
                                            <option value="out"><?= __('cashbox.out') ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="cashbox_category" class="form-label">
                                            <?= __('cashbox.category') ?>
                                        </label>
                                        <select class="form-select" id="cashbox_category" name="cashbox_category">
                                            <option value="customer_transfer"><?= __('cashbox.customer_transfer') ?></option>
                                            <option value="office_transfer"><?= __('cashbox.office_transfer') ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="tt_number" class="form-label">
                                            <?= __('cashbox.tt_number') ?>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="tt_number" 
                                               name="tt_number">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="receipt_no" class="form-label">
                                            <?= __('cashbox.receipt_no') ?>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="receipt_no" 
                                               name="receipt_no">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle"></i>
                            <?= __('validation.required') ?> *
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/transactions" class="btn btn-secondary">
                                <i class="bi bi-arrow-<?= isRTL() ? 'right' : 'left' ?>"></i> <?= __('back') ?>
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> <?= __('save') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.autocomplete-dropdown {
    position: absolute;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 200px;
    overflow-y: auto;
    display: none;
    z-index: 1000;
    width: 100%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-top: 2px;
}

.autocomplete-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s;
}

.autocomplete-item:last-child {
    border-bottom: none;
}

.autocomplete-item:hover {
    background-color: #f5f5f5;
}

.autocomplete-item.selected {
    background-color: #e9ecef;
}
</style>

<script>
// Exchange rates from settings
const exchangeRates = {
    USD_SDG: <?= $usdToSdg ?>,
    SDG_USD: <?= 1 / $usdToSdg ?>,
    USD_AED: <?= $usdToAed ?>,
    AED_USD: <?= 1 / $usdToAed ?>
};

// Bank autocomplete
const banksList = <?= json_encode($banksList ?? []) ?>;
const bankInput = document.getElementById('bank_name');
const autocompleteContainer = document.getElementById('bank-autocomplete');
let selectedIndex = -1;

function showBankSuggestions(value) {
    const filtered = banksList.filter(bank => 
        bank.toLowerCase().includes(value.toLowerCase())
    );
    
    if (filtered.length === 0 || value === '') {
        autocompleteContainer.style.display = 'none';
        selectedIndex = -1;
        return;
    }
    
    autocompleteContainer.innerHTML = filtered
        .map((bank, index) => `<div class="autocomplete-item" data-index="${index}">${escapeHtml(bank)}</div>`)
        .join('');
    
    autocompleteContainer.style.display = 'block';
    
    // Add click handlers
    autocompleteContainer.querySelectorAll('.autocomplete-item').forEach(item => {
        item.addEventListener('click', function() {
            bankInput.value = this.textContent;
            autocompleteContainer.style.display = 'none';
            selectedIndex = -1;
        });
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

bankInput.addEventListener('input', function() {
    showBankSuggestions(this.value);
});

bankInput.addEventListener('focus', function() {
    if (this.value) showBankSuggestions(this.value);
});

// Keyboard navigation
bankInput.addEventListener('keydown', function(e) {
    const items = autocompleteContainer.querySelectorAll('.autocomplete-item');
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
        updateSelection(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex = Math.max(selectedIndex - 1, -1);
        updateSelection(items);
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
        e.preventDefault();
        bankInput.value = items[selectedIndex].textContent;
        autocompleteContainer.style.display = 'none';
        selectedIndex = -1;
    } else if (e.key === 'Escape') {
        autocompleteContainer.style.display = 'none';
        selectedIndex = -1;
    }
});

function updateSelection(items) {
    items.forEach((item, index) => {
        if (index === selectedIndex) {
            item.classList.add('selected');
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.classList.remove('selected');
        }
    });
}

// Hide dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!bankInput.contains(e.target) && !autocompleteContainer.contains(e.target)) {
        autocompleteContainer.style.display = 'none';
        selectedIndex = -1;
    }
});

// Calculate totals
document.querySelectorAll('.calculate-total').forEach(input => {
    input.addEventListener('input', calculateTotal);
});

document.querySelectorAll('.calculate-balance').forEach(input => {
    input.addEventListener('input', calculateBalance);
});

function calculateTotal() {
    const goods = parseFloat(document.getElementById('goods_amount_rmb').value) || 0;
    const commission = parseFloat(document.getElementById('commission_rmb').value) || 0;
    document.getElementById('total_amount_rmb').value = (goods + commission).toFixed(2);
    calculateBalance();
}

function calculateBalance() {
    // RMB balance
    const total = parseFloat(document.getElementById('total_amount_rmb').value) || 0;
    const payment = parseFloat(document.getElementById('payment_rmb').value) || 0;
    document.getElementById('balance_rmb').value = (total - payment).toFixed(2);
    
    // USD balance
    const shipping = parseFloat(document.getElementById('shipping_usd').value) || 0;
    const paymentUsd = parseFloat(document.getElementById('payment_usd').value) || 0;
    document.getElementById('balance_usd').value = (shipping - paymentUsd).toFixed(2);
    
    // AED balance (currently set to 0 since no AED charges are defined, only payments)
    const paymentAed = parseFloat(document.getElementById('payment_aed').value) || 0;
    document.getElementById('balance_aed').value = (0 - paymentAed).toFixed(2);
}

// Currency conversion between USD and SDG
document.getElementById('payment_usd').addEventListener('input', function() {
    const usdAmount = parseFloat(this.value) || 0;
    const sdgAmount = usdAmount * exchangeRates.USD_SDG;
    document.getElementById('payment_sdg').value = sdgAmount.toFixed(2);
    calculateBalance();
});

document.getElementById('payment_sdg').addEventListener('input', function() {
    const sdgAmount = parseFloat(this.value) || 0;
    const usdAmount = sdgAmount * exchangeRates.SDG_USD;
    document.getElementById('payment_usd').value = usdAmount.toFixed(2);
    calculateBalance();
});

// AED currency conversion
document.getElementById('payment_aed').addEventListener('input', function() {
    const aedAmount = parseFloat(this.value) || 0;
    const usdAmount = aedAmount * exchangeRates.AED_USD;
    // Update USD payment field (this might affect other currencies)
    const currentUsd = parseFloat(document.getElementById('payment_usd').value) || 0;
    // For now, just calculate the balance without updating USD field to avoid conflicts
    calculateBalance();
});

// Shipping USD balance
document.getElementById('shipping_usd').addEventListener('input', calculateBalance);

// Cashbox section toggle
document.getElementById('affects_cashbox').addEventListener('change', function() {
    document.getElementById('cashbox-section').style.display = this.checked ? 'block' : 'none';
    
    // Auto-fill cashbox amounts
    if (this.checked) {
        // Hidden fields for cashbox amounts - they'll be set when submitting
    }
});

// Initialize
calculateTotal();
if (document.getElementById('affects_cashbox').checked) {
    document.getElementById('cashbox-section').style.display = 'block';
}

// Before form submission, set cashbox amounts
document.getElementById('transaction-form').addEventListener('submit', function(e) {
    if (document.getElementById('affects_cashbox').checked) {
        // Create hidden inputs for cashbox amounts
        const cashboxRmb = document.createElement('input');
        cashboxRmb.type = 'hidden';
        cashboxRmb.name = 'cashbox_rmb';
        cashboxRmb.value = document.getElementById('payment_rmb').value;
        this.appendChild(cashboxRmb);
        
        const cashboxUsd = document.createElement('input');
        cashboxUsd.type = 'hidden';
        cashboxUsd.name = 'cashbox_usd';
        cashboxUsd.value = document.getElementById('payment_usd').value;
        this.appendChild(cashboxUsd);
        
        const cashboxSdg = document.createElement('input');
        cashboxSdg.type = 'hidden';
        cashboxSdg.name = 'cashbox_sdg';
        cashboxSdg.value = document.getElementById('payment_sdg').value;
        this.appendChild(cashboxSdg);
        
        const cashboxAed = document.createElement('input');
        cashboxAed.type = 'hidden';
        cashboxAed.name = 'cashbox_aed';
        cashboxAed.value = document.getElementById('payment_aed').value;
        this.appendChild(cashboxAed);
    }
});
function searchClaim() {
    const claimNumber = document.getElementById('claim_number').value;
    if (!claimNumber) {
        alert('<?= __('messages.claim_number_required') ?>');
        return;
    }
    
    fetch(`/transactions/search-by-claim?claim_number=${encodeURIComponent(claimNumber)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const loading = data.loading;
                document.getElementById('claimInfo').style.display = 'block';
                document.getElementById('claimDetails').innerHTML = `
                    <strong><?= __('loadings.loading_no') ?>:</strong> ${loading.loading_no}<br>
                    <strong><?= __('loadings.container_no') ?>:</strong> ${loading.container_no}<br>
                    <strong><?= __('clients.client') ?>:</strong> ${loading.client_code} - ${loading.client_name}
                `;
                
                // Auto-fill client information
                document.getElementById('client_code').value = loading.client_code;
                document.getElementById('client_name').value = loading.client_name;
                if (document.getElementById('client_id')) {
                    document.getElementById('client_id').value = loading.client_id || '';
                }
                
                // Set loading_id hidden field
                if (!document.getElementById('loading_id')) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'loading_id';
                    hiddenInput.id = 'loading_id';
                    hiddenInput.value = loading.id;
                    document.getElementById('transaction-form').appendChild(hiddenInput);
                } else {
                    document.getElementById('loading_id').value = loading.id;
                }
            } else {
                alert(data.message);
                document.getElementById('claimInfo').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?= __('error') ?>');
        });
}

// Update calculation when currency changes
document.getElementById('payment_currency').addEventListener('change', function() {
    calculateTotals();
});

function calculateTotals() {
    // Your existing calculation logic
    // Update to handle multi-currency conversions
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>