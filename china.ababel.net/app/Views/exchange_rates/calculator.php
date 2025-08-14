<?php
// app/Views/exchange_rates/calculator.php
include __DIR__ . '/../layouts/header.php'; 
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-calculator"></i> <?= __('exchange_rates.calculator') ?></h1>
        <div class="btn-group">
            <a href="/exchange-rates" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> <?= __('back') ?>
            </a>
            <button type="button" class="btn btn-primary" onclick="refreshRates()">
                <i class="bi bi-arrow-clockwise"></i> <?= __('exchange_rates.refresh_rates') ?>
            </button>
        </div>
    </div>

    <div class="row">
        <!-- Currency Calculator -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-currency-exchange"></i> <?= __('exchange_rates.currency_converter') ?></h5>
                </div>
                <div class="card-body">
                    <form id="conversionForm">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label"><?= __('exchange_rates.amount') ?></label>
                                <input type="number" class="form-control form-control-lg" 
                                       id="amount" placeholder="0.00" step="0.01" min="0.01" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label"><?= __('exchange_rates.from_currency') ?></label>
                                <select class="form-select form-select-lg" id="fromCurrency" required>
                                    <option value=""><?= __('select') ?></option>
                                    <?php foreach ($supported_currencies as $currency): ?>
                                        <option value="<?= $currency ?>"><?= $currency ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label"><?= __('exchange_rates.to_currency') ?></label>
                                <select class="form-select form-select-lg" id="toCurrency" required>
                                    <option value=""><?= __('select') ?></option>
                                    <?php foreach ($supported_currencies as $currency): ?>
                                        <option value="<?= $currency ?>"><?= $currency ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="button" class="btn btn-success btn-lg" onclick="convertCurrency()">
                                        <i class="bi bi-arrow-left-right"></i> <?= __('exchange_rates.convert') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Result Display -->
                    <div id="conversionResult" class="mt-4" style="display: none;">
                        <div class="alert alert-success">
                            <div class="row align-items-center">
                                <div class="col-md-4 text-center">
                                    <div class="h4 mb-0" id="originalAmount">0.00</div>
                                    <div class="text-muted" id="fromCurrencyDisplay">---</div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="bi bi-arrow-right h3"></i>
                                    <div class="small"><?= __('exchange_rates.exchange_rate') ?>: <span id="exchangeRate">0.0000</span></div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="h3 text-success mb-0" id="convertedAmount">0.00</div>
                                    <div class="text-muted" id="toCurrencyDisplay">---</div>
                                </div>
                            </div>
                            <hr>
                            <div class="row text-center">
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> <?= __('exchange_rates.rate_updated') ?>: <span id="rateUpdated">---</span>
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> <?= __('exchange_rates.source') ?>: <span id="rateSource">---</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Conversion Buttons -->
                    <div class="mt-3">
                        <h6><?= __('exchange_rates.quick_convert') ?>:</h6>
                        <div class="btn-group flex-wrap" role="group">
                            <button class="btn btn-outline-secondary btn-sm" onclick="quickConvert(100, 'USD', 'RMB')">
                                $100 → RMB
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="quickConvert(1000, 'RMB', 'USD')">
                                ¥1000 → USD
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="quickConvert(100, 'USD', 'SDG')">
                                $100 → SDG
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="quickConvert(1000, 'SDG', 'USD')">
                                SDG 1000 → USD
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="quickConvert(100, 'USD', 'AED')">
                                $100 → AED
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="quickConvert(1000, 'AED', 'USD')">
                                AED 1000 → USD
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Conversion History -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> <?= __('exchange_rates.conversion_history') ?></h5>
                    <button class="btn btn-sm btn-outline-danger" onclick="clearHistory()">
                        <i class="bi bi-trash"></i> <?= __('exchange_rates.clear_history') ?>
                    </button>
                </div>
                <div class="card-body">
                    <div id="conversionHistory">
                        <p class="text-muted text-center"><?= __('exchange_rates.no_conversions') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Exchange Rates -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> <?= __('exchange_rates.live_rates') ?></h5>
                </div>
                <div class="card-body">
                    <div id="liveRates">
                        <?php if (empty($current_rates)): ?>
                            <div class="alert alert-info text-center">
                                <i class="bi bi-info-circle"></i> <?= __('exchange_rates.no_rates_available') ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($current_rates as $pair => $rate): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <strong><?= str_replace('_', ' → ', $pair) ?></strong>
                                    <br><small class="text-muted"><?= $rate['minutes_ago'] ?? 0 ?> <?= __('exchange_rates.minutes_ago') ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="h6 mb-0"><?= number_format($rate['rate'], 4) ?></div>
                                    <small class="badge bg-<?= $rate['status'] == 'stale' ? 'danger' : 'success' ?>">
                                        <?= __('exchange_rates.' . $rate['status']) ?>
                                    </small>
                                </div>
                            </div>
                            <hr class="my-2">
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Currency Information -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> <?= __('exchange_rates.currency_info') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <div class="h6">RMB</div>
                                <small class="text-muted"><?= __('exchange_rates.chinese_yuan') ?><br>人民币</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="h6">USD</div>
                            <small class="text-muted"><?= __('exchange_rates.us_dollar') ?><br>American</small>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <div class="h6">SDG</div>
                                <small class="text-muted"><?= __('exchange_rates.sudanese_pound') ?><br>جنيه سوداني</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="h6">AED</div>
                            <small class="text-muted"><?= __('exchange_rates.uae_dirham') ?><br>درهم إماراتي</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let conversionHistoryData = JSON.parse(localStorage.getItem('conversionHistory') || '[]');

