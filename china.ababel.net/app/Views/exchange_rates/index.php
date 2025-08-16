<?php
// app/Views/exchange_rates/index.php
include __DIR__ . '/../layouts/header.php'; 
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-currency-exchange"></i> <?= __('exchange_rates.title') ?></h1>
        <div class="btn-group">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRateModal">
                <i class="bi bi-plus-circle"></i> <?= __('exchange_rates.add_rate') ?>
            </button>
            <a href="/exchange-rates/calculator" class="btn btn-info">
                <i class="bi bi-calculator"></i> <?= __('exchange_rates.calculator') ?>
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5><?= count($current_rates ?? []) ?></h5>
                    <p class="mb-0"><?= __('exchange_rates.active_pairs') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5><?= count($conversion_summary ?? []) ?></h5>
                    <p class="mb-0"><?= __('exchange_rates.recent_conversions') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5><?= count($supported_currencies ?? []) ?></h5>
                    <p class="mb-0"><?= __('exchange_rates.supported_currencies') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h5 id="staleRatesCount">0</h5>
                    <p class="mb-0"><?= __('exchange_rates.stale_rates') ?> (>24h)</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Current Exchange Rates -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> <?= __('exchange_rates.current_rates') ?></h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="refreshRates()">
                        <i class="bi bi-arrow-clockwise"></i> <?= __('exchange_rates.refresh_rates') ?>
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($current_rates)): ?>
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle"></i> <?= __('exchange_rates.no_rates_available') ?>
                            <br>
                            <button type="button" class="btn btn-primary mt-2" onclick="initializeDefaults()">
                                <?= __('exchange_rates.initialize_defaults') ?>
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="exchangeRatesTable">
                                <thead>
                                    <tr>
                                        <th><?= __('exchange_rates.currency_pair') ?></th>
                                        <th><?= __('exchange_rates.rate') ?></th>
                                        <th><?= __('exchange_rates.last_updated') ?></th>
                                        <th><?= __('exchange_rates.source') ?></th>
                                        <th><?= __('exchange_rates.status') ?></th>
                                        <th><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($current_rates as $pair => $rate): ?>
                                    <tr data-pair="<?= $pair ?>">
                                        <td>
                                            <strong><?= str_replace('_', ' → ', $pair) ?></strong>
                                            <br><small class="text-muted"><?= $rate['from_currency'] ?> <?= __('to') ?> <?= $rate['to_currency'] ?></small>
                                        </td>
                                        <td>
                                            <span class="rate-value h5 mb-0"><?= number_format($rate['rate'], 4) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-muted"><?= date('M j, H:i', strtotime($rate['last_updated'])) ?></span>
                                            <br><small class="text-info"><?= $rate['minutes_ago'] ?> <?= __('exchange_rates.minutes_ago') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $rate['source'] == 'manual' ? 'warning' : 'success' ?>">
                                                <?= __('exchange_rates.' . $rate['source']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $rate['status'] == 'stale' ? 'danger' : 'success' ?>">
                                                <?= __('exchange_rates.' . $rate['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editRate('<?= $pair ?>', <?= $rate['rate'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-info" onclick="viewHistory('<?= $pair ?>')">
                                                    <i class="bi bi-clock-history"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Info -->
        <div class="col-lg-4">
            <?php if (!empty($volatility_analysis)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-activity"></i> <?= __('exchange_rates.volatility_analysis') ?></h5>
                </div>
                <div class="card-body">
                    <?php foreach ($volatility_analysis as $pair => $analysis): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold"><?= str_replace('_', '→', $pair) ?></span>
                            <span class="badge bg-<?= $analysis['status'] == 'high' ? 'danger' : ($analysis['status'] == 'medium' ? 'warning' : 'success') ?>">
                                <?= $analysis['volatility'] ?>%
                            </span>
                        </div>
                        <div class="progress mt-1" style="height: 4px;">
                            <div class="progress-bar bg-<?= $analysis['status'] == 'high' ? 'danger' : ($analysis['status'] == 'medium' ? 'warning' : 'success') ?>" 
                                 style="width: <?= min($analysis['volatility'] * 10, 100) ?>%"></div>
                        </div>
                        <small class="text-muted"><?= $analysis['data_points'] ?> <?= __('exchange_rates.data_points') ?> (30 <?= __('exchange_rates.days') ?>)</small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Supported Currencies -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> <?= __('exchange_rates.supported_currencies') ?></h5>
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

<!-- Add Rate Modal -->
<div class="modal fade" id="addRateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('exchange_rates.add_rate') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addRateForm">
                    <input type="hidden" name="_csrf_token" value="<?= \App\Core\Security\CSRF::generate() ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?= __('exchange_rates.from_currency') ?></label>
                                <select class="form-select" name="from_currency" id="addFromCurrency" required>
                                    <option value="">Select...</option>
                                    <option value="USD">USD</option>
                                    <option value="RMB">RMB</option>
                                    <option value="SDG">SDG</option>
                                    <option value="AED">AED</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?= __('exchange_rates.to_currency') ?></label>
                                <select class="form-select" name="to_currency" id="addToCurrency" required>
                                    <option value="">Select...</option>
                                    <option value="USD">USD</option>
                                    <option value="RMB">RMB</option>
                                    <option value="SDG">SDG</option>
                                    <option value="AED">AED</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?= __('exchange_rates.exchange_rate') ?></label>
                        <input type="number" class="form-control" name="rate" step="0.000001" required placeholder="e.g., 7.24">
                        <small class="text-muted">1 <span id="fromCurrencyDisplay">FROM</span> = ? <span id="toCurrencyDisplay">TO</span></small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                <button type="button" class="btn btn-success" onclick="addNewRate()"><?= __('exchange_rates.add_rate') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Rate Modal -->
<div class="modal fade" id="editRateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('exchange_rates.update_rate') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editRateForm">
                    <input type="hidden" name="_csrf_token" value="<?= \App\Core\Security\CSRF::generate() ?>">
                    <input type="hidden" id="editCurrencyPair" name="currency_pair">
                    
                    <div class="mb-3">
                        <label class="form-label"><?= __('exchange_rates.currency_pair') ?></label>
                        <input type="text" class="form-control" id="editPairDisplay" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?= __('exchange_rates.exchange_rate') ?></label>
                        <input type="number" class="form-control" id="editRate" name="rate" step="0.000001" required>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted"><?= __('exchange_rates.manual_update_note') ?></small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                <button type="button" class="btn btn-primary" onclick="saveRate()"><?= __('exchange_rates.update_rate') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize default exchange rates
function initializeDefaults() {
    if (!confirm('<?= __('exchange_rates.confirm_initialize') ?>')) return;
    
    showLoading('<?= __('exchange_rates.initializing') ?>');
    
    const formData = new FormData();
    formData.append('_csrf_token', '<?= \App\Core\Security\CSRF::generate() ?>');
    
    fetch('/exchange-rates/initialize-defaults', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('success', '<?= __('exchange_rates.defaults_initialized') ?>');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('danger', data.error || '<?= __('exchange_rates.initialization_failed') ?>');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('danger', '<?= __('error.general') ?>');
    });
}

// Edit exchange rate
function editRate(pair, currentRate) {
    document.getElementById('editCurrencyPair').value = pair;
    document.getElementById('editPairDisplay').value = pair.replace('_', ' → ');
    document.getElementById('editRate').value = currentRate;
    
    new bootstrap.Modal(document.getElementById('editRateModal')).show();
}

// Save updated rate
function saveRate() {
    const formData = new FormData(document.getElementById('editRateForm'));
    
    showLoading('<?= __('exchange_rates.updating_rate') ?>');
    
    fetch('/exchange-rates/update-rate', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('success', '<?= __('exchange_rates.rate_updated') ?>');
            bootstrap.Modal.getInstance(document.getElementById('editRateModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('danger', data.error || '<?= __('exchange_rates.update_failed') ?>');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('danger', '<?= __('error.general') ?>');
    });
}

// Refresh rates display
function refreshRates() {
    location.reload();
}

// Add new exchange rate
function addNewRate() {
    const form = document.getElementById('addRateForm');
    const formData = new FormData(form);
    
    const fromCurrency = formData.get('from_currency');
    const toCurrency = formData.get('to_currency');
    
    if (fromCurrency === toCurrency) {
        showAlert('warning', 'Cannot set rate for same currency');
        return;
    }
    
    // Create currency pair
    formData.append('currency_pair', `${fromCurrency}_${toCurrency}`);
    
    showLoading('<?= __('exchange_rates.adding_rate') ?>');
    
    fetch('/exchange-rates/update-rate', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('success', '<?= __('exchange_rates.rate_added') ?>');
            bootstrap.Modal.getInstance(document.getElementById('addRateModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('danger', data.error || '<?= __('exchange_rates.add_failed') ?>');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('danger', '<?= __('error.general') ?>');
    });
}

// Update currency display in add form
document.getElementById('addFromCurrency')?.addEventListener('change', function() {
    document.getElementById('fromCurrencyDisplay').textContent = this.value || 'FROM';
});

document.getElementById('addToCurrency')?.addEventListener('change', function() {
    document.getElementById('toCurrencyDisplay').textContent = this.value || 'TO';
});

// View rate history
function viewHistory(pair) {
    showLoading('<?= __('exchange_rates.loading_history') ?>');
    
    fetch(`/exchange-rates/history?pair=${pair}&days=30`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success && data.data.history) {
                // Display history in a simple alert for now
                let historyText = `History for ${pair}:\n\n`;
                data.data.history.slice(0, 10).forEach(item => {
                    historyText += `${item.date}: ${item.rate} (${item.source})\n`;
                });
                alert(historyText);
            } else {
                showAlert('warning', '<?= __('exchange_rates.no_history_available') ?>');
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('danger', '<?= __('error.general') ?>');
        });
}

// Count stale rates
document.addEventListener('DOMContentLoaded', function() {
    const staleCount = document.querySelectorAll('.badge.bg-danger').length;
    document.getElementById('staleRatesCount').textContent = staleCount;
});

// Utility functions
function showLoading(message) {
    // Create loading overlay
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;justify-content:center;align-items:center;z-index:9999';
    overlay.innerHTML = `
        <div class="bg-white p-4 rounded">
            <div class="spinner-border text-primary me-2" role="status"></div>
            <span>${message || 'Loading...'}</span>
        </div>
    `;
    document.body.appendChild(overlay);
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.remove();
}

function showAlert(type, message) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>