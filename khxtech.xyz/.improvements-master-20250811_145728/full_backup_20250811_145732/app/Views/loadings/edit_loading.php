<?php
// app/Views/loadings/edit_loading.php
include __DIR__ . '/../layouts/header.php';

// Get clients for dropdown
$db = \App\Core\Database::getInstance();
$stmt = $db->query("SELECT id, client_code, name, name_ar, balance_rmb, balance_usd FROM clients WHERE status = 'active' ORDER BY name");
$clients = $stmt->fetchAll();

// Get exchange rates
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'exchange_rate_%'");
$rates = $stmt->fetchAll();
$exchangeRates = [];
foreach ($rates as $rate) {
    $exchangeRates[$rate['setting_key']] = $rate['setting_value'];
}

// Updated office list
$offices = [
    'port_sudan' => __('offices.port_sudan'),
    'uae' => __('offices.uae'),
    'tanzania' => __('offices.tanzania'),
    'egypt' => __('offices.egypt'),
];

// Get sync status if this is a Port Sudan loading
$syncStatus = null;
$syncLog = [];
if ($loading['office'] === 'port_sudan') {
    $stmt = $db->query("SELECT * FROM api_sync_log WHERE china_loading_id = ? ORDER BY created_at DESC LIMIT 5", [$loading['id']]);
    $syncLog = $stmt->fetchAll();
    
    $lastSync = $syncLog[0] ?? null;
    if ($lastSync) {
        $syncStatus = $lastSync['response_code'] >= 200 && $lastSync['response_code'] < 300 ? 'success' : 'failed';
    }
}
?>