// Convert currency
function convertCurrency() {
    const amount = document.getElementById('amount').value;
    const fromCurrency = document.getElementById('fromCurrency').value;
    const toCurrency = document.getElementById('toCurrency').value;

    if (!amount || !fromCurrency || !toCurrency) {
        alert('<?= __('exchange_rates.missing_parameters') ?>');
        return;
    }

    if (fromCurrency === toCurrency) {
        alert('<?= __('exchange_rates.same_currency_error') ?>');
        return;
    }

    const formData = new FormData();
    formData.append('amount', amount);
    formData.append('from', fromCurrency);
    formData.append('to', toCurrency);

    fetch('/exchange-rates/convert', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayConversionResult(data.data);
            addToHistory(data.data);
        } else {
            alert(data.error || '<?= __('exchange_rates.conversion_failed') ?>');
        }
    })
    .catch(error => {
        alert('<?= __('error.general') ?>');
    });
}

// Display conversion result
function displayConversionResult(conversion) {
    document.getElementById('originalAmount').textContent = parseFloat(conversion.original_amount).toFixed(2);
    document.getElementById('fromCurrencyDisplay').textContent = conversion.from_currency;
    document.getElementById('convertedAmount').textContent = parseFloat(conversion.amount).toFixed(2);
    document.getElementById('toCurrencyDisplay').textContent = conversion.to_currency;
    document.getElementById('exchangeRate').textContent = parseFloat(conversion.rate).toFixed(4);
    document.getElementById('rateUpdated').textContent = new Date(conversion.rate_last_updated).toLocaleString();
    document.getElementById('rateSource').textContent = conversion.rate_status.replace('_', ' ');
    
    document.getElementById('conversionResult').style.display = 'block';
}

// Quick conversion
function quickConvert(amount, from, to) {
    document.getElementById('amount').value = amount;
    document.getElementById('fromCurrency').value = from;
    document.getElementById('toCurrency').value = to;
    convertCurrency();
}

// Add to conversion history
function addToHistory(conversion) {
    const historyItem = {
        timestamp: new Date().toISOString(),
        original_amount: conversion.original_amount,
        from_currency: conversion.from_currency,
        converted_amount: conversion.amount,
        to_currency: conversion.to_currency,
        exchange_rate: conversion.rate
    };

    conversionHistoryData.unshift(historyItem);
    conversionHistoryData = conversionHistoryData.slice(0, 10); // Keep last 10

    localStorage.setItem('conversionHistory', JSON.stringify(conversionHistoryData));
    updateHistoryDisplay();
}

// Update history display
function updateHistoryDisplay() {
    const historyContainer = document.getElementById('conversionHistory');
    
    if (conversionHistoryData.length === 0) {
        historyContainer.innerHTML = '<p class="text-muted text-center"><?= __('exchange_rates.no_conversions') ?></p>';
        return;
    }

    let html = '<div class="list-group">';
    
    conversionHistoryData.forEach((item, index) => {
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="fw-bold">${parseFloat(item.original_amount).toFixed(2)} ${item.from_currency}</span>
                        <i class="bi bi-arrow-right mx-2"></i>
                        <span class="fw-bold text-success">${parseFloat(item.converted_amount).toFixed(2)} ${item.to_currency}</span>
                    </div>
                    <small class="text-muted">${new Date(item.timestamp).toLocaleTimeString()}</small>
                </div>
                <small class="text-muted"><?= __('exchange_rates.rate') ?>: ${parseFloat(item.exchange_rate).toFixed(4)}</small>
            </div>
        `;
    });
    
    html += '</div>';
    historyContainer.innerHTML = html;
}

// Clear conversion history
function clearHistory() {
    if (confirm('<?= __('messages.are_you_sure') ?>')) {
        conversionHistoryData = [];
        localStorage.removeItem('conversionHistory');
        updateHistoryDisplay();
    }
}

// Refresh rates
function refreshRates() {
    location.reload();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateHistoryDisplay();
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>