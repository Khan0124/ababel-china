<?php
// app/Views/loadings/add_loading.php
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

// Updated office list - removed unsupported offices for now
$offices = [
    'port_sudan' => __('offices.port_sudan'),
    'uae' => __('offices.uae'),
    'tanzania' => __('offices.tanzania'), 
    'egypt' => __('offices.egypt'),
];
?>

<div class="col-md-12 p-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="bi bi-box-seam"></i> <?= __('loadings.add_new') ?>
                    </h4>
                    <a href="/loadings" class="btn btn-sm btn-secondary">
                        <i class="bi bi-list"></i> <?= __('loadings.view_list') ?>
                    </a>
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
                    
                    <form method="POST" action="/loadings/create" id="loading-form">
                        
                        <!-- Basic Information Card -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <?= __('loadings.basic_information') ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="shipping_date" class="form-label">
                                            <?= __('loadings.shipping_date') ?> <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" 
                                               class="form-control" 
                                               id="shipping_date" 
                                               name="shipping_date" 
                                               value="<?= date('Y-m-d') ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="loading_no" class="form-label">
                                            <?= __('loadings.loading_number') ?> <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="loading_no" 
                                               name="loading_no" 
                                               placeholder="<?= __('loadings.enter_loading_number') ?>"
                                               required>
                                        <small class="text-muted"><?= __('loadings.loading_number_hint') ?></small>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="claim_number" class="form-label">
                                            <?= __('loadings.claim_number') ?>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="claim_number" 
                                               name="claim_number" 
                                               placeholder="<?= __('loadings.auto_generated') ?>"
                                               readonly
                                               disabled>
                                        <small class="text-muted"><?= __('loadings.will_be_generated_automatically') ?></small>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <label for="container_no" class="form-label">
                                            <?= __('loadings.container_no') ?> <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control text-uppercase" 
                                               id="container_no" 
                                               name="container_no" 
                                               placeholder="CMAU7702683"
                                               pattern="[A-Z]{4}[0-9]{7}"
                                               title="<?= __('loadings.container_format') ?>"
                                               required>
                                        <small class="text-muted"><?= __('loadings.container_format_hint') ?></small>
                                        <small class="text-info d-block"><?= __('loadings.container_can_be_repeated') ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Client & Cargo Details Card -->
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-person-circle me-2"></i>
                                    <?= __('loadings.client_cargo_details') ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                <label for="client_code" class="form-label">
                                    <?= __('loadings.client_code') ?> <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="client_code" 
                                       name="client_code" 
                                       placeholder="<?= __('loadings.enter_client_code') ?>"
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
                                       placeholder="<?= __('loadings.auto_filled_from_client_code') ?>"
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
                                       placeholder="<?= __('loadings.describe_items_being_shipped') ?>">
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
                                       step="0.01"
                                       min="0"
                                       value="0">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="total_amount" class="form-label">
                                    <?= __('loadings.total') ?> (¥)
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="total_amount" 
                                       name="total_amount" 
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
                                       step="0.01"
                                       min="0"
                                       value="0">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="total_with_shipping" class="form-label">
                                    <?= __('loadings.grand_total') ?> (¥)
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="total_with_shipping" 
                                       name="total_with_shipping" 
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
                                    <option value=""><?= __('loadings.no_office_selected') ?></option>
                                    <?php foreach ($offices as $key => $office): ?>
                                        <option value="<?= $key ?>"><?= $office ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted"><?= __('loadings.notification_will_be_sent') ?></small>
                            </div>
                            
                            <div class="col-md-8">
                                <label for="notes" class="form-label">
                                    <?= __('notes') ?>
                                </label>
                                <textarea class="form-control" 
                                          id="notes" 
                                          name="notes" 
                                          rows="2"
                                          placeholder="<?= __('loadings.additional_notes_placeholder') ?>"></textarea>
                            </div>
                        </div>
                        
                        <!-- Financial Summary Alert -->
                        <div class="alert alert-info" role="alert">
                            <h6 class="alert-heading">
                                <i class="bi bi-info-circle"></i> <?= __('loadings.financial_details') ?>
                            </h6>
                            <p class="mb-0"><?= __('loadings.financial_details_message') ?></p>
                        </div>
                        
                        <!-- Port Sudan Sync Alert -->
                        <div class="alert alert-warning" role="alert" id="port-sudan-alert" style="display: none;">
                            <h6 class="alert-heading">
                                <i class="bi bi-arrow-left-right"></i> <?= __('loadings.port_sudan_synchronization') ?>
                            </h6>
                            <p class="mb-0"><?= __('loadings.port_sudan_sync_message') ?></p>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="/loadings" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> <?= __('back') ?>
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> <?= __('loadings.create_loading') ?>
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
    const portSudanAlert = document.getElementById('port-sudan-alert');
    
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
    
    // Show/hide Port Sudan sync alert
    officeSelect.addEventListener('change', function() {
        if (this.value === 'port_sudan') {
            portSudanAlert.style.display = 'block';
        } else {
            portSudanAlert.style.display = 'none';
        }
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
            alert('<?= __('loadings.loading_number_required') ?>');
            return;
        }
        
        if (!clientCode || !clientsData[clientCode]) {
            e.preventDefault();
            alert('<?= __('loadings.select_valid_client') ?>');
            return;
        }
        
        if (!containerNo.match(/^[A-Z]{4}[0-9]{7}$/)) {
            e.preventDefault();
            alert('<?= __('loadings.container_format_invalid') ?>');
            return;
        }
    });
});
</script>

<style>
.alert-info {
    border-left: 4px solid #17a2b8;
}

.alert-warning {
    border-left: 4px solid #ffc107;
}

#port-sudan-alert {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.form-control:read-only {
    background-color: #f8f9fa;
}

.text-info {
    font-size: 0.875em;
}
</style>