<div class="col-md-12 p-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="bi bi-pencil-square"></i> <?= __('loadings.edit_loading') ?>
                    </h4>
                    <div>
                        <?php if ($loading['office'] === 'port_sudan' && $syncStatus): ?>
                            <span class="badge bg-<?= $syncStatus === 'success' ? 'success' : 'danger' ?> me-2">
                                <?= $syncStatus === 'success' ? __('sync.sync_completed') : __('sync.sync_failed') ?>
                            </span>
                        <?php endif; ?>
                        <a href="/loadings" class="btn btn-sm btn-secondary">
                            <i class="bi bi-list"></i> <?= __('loadings.view_list') ?>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $_SESSION['error'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $_SESSION['success'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    
                    <form method="POST" action="/loadings/edit/<?= $loading['id'] ?>" id="loading-form">
                        <!-- Row 1: Basic Information -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="shipping_date" class="form-label">
                                    <?= __('loadings.shipping_date') ?> <span class="text-danger">*</span>
                                </label>
                                <input type="date" 
                                       class="form-control" 
                                       id="shipping_date" 
                                       name="shipping_date" 
                                       value="<?= $loading['shipping_date'] ?>" 
                                       required>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="loading_no" class="form-label">
                                    <?= __('loadings.loading_no') ?> <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="loading_no" 
                                       name="loading_no" 
                                       value="<?= $loading['loading_no'] ?>"
                                       required>
                                <small class="text-muted"><?= __('loadings.loading_no_hint') ?></small>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="claim_number" class="form-label">
                                    <?= __('loadings.claim_number') ?>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="claim_number" 
                                       name="claim_number" 
                                       value="<?= $loading['claim_number'] ?>"
                                       readonly
                                       disabled>
                                <small class="text-muted"><?= __('loadings.claim_auto_generate_hint') ?></small>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="container_no" class="form-label">
                                    <?= __('loadings.container_no') ?> <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control text-uppercase" 
                                       id="container_no" 
                                       name="container_no" 
                                       value="<?= $loading['container_no'] ?>"
                                       pattern="[A-Z]{4}[0-9]{7}"
                                       title="<?= __('loadings.container_format') ?>"
                                       required>
                                <small class="text-muted"><?= __('loadings.container_format_hint') ?></small>
                                <small class="text-info d-block"><?= __('loadings.container_repeat_allowed') ?></small>
                            </div>
                        </div>
                        
                        <!-- Row 2: Client Information -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="client_code" class="form-label">
                                    <?= __('loadings.client_code') ?> <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="client_code" 
                                       name="client_code" 
                                       value="<?= $loading['client_code'] ?>"
                                       list="client-codes"
                                       required>
                                <datalist id="client-codes">
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['client_code'] ?>"><?= $client['name'] ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div class="col-md-5">
                                <label for="client_name" class="form-label">
                                    <?= __('loadings.client_name') ?>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="client_name" 
                                       name="client_name" 
                                       value="<?= $loading['client_name'] ?>"
                                       readonly>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="item_description" class="form-label">
                                    <?= __('loadings.item_description') ?>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="item_description" 
                                       name="item_description" 
                                       value="<?= $loading['item_description'] ?>"
                                       placeholder="<?= __('loadings.describe_items') ?>">
                            </div>
                        </div>
                        
                        <!-- Row 3: Cargo Details -->
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label for="cartons_count" class="form-label">
                                    <?= __('loadings.cartons_count') ?> <span class="text-danger">*</span>
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="cartons_count" 
                                       name="cartons_count" 
                                       value="<?= $loading['cartons_count'] ?>"
                                       min="1"
                                       required>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="purchase_amount" class="form-label">
                                    <?= __('loadings.purchase') ?> (¥) <span class="text-danger">*</span>
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="purchase_amount" 
                                       name="purchase_amount" 
                                       value="<?= $loading['purchase_amount'] ?>"
                                       step="0.01"
                                       min="0"
                                       required>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="commission_amount" class="form-label">
                                    <?= __('loadings.commission') ?> (¥)
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="commission_amount" 
                                       name="commission_amount" 
                                       value="<?= $loading['commission_amount'] ?>"
                                       step="0.01"
                                       min="0">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="total_amount" class="form-label">
                                    <?= __('loadings.total') ?> (¥)
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="total_amount" 
                                       name="total_amount" 
                                       value="<?= $loading['total_amount'] ?>"
                                       step="0.01"
                                       readonly>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="shipping_usd" class="form-label">
                                    <?= __('loadings.shipping') ?> ($)
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="shipping_usd" 
                                       name="shipping_usd" 
                                       value="<?= $loading['shipping_usd'] ?>"
                                       step="0.01"
                                       min="0">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="total_with_shipping" class="form-label">
                                    <?= __('loadings.grand_total') ?> (¥)
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="total_with_shipping" 
                                       name="total_with_shipping" 
                                       value="<?= $loading['total_with_shipping'] ?>"
                                       step="0.01"
                                       readonly>
                            </div>
                        </div>
                        
                        <!-- Row 4: Office and Notes -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="office" class="form-label">
                                    <?= __('loadings.office') ?>
                                </label>
                                <select class="form-select" id="office" name="office">
                                    <option value=""><?= __('loadings.no_office') ?></option>
                                    <?php foreach ($offices as $key => $office): ?>
                                        <option value="<?= $key ?>" <?= $loading['office'] === $key ? 'selected' : '' ?>>
                                            <?= $office ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted"><?= __('loadings.office_notification_hint') ?></small>
                            </div>
                            
                            <div class="col-md-8">
                                <label for="notes" class="form-label">
                                    <?= __('notes') ?>
                                </label>
                                <textarea class="form-control" 
                                          id="notes" 
                                          name="notes" 
                                          rows="2"
                                          placeholder="<?= __('loadings.additional_notes') ?>"><?= $loading['notes'] ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Port Sudan Sync Status -->
                        <?php if ($loading['office'] === 'port_sudan'): ?>
                            <div class="alert alert-info" role="alert">
                                <h6 class="alert-heading">
                                    <i class="bi bi-arrow-left-right"></i> <?= __('sync.sync_status') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong><?= __('sync.last_sync') ?>:</strong> 
                                            <?php if ($lastSync): ?>
                                                <?= date('Y-m-d H:i:s', strtotime($lastSync['created_at'])) ?>
                                                <span class="badge bg-<?= $syncStatus === 'success' ? 'success' : 'danger' ?> ms-2">
                                                    <?= $syncStatus === 'success' ? __('sync.sync_completed') : __('sync.sync_failed') ?>
                                                </span>
                                            <?php else: ?>
                                                <?= __('sync.sync_pending') ?>
                                            <?php endif; ?>
                                        </p>
                                        <p class="mb-0">
                                            <strong><?= __('sync.sync_attempts') ?>:</strong> <?= count($syncLog) ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <?php if ($syncStatus === 'failed'): ?>
                                            <button type="button" class="btn btn-warning btn-sm" onclick="retrySync(<?= $loading['id'] ?>)">
                                                <i class="bi bi-arrow-clockwise"></i> <?= __('sync.retry_sync') ?>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-info btn-sm" onclick="showSyncLog()">
                                            <i class="bi bi-list-ul"></i> <?= __('sync.sync_log') ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- BOL Status Section -->
                        <?php if ($loading['office'] === 'port_sudan'): ?>
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="bi bi-file-earmark-text"></i> <?= __('bol.bill_of_lading') ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label for="bol_status" class="form-label"><?= __('bol.bol_status') ?></label>
                                            <select class="form-select" id="bol_status" name="bol_status">
                                                <option value="not_issued" <?= ($loading['bill_of_lading_status'] ?? 'not_issued') === 'not_issued' ? 'selected' : '' ?>>
                                                    <?= __('bol.bol_not_issued') ?>
                                                </option>
                                                <option value="issued" <?= ($loading['bill_of_lading_status'] ?? '') === 'issued' ? 'selected' : '' ?>>
                                                    <?= __('bol.bol_issued') ?>
                                                </option>
                                                <option value="delayed" <?= ($loading['bill_of_lading_status'] ?? '') === 'delayed' ? 'selected' : '' ?>>
                                                    <?= __('bol.bol_delayed') ?>
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="bol_date" class="form-label"><?= __('bol.bol_date') ?></label>
                                            <input type="date" 
                                                   class="form-control" 
                                                   id="bol_date" 
                                                   name="bol_date" 
                                                   value="<?= $loading['bill_of_lading_date'] ?? '' ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="bol_file" class="form-label"><?= __('bol.bol_file') ?></label>
                                            <input type="file" 
                                                   class="form-control" 
                                                   id="bol_file" 
                                                   name="bol_file" 
                                                   accept=".pdf,.jpg,.jpeg,.png">
                                            <?php if (!empty($loading['bill_of_lading_file'])): ?>
                                                <small class="text-muted">
                                                    <?= __('loadings.current_file') ?>: <a href="/<?= $loading['bill_of_lading_file'] ?>" target="_blank"><?= __('loadings.view_file') ?></a>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Submit Button -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="/loadings" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> <?= __('back') ?>
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> <?= __('loadings.update_loading') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sync Log Modal -->
<div class="modal fade" id="syncLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('sync.sync_log') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th><?= __('date') ?></th>
                                <th><?= __('actions.action') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('response') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($syncLog as $log): ?>
                                <tr>
                                    <td><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?></td>
                                    <td><?= $log['endpoint'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $log['response_code'] >= 200 && $log['response_code'] < 300 ? 'success' : 'danger' ?>">
                                            <?= $log['response_code'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= substr($log['response_data'], 0, 100) ?>...</small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const clientCodeInput = document.getElementById('client_code');
    const clientNameInput = document.getElementById('client_name');
    const purchaseInput = document.getElementById('purchase_amount');
    const commissionInput = document.getElementById('commission_amount');
    const totalInput = document.getElementById('total_amount');
    const shippingInput = document.getElementById('shipping_usd');
    const grandTotalInput = document.getElementById('total_with_shipping');
    const officeSelect = document.getElementById('office');
    
    // Client data for auto-filling
    const clientsData = <?= json_encode(array_column($clients, null, 'client_code')) ?>;
    
    // Exchange rates
    const exchangeRates = <?= json_encode($exchangeRates) ?>;
    const usdToRmbRate = parseFloat(exchangeRates['exchange_rate_usd_rmb'] || '7.20');
    
    // Auto-fill client name when client code is selected
    clientCodeInput.addEventListener('input', function() {
        const clientCode = this.value.trim();
        if (clientsData[clientCode]) {
            clientNameInput.value = clientsData[clientCode].name;
        } else {
            clientNameInput.value = '';
        }
    });
    
    // Calculate totals automatically
    function calculateTotals() {
        const purchase = parseFloat(purchaseInput.value) || 0;
        const commission = parseFloat(commissionInput.value) || 0;
        const shipping = parseFloat(shippingInput.value) || 0;
        
        const total = purchase + commission;
        const shippingRmb = shipping * usdToRmbRate;
        const grandTotal = total + shippingRmb;
        
        totalInput.value = total.toFixed(2);
        grandTotalInput.value = grandTotal.toFixed(2);
    }
    
    // Add event listeners for calculation
    [purchaseInput, commissionInput, shippingInput].forEach(input => {
        input.addEventListener('input', calculateTotals);
    });
    
    // Container number formatting
    const containerInput = document.getElementById('container_no');
    containerInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    
    // Form validation
    document.getElementById('loading-form').addEventListener('submit', function(e) {
        const loadingNumber = document.getElementById('loading_no').value.trim();
        const clientCode = clientCodeInput.value.trim();
        const containerNo = containerInput.value.trim();
        
        if (!loadingNumber) {
            e.preventDefault();
            alert('<?= __('loadings.loading_no_required') ?>');
            return;
        }
        
        if (!clientCode || !clientsData[clientCode]) {
            e.preventDefault();
            alert('<?= __('loadings.valid_client_required') ?>');
            return;
        }
        
        if (!containerNo.match(/^[A-Z]{4}[0-9]{7}$/)) {
            e.preventDefault();
            alert('<?= __('loadings.container_format_invalid') ?>');
            return;
        }
    });
    
    // BOL status change handler
    const bolStatusSelect = document.getElementById('bol_status');
    const bolDateInput = document.getElementById('bol_date');
    const bolFileInput = document.getElementById('bol_file');
    
    if (bolStatusSelect) {
        bolStatusSelect.addEventListener('change', function() {
            if (this.value === 'issued' && !bolDateInput.value) {
                bolDateInput.value = new Date().toISOString().split('T')[0];
            }
        });
    }
});

// Show sync log modal
function showSyncLog() {
    const modal = new bootstrap.Modal(document.getElementById('syncLogModal'));
    modal.show();
}

// Retry sync function
function retrySync(loadingId) {
    if (confirm('<?= __('sync.retry_sync') ?>?')) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> <?= __('processing') ?>...';
        btn.disabled = true;
        
        fetch(`/api/sync/retry/${loadingId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('<?= __('sync.sync_error') ?>: ' + data.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            alert('<?= __('sync.sync_error') ?>: ' + error.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
}
</script>

<style>
.alert-info {
    border-left: 4px solid #17a2b8;
}

.form-control:read-only {
    background-color: #f8f9fa;
}

.text-info {
    font-size: 0.875em;
}

.badge {
    font-size: 0.75em;
}

.table-responsive {
    max-height: 400px;
    overflow-y: auto;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.modal-lg {
    max-width: 800px;
}

/* Sync status indicators */
.sync-status-success {
    color: #198754;
}

.sync-status-failed {
    color: #dc3545;
}

.sync-status-pending {
    color: #ffc107;
}

/* Loading animation for sync retry */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.spinning {
    animation: spin 1s linear infinite;
}
</style>