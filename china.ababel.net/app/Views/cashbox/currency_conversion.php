<?php
// app/Views/cashbox/currency_conversion.php
include __DIR__ . '/../layouts/header.php'; 
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-cash-stack"></i> <?= __('cashbox_currency.title') ?></h1>
        <div class="btn-group">
            <a href="/cashbox" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> <?= __('cashbox_currency.back_to_cashbox') ?>
            </a>
            <button type="button" class="btn btn-info" onclick="refreshBalances()">
                <i class="bi bi-arrow-clockwise"></i> <?= __('cashbox_currency.refresh_balances') ?>
            </button>
        </div>
    </div>

    <!-- Current Balances Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 id="balance-RMB">¥<?= number_format($cashbox_balances['RMB'] ?? 0, 2) ?></h3>
                    <p class="mb-0"><?= __('cashbox_currency.chinese_yuan') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 id="balance-USD">$<?= number_format($cashbox_balances['USD'] ?? 0, 2) ?></h3>
                    <p class="mb-0"><?= __('cashbox_currency.us_dollar') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3 id="balance-SDG">SDG <?= number_format($cashbox_balances['SDG'] ?? 0, 2) ?></h3>
                    <p class="mb-0"><?= __('cashbox_currency.sudanese_pound') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h3 id="balance-AED">AED <?= number_format($cashbox_balances['AED'] ?? 0, 2) ?></h3>
                    <p class="mb-0"><?= __('cashbox_currency.uae_dirham') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Currency Conversion Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> <?= __('cashbox_currency.currency_exchange') ?></h5>
                </div>
                <div class="card-body">
                    <form id="conversionForm">
                        <?= csrf_field() ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0 text-danger"><?= __('cashbox_currency.from_deduct') ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label"><?= __('cashbox_currency.source_currency') ?></label>
                                            <select class="form-select" id="fromCurrency" name="from_currency" required onchange="updatePreview()">
                                                <option value=""><?= __('cashbox_currency.select_currency') ?></option>
                                                <?php foreach ($supported_currencies as $currency): ?>
                                                    <option value="<?= $currency ?>" data-balance="<?= $cashbox_balances[$currency] ?? 0 ?>">
                                                        <?= $currency ?> (<?= __('cashbox_currency.available_balance') ?>: <?= number_format($cashbox_balances[$currency] ?? 0, 2) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label"><?= __('cashbox_currency.amount_to_convert') ?></label>
                                            <input type="number" 
                                                   class="form-control form-control-lg" 
                                                   id="amount" 
                                                   name="amount" 
                                                   step="0.01" 
                                                   min="0.01" 
                                                   placeholder="0.00"
                                                   required 
                                                   onchange="updatePreview()">
                                            <div class="form-text" id="availableBalance"><?= __('cashbox_currency.select_currency') ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0 text-success"><?= __('cashbox_currency.to_receive') ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label"><?= __('cashbox_currency.target_currency') ?></label>
                                            <select class="form-select" id="toCurrency" name="to_currency" required onchange="updatePreview()">
                                                <option value=""><?= __('cashbox_currency.select_currency') ?></option>
                                                <?php foreach ($supported_currencies as $currency): ?>
                                                    <option value="<?= $currency ?>">
                                                        <?= $currency ?> (<?= __('current') ?>: <?= number_format($cashbox_balances[$currency] ?? 0, 2) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label"><?= __('cashbox_currency.amount_to_receive') ?></label>
                                            <input type="text" 
                                                   class="form-control form-control-lg bg-light" 
                                                   id="convertedAmount" 
                                                   placeholder="0.00"
                                                   readonly>
                                            <div class="form-text">
                                                <?= __('cashbox_currency.conversion_rate') ?>: <span id="exchangeRate">-</span>
                                                <span id="rateTimestamp" class="text-muted"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label"><?= __('cashbox_currency.description_optional') ?></label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="description" 
                                           placeholder="<?= __('cashbox_currency.description_placeholder') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Conversion Preview -->
                        <div id="conversionPreview" class="alert alert-info" style="display: none;">
                            <h6><i class="bi bi-eye"></i> <?= __('cashbox_currency.conversion_preview') ?></h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong><?= __('cashbox_currency.current_balances') ?>:</strong>
                                    <div id="currentBalances"></div>
                                </div>
                                <div class="col-md-6">
                                    <strong><?= __('cashbox_currency.after_conversion') ?>:</strong>
                                    <div id="newBalances"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="button" class="btn btn-outline-secondary me-md-2" onclick="clearForm()">
                                <i class="bi bi-x-circle"></i> <?= __('cashbox_currency.clear_form') ?>
                            </button>
                            <button type="button" class="btn btn-success" onclick="executeConversion()">
                                <i class="bi bi-arrow-left-right"></i> <?= __('cashbox_currency.execute_exchange') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recent Conversions -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> <?= __('cashbox_currency.recent_exchanges') ?></h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadConversionHistory()">
                        <i class="bi bi-arrow-clockwise"></i> <?= __('refresh') ?>
                    </button>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <div id="conversionHistory">
                        <?php if (empty($conversion_history)): ?>
                            <p class="text-muted text-center"><?= __('cashbox_currency.no_recent_conversions') ?></p>
                        <?php else: ?>
                            <?php foreach ($conversion_history as $conversion): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="fw-bold text-danger">-<?= number_format($conversion['original_amount'], 2) ?> <?= $conversion['from_currency'] ?></span>
                                        <br>
                                        <span class="fw-bold text-success">+<?= number_format($conversion['converted_amount'], 2) ?> <?= $conversion['to_currency'] ?></span>
                                    </div>
                                    <small class="text-muted"><?= date('M j, H:i', strtotime($conversion['converted_at'])) ?></small>
                                </div>
                                <small class="text-muted">
                                    <?= __('cashbox_currency.conversion_rate') ?>: <?= number_format($conversion['exchange_rate'], 4) ?>
                                    <?php if (!empty($conversion['description'])): ?>
                                        <br><?= h($conversion['description']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Conversion Summary -->
            <?php if (!empty($conversion_summary)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> <?= __('cashbox_currency.weekly_summary') ?></h5>
                </div>
                <div class="card-body">
                    <?php foreach (array_slice($conversion_summary, 0, 5) as $summary): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="fw-semibold"><?= $summary['from_currency'] ?> → <?= $summary['to_currency'] ?></span>
                            <br><small class="text-muted"><?= $summary['conversion_count'] ?> <?= __('cashbox_currency.conversions') ?></small>
                        </div>
                        <div class="text-end">
                            <small class="text-primary"><?= __('cashbox_currency.avg_rate') ?>: <?= number_format($summary['avg_rate'], 4) ?></small>
                        </div>
                    </div>
                    <hr class="my-1">
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('cashbox_currency.confirm_exchange') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <strong><?= __('cashbox_currency.exchange_confirmation') ?></strong>
                </div>
                
                <div id="confirmationDetails"></div>
                
                <p class="mt-3"><strong><?= __('cashbox_currency.confirm_proceed') ?></strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                <button type="button" class="btn btn-success" onclick="confirmConversion()">
                    <i class="bi bi-check-circle"></i> <?= __('confirm') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let conversionPreviewData = null;

// Update conversion preview
function updatePreview() {
    const fromCurrency = document.getElementById('fromCurrency').value;
    const toCurrency = document.getElementById('toCurrency').value;
    const amount = parseFloat(document.getElementById('amount').value);
    
    // Update available balance display
    if (fromCurrency) {
        const option = document.querySelector(`option[value="${fromCurrency}"]`);
        const balance = option ? option.dataset.balance : 0;
        document.getElementById('availableBalance').textContent = `<?= __('cashbox_currency.available_balance') ?>: ${parseFloat(balance).toFixed(2)} ${fromCurrency}`;
    }
    
    if (!fromCurrency || !toCurrency || !amount || amount <= 0) {
        document.getElementById('conversionPreview').style.display = 'none';
        document.getElementById('convertedAmount').value = '';
        document.getElementById('exchangeRate').textContent = '-';
        return;
    }
    
    // Get conversion preview
    fetch(`/cashbox/currency-conversion/preview?from=${fromCurrency}&to=${toCurrency}&amount=${amount}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const preview = data.preview;
            conversionPreviewData = preview;
            
            // Update converted amount and rate
            document.getElementById('convertedAmount').value = parseFloat(preview.converted_amount).toFixed(2);
            document.getElementById('exchangeRate').textContent = `1 ${fromCurrency} = ${parseFloat(preview.exchange_rate).toFixed(4)} ${toCurrency}`;
            document.getElementById('rateTimestamp').textContent = `(<?= __('last_updated') ?>: ${new Date(preview.rate_last_updated).toLocaleTimeString()})`;
            
            // Show preview
            document.getElementById('currentBalances').innerHTML = Object.entries(preview.current_balances)
                .map(([curr, bal]) => `<div>${curr}: ${parseFloat(bal).toFixed(2)}</div>`).join('');
            document.getElementById('newBalances').innerHTML = Object.entries(preview.new_balances)
                .map(([curr, bal]) => `<div>${curr}: ${parseFloat(bal).toFixed(2)}</div>`).join('');
            
            document.getElementById('conversionPreview').style.display = 'block';
        } else {
            alert(data.errors ? data.errors.join(', ') : '<?= __('cashbox_currency.exchange_failed') ?>');
            document.getElementById('conversionPreview').style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Preview error:', error);
        document.getElementById('conversionPreview').style.display = 'none';
    });
}

// Execute conversion
function executeConversion() {
    if (!conversionPreviewData) {
        alert('<?= __('cashbox_currency.provide_valid_details') ?>');
        return;
    }
    
    // Show confirmation modal
    const details = `
        <div class="row">
            <div class="col-6">
                <strong><?= __('from') ?>:</strong><br>
                ${conversionPreviewData.original_amount} ${conversionPreviewData.from_currency}
            </div>
            <div class="col-6">
                <strong><?= __('to') ?>:</strong><br>
                ${parseFloat(conversionPreviewData.converted_amount).toFixed(2)} ${conversionPreviewData.to_currency}
            </div>
        </div>
        <hr>
        <div><strong><?= __('cashbox_currency.conversion_rate') ?>:</strong> ${parseFloat(conversionPreviewData.exchange_rate).toFixed(4)}</div>
    `;
    
    document.getElementById('confirmationDetails').innerHTML = details;
    new bootstrap.Modal(document.getElementById('confirmationModal')).show();
}

// Confirm and execute conversion
function confirmConversion() {
    const formData = new FormData(document.getElementById('conversionForm'));
    
    fetch('/cashbox/currency-conversion/execute', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('confirmationModal')).hide();
        
        if (data.success) {
            alert('<?= __('cashbox_currency.exchange_completed') ?>');
            
            // Update balances
            updateDisplayedBalances(data.updated_balances);
            
            // Clear form
            clearForm();
            
            // Refresh history
            loadConversionHistory();
        } else {
            alert(data.error || '<?= __('cashbox_currency.exchange_failed') ?>');
        }
    })
    .catch(error => {
        bootstrap.Modal.getInstance(document.getElementById('confirmationModal')).hide();
        alert('<?= __('error.general') ?>');
    });
}

// Update displayed balances
function updateDisplayedBalances(balances) {
    Object.entries(balances).forEach(([currency, balance]) => {
        const element = document.getElementById(`balance-${currency}`);
        if (element) {
            const symbol = currency === 'RMB' ? '¥' : (currency === 'USD' ? '$' : currency + ' ');
            element.textContent = symbol + parseFloat(balance).toFixed(2);
        }
    });
}

// Load conversion history
function loadConversionHistory() {
    fetch('/cashbox/currency-conversion/history?limit=10')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const historyHtml = data.data.map(conversion => `
                <div class="border-bottom pb-2 mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="fw-bold text-danger">-${parseFloat(conversion.original_amount).toFixed(2)} ${conversion.from_currency}</span>
                            <br>
                            <span class="fw-bold text-success">+${parseFloat(conversion.converted_amount).toFixed(2)} ${conversion.to_currency}</span>
                        </div>
                        <small class="text-muted">${new Date(conversion.converted_at).toLocaleDateString()}</small>
                    </div>
                    <small class="text-muted">
                        <?= __('cashbox_currency.conversion_rate') ?>: ${parseFloat(conversion.exchange_rate).toFixed(4)}
                        ${conversion.description ? '<br>' + conversion.description : ''}
                    </small>
                </div>
            `).join('');
            
            document.getElementById('conversionHistory').innerHTML = historyHtml || '<p class="text-muted text-center"><?= __('cashbox_currency.no_recent_conversions') ?></p>';
        }
    })
    .catch(error => console.error('History loading error:', error));
}

// Clear form
function clearForm() {
    document.getElementById('conversionForm').reset();
    document.getElementById('conversionPreview').style.display = 'none';
    document.getElementById('convertedAmount').value = '';
    document.getElementById('exchangeRate').textContent = '-';
    document.getElementById('rateTimestamp').textContent = '';
    document.getElementById('availableBalance').textContent = '<?= __('cashbox_currency.select_currency') ?>';
    conversionPreviewData = null;
}

// Refresh balances
function refreshBalances() {
    fetch('/cashbox/currency-conversion/balances')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateDisplayedBalances(data.data);
            alert('<?= __('cashbox_currency.balances_refreshed') ?>');
        }
    })
    .catch(error => console.error('Balance refresh error:', error));
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>