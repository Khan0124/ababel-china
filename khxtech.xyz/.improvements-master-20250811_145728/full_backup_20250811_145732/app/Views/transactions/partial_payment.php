<?php
// app/Views/transactions/partial_payment.php
include __DIR__ . '/../layouts/header.php';

// Get exchange rates
$db = \App\Core\Database::getInstance();
$stmt = $db->query("SELECT * FROM settings WHERE setting_key LIKE 'exchange_rate_%'");
$ratesRaw = $stmt->fetchAll();
$exchangeRates = [];
foreach ($ratesRaw as $rate) {
    $exchangeRates[$rate['setting_key']] = $rate['setting_value'];
}

$usdToRmb = floatval($exchangeRates['exchange_rate_usd_rmb'] ?? 7.20);
$sdgToRmb = floatval($exchangeRates['exchange_rate_sdg_rmb'] ?? 0.012);
$aedToRmb = floatval($exchangeRates['exchange_rate_aed_rmb'] ?? 1.96);

// Calculate total outstanding balance
$totalBalanceRmb = $transaction['balance_rmb'] + 
                   ($transaction['balance_usd'] * $usdToRmb) + 
                   ($transaction['balance_sdg'] * $sdgToRmb) + 
                   ($transaction['balance_aed'] * $aedToRmb);

// Get previous payments for this transaction
$previousPayments = $db->query("SELECT * FROM transactions WHERE parent_transaction_id = ? AND status = 'approved' ORDER BY created_at DESC", [$transaction['id']])->fetchAll();

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!-- Enhanced CSS for Partial Payment -->
<link rel="stylesheet" href="/assets/css/partial-payment.css">

