<?php
// app/Views/cashbox/movement.php
include __DIR__ . '/../layouts/header.php';
?>

<div class="col-md-12 p-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><?= __('cashbox.movement') ?></h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="/cashbox/movement" id="movement-form">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="movement_date" class="form-label"><?= __('date') ?> *</label>
                                <input type="date" 
                                       class="form-control" 
                                       id="movement_date" 
                                       name="movement_date" 
                                       value="<?= $data['movement_date'] ?? date('Y-m-d') ?>" 
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="movement_type" class="form-label"><?= __('cashbox.movement_type') ?> *</label>
                                <select class="form-select" id="movement_type" name="movement_type" required>
                                    <option value=""><?= __('search') ?>...</option>
                                    <option value="in" <?= ($data['movement_type'] ?? '') == 'in' ? 'selected' : '' ?>>
                                        <?= __('cashbox.in') ?>
                                    </option>
                                    <option value="out" <?= ($data['movement_type'] ?? '') == 'out' ? 'selected' : '' ?>>
                                        <?= __('cashbox.out') ?>
                                    </option>
                                    <option value="transfer" <?= ($data['movement_type'] ?? '') == 'transfer' ? 'selected' : '' ?>>
                                        <?= __('cashbox.transfer') ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label"><?= __('cashbox.category') ?> *</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value=""><?= __('search') ?>...</option>
                                <option value="office_transfer" <?= ($data['category'] ?? '') == 'office_transfer' ? 'selected' : '' ?>>
                                    <?= __('cashbox.office_transfer') ?>
                                </option>
                                <option value="customer_transfer" <?= ($data['category'] ?? '') == 'customer_transfer' ? 'selected' : '' ?>>
                                    <?= __('cashbox.customer_transfer') ?>
                                </option>
                                <option value="shipping_transfer" <?= ($data['category'] ?? '') == 'shipping_transfer' ? 'selected' : '' ?>>
                                    <?= __('cashbox.shipping_transfer') ?>
                                </option>
                                <option value="factory_payment" <?= ($data['category'] ?? '') == 'factory_payment' ? 'selected' : '' ?>>
                                    <?= __('cashbox.factory_payment') ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="amount_rmb" class="form-label"><?= __('amount') ?> (RMB)</label>
                                <div class="input-group">
                                    <span class="input-group-text">¥</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="amount_rmb" 
                                           name="amount_rmb" 
                                           step="0.01" 
                                           min="0"
                                           value="<?= $data['amount_rmb'] ?? '' ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="amount_usd" class="form-label"><?= __('amount') ?> (USD)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="amount_usd" 
                                           name="amount_usd" 
                                           step="0.01" 
                                           min="0"
                                           value="<?= $data['amount_usd'] ?? '' ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="amount_sdg" class="form-label"><?= __('amount') ?> (SDG)</label>
                                <div class="input-group">
                                    <span class="input-group-text">SDG</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="amount_sdg" 
                                           name="amount_sdg" 
                                           step="0.01" 
                                           min="0"
                                           value="<?= $data['amount_sdg'] ?? '' ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="amount_aed" class="form-label"><?= __('amount') ?> (AED)</label>
                                <div class="input-group">
                                    <span class="input-group-text">AED</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="amount_aed" 
                                           name="amount_aed" 
                                           step="0.01" 
                                           min="0"
                                           value="<?= $data['amount_aed'] ?? '' ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="bank_name" class="form-label"><?= __('cashbox.bank_name') ?></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="bank_name" 
                                       name="bank_name" 
                                       value="<?= $data['bank_name'] ?? '' ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="tt_number" class="form-label"><?= __('cashbox.tt_number') ?></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="tt_number" 
                                       name="tt_number" 
                                       value="<?= $data['tt_number'] ?? '' ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="receipt_no" class="form-label"><?= __('cashbox.receipt_no') ?></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="receipt_no" 
                                       name="receipt_no" 
                                       value="<?= $data['receipt_no'] ?? '' ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label"><?= __('transactions.description') ?></label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="3"><?= $data['description'] ?? '' ?></textarea>
                        </div>
                        
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle"></i>
                            <?= __('validation.required') ?> *
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/cashbox" class="btn btn-secondary">
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

<script>
document.getElementById('movement-form').addEventListener('submit', function(e) {
    // Check if at least one amount is entered
    const amounts = ['amount_rmb', 'amount_usd', 'amount_sdg', 'amount_aed'];
    let hasAmount = false;
    
    for (const field of amounts) {
        const value = parseFloat(document.getElementById(field).value) || 0;
        if (value > 0) {
            hasAmount = true;
            break;
        }
    }
    
    if (!hasAmount) {
        e.preventDefault();
        alert('<?= __('validation.required') ?>: <?= __('amount') ?>');
    }
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>