<div class="col-md-12 p-4">
    <div class="row">
        <!-- Main Payment Form -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>
                            <?= __('transactions.partial_payment') ?>
                        </h4>
                        <span class="badge bg-light text-dark">#<?= htmlspecialchars($transaction['transaction_no']) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Alerts -->
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($_GET['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($_GET['success']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Transaction Info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted"><?= __('clients.client') ?></h6>
                            <p class="fw-bold"><?= htmlspecialchars($transaction['client_name']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted"><?= __('transactions.transaction_date') ?></h6>
                            <p class="fw-bold"><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></p>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <?php
                    $originalTotal = $transaction['total_amount_rmb'] + ($transaction['shipping_usd'] * $usdToRmb);
                    $paidAmount = ($transaction['total_amount_rmb'] + ($transaction['shipping_usd'] * $usdToRmb)) - $totalBalanceRmb;
                    $progressPercent = $originalTotal > 0 ? ($paidAmount / $originalTotal) * 100 : 0;
                    ?>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><?= __('transactions.payment_progress') ?></h6>
                            <small class="text-muted"><?= number_format($progressPercent, 1) ?>% <?= __('paid') ?></small>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" 
                                 role="progressbar" 
                                 style="width: <?= $progressPercent ?>%"
                                 aria-valuenow="<?= $progressPercent ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted"><?= __('paid') ?>: ¥<?= number_format($paidAmount, 2) ?></small>
                            <small class="text-muted"><?= __('remaining') ?>: ¥<?= number_format($totalBalanceRmb, 2) ?></small>
                        </div>
                    </div>
                    
                    <!-- Outstanding Balances -->
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-balance-scale me-2"></i>
                                <?= __('transactions.outstanding_balances') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if ($transaction['balance_rmb'] > 0): ?>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <div class="d-flex align-items-center p-2 border rounded bg-light">
                                        <div class="text-primary me-2">
                                            <i class="fas fa-yen-sign"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">RMB</small>
                                            <strong>¥<?= number_format($transaction['balance_rmb'], 2) ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($transaction['balance_usd'] > 0): ?>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <div class="d-flex align-items-center p-2 border rounded bg-light">
                                        <div class="text-success me-2">
                                            <i class="fas fa-dollar-sign"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">USD</small>
                                            <strong>$<?= number_format($transaction['balance_usd'], 2) ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($transaction['balance_sdg'] > 0): ?>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <div class="d-flex align-items-center p-2 border rounded bg-light">
                                        <div class="text-warning me-2">
                                            <i class="fas fa-coins"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">SDG</small>
                                            <strong><?= number_format($transaction['balance_sdg'], 2) ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($transaction['balance_aed'] > 0): ?>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <div class="d-flex align-items-center p-2 border rounded bg-light">
                                        <div class="text-info me-2">
                                            <i class="fas fa-money-bill"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">AED</small>
                                            <strong><?= number_format($transaction['balance_aed'], 2) ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <hr>
                            <div class="text-center">
                                <small class="text-muted"><?= __('transactions.total_equivalent') ?>:</small>
                                <strong class="text-primary ms-2">¥<?= number_format($totalBalanceRmb, 2) ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Form -->
                    <form method="POST" action="/transactions/partial-payment/<?= $transaction['id'] ?>" id="payment-form" class="needs-validation" novalidate>
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="transaction_id" value="<?= $transaction['id'] ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="bank_name" class="form-label"><?= __('cashbox.bank_name') ?> *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="bank_name" 
                                       name="bank_name" 
                                       list="banks-list"
                                       required>
                                <datalist id="banks-list">
                                    <?php foreach ($banksList as $bank): ?>
                                    <option value="<?= htmlspecialchars($bank) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="payment_date" class="form-label"><?= __('transactions.payment_date') ?></label>
                                <input type="date" 
                                       class="form-control" 
                                       id="payment_date" 
                                       value="<?= date('Y-m-d') ?>" 
                                       readonly>
                            </div>
                        </div>
                        
                        <h5 class="mb-3"><?= __('transactions.payment_amounts') ?></h5>
                        
                        <div class="row">
                            <?php if ($transaction['balance_rmb'] > 0): ?>
                            <div class="col-md-6 mb-3">
                                <label for="payment_rmb" class="form-label"><?= __('amount') ?> (RMB)</label>
                                <div class="input-group">
                                    <span class="input-group-text">¥</span>
                                    <input type="number" 
                                           class="form-control payment-amount" 
                                           id="payment_rmb" 
                                           name="payment_rmb" 
                                           step="0.01" 
                                           min="0" 
                                           max="<?= $transaction['balance_rmb'] ?>"
                                           value="0"
                                           data-currency="RMB"
                                           data-max="<?= $transaction['balance_rmb'] ?>">
                                    <button type="button" class="btn btn-outline-secondary pay-full" data-currency="RMB">
                                        <?= __('transactions.pay_full') ?>
                                    </button>
                                </div>
                                <small class="text-muted">
                                    <?= __('transactions.max') ?>: ¥<?= number_format($transaction['balance_rmb'], 2) ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($transaction['balance_usd'] > 0): ?>
                            <div class="col-md-6 mb-3">
                                <label for="payment_usd" class="form-label"><?= __('amount') ?> (USD)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control payment-amount" 
                                           id="payment_usd" 
                                           name="payment_usd" 
                                           step="0.01" 
                                           min="0" 
                                           max="<?= $transaction['balance_usd'] ?>"
                                           value="0"
                                           data-currency="USD"
                                           data-max="<?= $transaction['balance_usd'] ?>">
                                    <button type="button" class="btn btn-outline-secondary pay-full" data-currency="USD">
                                        <?= __('transactions.pay_full') ?>
                                    </button>
                                </div>
                                <small class="text-muted">
                                    <?= __('transactions.max') ?>: $<?= number_format($transaction['balance_usd'], 2) ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($transaction['balance_sdg'] > 0): ?>
                            <div class="col-md-6 mb-3">
                                <label for="payment_sdg" class="form-label"><?= __('amount') ?> (SDG)</label>
                                <div class="input-group">
                                    <span class="input-group-text">SDG</span>
                                    <input type="number" 
                                           class="form-control payment-amount" 
                                           id="payment_sdg" 
                                           name="payment_sdg" 
                                           step="0.01" 
                                           min="0" 
                                           max="<?= $transaction['balance_sdg'] ?>"
                                           value="0"
                                           data-currency="SDG"
                                           data-max="<?= $transaction['balance_sdg'] ?>">
                                    <button type="button" class="btn btn-outline-secondary pay-full" data-currency="SDG">
                                        <?= __('transactions.pay_full') ?>
                                    </button>
                                </div>
                                <small class="text-muted">
                                    <?= __('transactions.max') ?>: <?= number_format($transaction['balance_sdg'], 2) ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($transaction['balance_aed'] > 0): ?>
                            <div class="col-md-6 mb-3">
                                <label for="payment_aed" class="form-label"><?= __('amount') ?> (AED)</label>
                                <div class="input-group">
                                    <span class="input-group-text">AED</span>
                                    <input type="number" 
                                           class="form-control payment-amount" 
                                           id="payment_aed" 
                                           name="payment_aed" 
                                           step="0.01" 
                                           min="0" 
                                           max="<?= $transaction['balance_aed'] ?>"
                                           value="0"
                                           data-currency="AED"
                                           data-max="<?= $transaction['balance_aed'] ?>">
                                    <button type="button" class="btn btn-outline-secondary pay-full" data-currency="AED">
                                        <?= __('transactions.pay_full') ?>
                                    </button>
                                </div>
                                <small class="text-muted">
                                    <?= __('transactions.max') ?>: <?= number_format($transaction['balance_aed'], 2) ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Total Summary -->
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h5 class="card-title"><?= __('transactions.payment_summary') ?></h5>
                                <div id="payment-summary">
                                    <p><?= __('messages.select_payment_amounts') ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/transactions/view/<?= $transaction['id'] ?>" class="btn btn-secondary">
                                <?= __('cancel') ?>
                            </a>
                            <button type="submit" class="btn btn-primary" id="submit-payment" disabled>
                                <?= __('transactions.process_payment') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar with Additional Information -->
        <div class="col-md-4">
            <!-- Transaction Summary -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <?= __('transactions.transaction_summary') ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted"><?= __('transactions.original_amount') ?>:</small>
                        <div class="fw-bold">¥<?= number_format($originalTotal, 2) ?></div>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted"><?= __('transactions.paid_amount') ?>:</small>
                        <div class="fw-bold text-success">¥<?= number_format($paidAmount, 2) ?></div>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted"><?= __('transactions.remaining_amount') ?>:</small>
                        <div class="fw-bold text-danger">¥<?= number_format($totalBalanceRmb, 2) ?></div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <small class="text-muted"><?= __('transactions.completion_rate') ?></small>
                        <div class="h5 text-primary"><?= number_format($progressPercent, 1) ?>%</div>
                    </div>
                </div>
            </div>
            
            <!-- Exchange Rates -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="fas fa-exchange-alt me-2"></i>
                        <?= __('settings.exchange_rates') ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-sm">
                        <div class="col-6">
                            <small class="text-muted">USD → RMB:</small>
                            <div class="fw-bold"><?= number_format($usdToRmb, 4) ?></div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">SDG → RMB:</small>
                            <div class="fw-bold"><?= number_format($sdgToRmb, 4) ?></div>
                        </div>
                        <div class="col-6 mt-2">
                            <small class="text-muted">AED → RMB:</small>
                            <div class="fw-bold"><?= number_format($aedToRmb, 4) ?></div>
                        </div>
                    </div>
                    <hr>
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?= __('last_updated') ?>: <?= date('Y-m-d H:i') ?>
                    </small>
                </div>
            </div>
            
            <!-- Previous Payments -->
            <?php if (!empty($previousPayments)): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        <?= __('transactions.previous_payments') ?> (<?= count($previousPayments) ?>)
                    </h6>
                </div>
                <div class="card-body p-2">
                    <div class="max-height-200 overflow-auto">
                        <?php foreach ($previousPayments as $payment): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                            <div>
                                <small class="text-muted"><?= date('M d, Y', strtotime($payment['created_at'])) ?></small>
                                <div class="small">
                                    <?php if ($payment['payment_rmb'] > 0): ?>
                                        <span class="badge bg-primary">¥<?= number_format($payment['payment_rmb'], 2) ?></span>
                                    <?php endif; ?>
                                    <?php if ($payment['payment_usd'] > 0): ?>
                                        <span class="badge bg-success">$<?= number_format($payment['payment_usd'], 2) ?></span>
                                    <?php endif; ?>
                                    <?php if ($payment['payment_sdg'] > 0): ?>
                                        <span class="badge bg-warning"><?= number_format($payment['payment_sdg'], 2) ?> SDG</span>
                                    <?php endif; ?>
                                    <?php if ($payment['payment_aed'] > 0): ?>
                                        <span class="badge bg-info"><?= number_format($payment['payment_aed'], 2) ?> AED</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <small class="text-success">
                                <i class="fas fa-check-circle"></i>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Payment Tips -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        <?= __('payment_tips') ?>
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <?= __('tips.multiple_currencies') ?>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <?= __('tips.partial_payment_allowed') ?>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <?= __('tips.automatic_calculation') ?>
                        </li>
                        <li>
                            <i class="fas fa-check text-success me-2"></i>
                            <?= __('tips.immediate_balance_update') ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.max-height-200 {
    max-height: 200px;
}

.payment-amount:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.currency-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.currency-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.progress {
    border-radius: 10px;
}

.progress-bar {
    border-radius: 10px;
}

@media (max-width: 768px) {
    .col-md-8, .col-md-4 {
        margin-bottom: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration
    const config = {
        exchangeRates: {
            usdToRmb: <?= $usdToRmb ?>,
            sdgToRmb: <?= $sdgToRmb ?>,
            aedToRmb: <?= $aedToRmb ?>
        },
        maxBalances: {
            rmb: <?= $transaction['balance_rmb'] ?>,
            usd: <?= $transaction['balance_usd'] ?>,
            sdg: <?= $transaction['balance_sdg'] ?>,
            aed: <?= $transaction['balance_aed'] ?>
        },
        currencySymbols: {
            rmb: '¥',
            usd: '$',
            sdg: 'SDG',
            aed: 'AED'
        }
    };
    
    // Initialize pay full buttons
    document.querySelectorAll('.pay-full').forEach(function(button) {
        button.addEventListener('click', function() {
            const currency = this.getAttribute('data-currency').toLowerCase();
            const input = document.getElementById('payment_' + currency);
            const maxValue = config.maxBalances[currency];
            
            if (input && maxValue) {
                input.value = maxValue.toFixed(2);
                updatePaymentSummary();
                
                // Visual feedback
                this.classList.add('btn-success');
                this.classList.remove('btn-outline-secondary');
                setTimeout(() => {
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-secondary');
                }, 1000);
            }
        });
    });
    
    // Enhanced input handlers
    document.querySelectorAll('.payment-amount').forEach(function(input) {
        input.addEventListener('input', function() {
            validateSingleInput(this);
            updatePaymentSummary();
        });
    });
    
    // Real-time validation for single input
    function validateSingleInput(input) {
        const value = parseFloat(input.value) || 0;
        const currency = input.getAttribute('data-currency').toLowerCase();
        const maxValue = config.maxBalances[currency];
        
        // Remove existing validation classes
        input.classList.remove('is-valid', 'is-invalid');
        const feedbacks = input.parentNode.querySelectorAll('.invalid-feedback, .valid-feedback');
        feedbacks.forEach(fb => fb.remove());
        
        if (value < 0) {
            input.classList.add('is-invalid');
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = 'Amount cannot be negative';
            input.parentNode.appendChild(feedback);
        } else if (value > maxValue) {
            input.classList.add('is-invalid');
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = `Amount exceeds maximum balance of ${config.currencySymbols[currency]}${maxValue.toFixed(2)}`;
            input.parentNode.appendChild(feedback);
        } else if (value > 0) {
            input.classList.add('is-valid');
            const feedback = document.createElement('div');
            feedback.className = 'valid-feedback';
            feedback.textContent = 'Valid amount';
            input.parentNode.appendChild(feedback);
        }
    }
    
    // Enhanced payment summary with animations
    function updatePaymentSummary() {
        const amounts = {
            rmb: parseFloat(document.getElementById('payment_rmb')?.value) || 0,
            usd: parseFloat(document.getElementById('payment_usd')?.value) || 0,
            sdg: parseFloat(document.getElementById('payment_sdg')?.value) || 0,
            aed: parseFloat(document.getElementById('payment_aed')?.value) || 0
        };
        
        let summary = '<div class="row g-2">';
        let hasPayment = false;
        let totalRmbEquivalent = 0;
        
        // Build payment summary
        Object.keys(amounts).forEach(currency => {
            if (amounts[currency] > 0) {
                const symbol = config.currencySymbols[currency];
                const rate = currency === 'rmb' ? 1 : config.exchangeRates[currency + 'ToRmb'];
                
                totalRmbEquivalent += amounts[currency] * rate;
                
                summary += `
                    <div class="col-6 col-md-3">
                        <div class="text-center p-2 bg-light rounded">
                            <small class="text-muted d-block">${currency.toUpperCase()}</small>
                            <strong class="text-primary">${symbol}${amounts[currency].toFixed(2)}</strong>
                        </div>
                    </div>`;
                hasPayment = true;
            }
        });
        
        summary += '</div>';
        
        if (hasPayment) {
            // Add total equivalent
            summary += `
                <hr class="my-3">
                <div class="text-center">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted d-block">Total Equivalent</small>
                            <h5 class="text-success mb-0">¥${totalRmbEquivalent.toFixed(2)}</h5>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Remaining After Payment</small>
                            <h6 class="text-danger mb-0">¥${(<?= $totalBalanceRmb ?> - totalRmbEquivalent).toFixed(2)}</h6>
                        </div>
                    </div>
                </div>`;
            
            const summaryElement = document.getElementById('payment-summary');
            if (summaryElement) summaryElement.innerHTML = summary;
            
            const submitBtn = document.getElementById('submit-payment');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-secondary');
                submitBtn.classList.add('btn-primary');
            }
        } else {
            const summaryElement = document.getElementById('payment-summary');
            if (summaryElement) {
                summaryElement.innerHTML = `
                    <div class="text-center text-muted">
                        <i class="fas fa-hand-point-right fa-2x mb-2"></i>
                        <p>Select payment amounts above to see summary</p>
                    </div>`;
            }
            
            const submitBtn = document.getElementById('submit-payment');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.remove('btn-primary');
                submitBtn.classList.add('btn-secondary');
            }
        }
        
        // Update progress indicator
        updateProgressIndicator(totalRmbEquivalent);
    }
    
    // Enhanced form submission with loading state
    const paymentForm = document.getElementById('payment-form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submit-payment');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            submitBtn.disabled = true;
            
            // Add confirmation modal
            showConfirmationModal(() => {
                // Submit form after confirmation
                paymentForm.submit();
            }, () => {
                // Reset button if cancelled
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    
    // Comprehensive form validation
    function validateForm() {
        let isValid = true;
        let hasPayment = false;
        
        document.querySelectorAll('.payment-amount').forEach(function(input) {
            const value = parseFloat(input.value) || 0;
            const currency = input.getAttribute('data-currency').toLowerCase();
            const maxValue = config.maxBalances[currency];
            
            // Clear previous validation
            input.classList.remove('is-valid', 'is-invalid');
            const feedbacks = input.parentNode.querySelectorAll('.invalid-feedback');
            feedbacks.forEach(fb => fb.remove());
            
            if (value < 0) {
                input.classList.add('is-invalid');
                const feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.textContent = 'Amount cannot be negative';
                input.parentNode.appendChild(feedback);
                isValid = false;
            } else if (value > maxValue) {
                input.classList.add('is-invalid');
                const feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.textContent = 'Exceeds maximum balance';
                input.parentNode.appendChild(feedback);
                isValid = false;
            } else if (value > 0) {
                input.classList.add('is-valid');
                hasPayment = true;
            }
        });
        
        if (!hasPayment) {
            showNotification('Please enter at least one payment amount', 'warning');
            isValid = false;
        }
        
        // Validate bank name
        const bankNameInput = document.getElementById('bank_name');
        const bankName = bankNameInput ? bankNameInput.value.trim() : '';
        if (!bankName) {
            if (bankNameInput) bankNameInput.classList.add('is-invalid');
            showNotification('Please select or enter a bank name', 'warning');
            isValid = false;
        }
        
        return isValid;
    }
    
    // Show confirmation modal
    function showConfirmationModal(onConfirm, onCancel) {
        const amounts = [];
        document.querySelectorAll('.payment-amount').forEach(function(input) {
            const value = parseFloat(input.value) || 0;
            if (value > 0) {
                const currency = input.getAttribute('data-currency');
                amounts.push(`${config.currencySymbols[currency.toLowerCase()]}${value.toFixed(2)} ${currency}`);
            }
        });
        
        const message = `Confirm payment of: ${amounts.join(', ')}`;
        
        if (confirm(message)) {
            onConfirm();
        } else {
            onCancel();
        }
    }
    
    // Show notifications
    function showNotification(message, type = 'info') {
        const alertClass = `alert-${type}`;
        const icon = {
            success: 'fa-check-circle',
            warning: 'fa-exclamation-triangle',
            error: 'fa-times-circle',
            info: 'fa-info-circle'
        }[type] || 'fa-info-circle';
        
        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 350px;';
        notification.innerHTML = `
            <i class="fas ${icon} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
    
    // Initialize summary on page load
    updatePaymentSummary();
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>