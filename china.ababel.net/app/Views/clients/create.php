<?php
// app/Views/clients/create.php
include __DIR__ . '/../layouts/header.php';
$isEdit = isset($client);
?>

<div class="col-md-12 p-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <?= $isEdit ? __('clients.edit_client') : __('clients.add_new') ?>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?= $isEdit ? '/clients/edit/' . $client['id'] : '/clients/create' ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="client_code" class="form-label">
                                    <?= __('clients.client_code') ?> *
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="client_code" 
                                       name="client_code" 
                                       value="<?= $client['client_code'] ?? $data['client_code'] ?? '' ?>"
                                       <?= $isEdit ? 'readonly' : 'required' ?>>
                                <small class="text-muted">
                                    <?= __('validation.unique') ?>
                                </small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="credit_limit" class="form-label">
                                    <?= __('clients.credit_limit') ?>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">¥</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="credit_limit" 
                                           name="credit_limit" 
                                           step="0.01" 
                                           min="0"
                                           value="<?= $client['credit_limit'] ?? $data['credit_limit'] ?? '0' ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                <?= __('clients.name_en') ?> *
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="name" 
                                   name="name" 
                                   value="<?= $client['name'] ?? $data['name'] ?? '' ?>"
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="name_ar" class="form-label">
                                <?= __('clients.name_ar') ?>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="name_ar" 
                                   name="name_ar" 
                                   value="<?= $client['name_ar'] ?? $data['name_ar'] ?? '' ?>"
                                   dir="rtl">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">
                                    <?= __('clients.phone') ?> *
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?= $client['phone'] ?? $data['phone'] ?? '' ?>"
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">
                                    <?= __('clients.email') ?>
                                </label>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       value="<?= $client['email'] ?? $data['email'] ?? '' ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">
                                <?= __('clients.address') ?>
                            </label>
                            <textarea class="form-control" 
                                      id="address" 
                                      name="address" 
                                      rows="3"><?= $client['address'] ?? $data['address'] ?? '' ?></textarea>
                        </div>
                        
                        <?php if ($isEdit): ?>
                        <div class="mb-3">
                            <label for="status" class="form-label">
                                <?= __('status') ?>
                            </label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= ($client['status'] ?? '') == 'active' ? 'selected' : '' ?>>
                                    <?= __('active') ?>
                                </option>
                                <option value="inactive" <?= ($client['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>
                                    <?= __('inactive') ?>
                                </option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle"></i>
                            <?= __('validation.required') ?> *
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/clients" class="btn btn-secondary">
                                <i class="bi bi-arrow-<?= isRTL() ? 'right' : 'left' ?>"></i> <?= __('back') ?>
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> <?= __('save') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($isEdit && ($client['current_balance_rmb'] ?? 0) != 0): ?>
            <div class="alert alert-warning mt-3">
                <i class="bi bi-exclamation-triangle"></i>
                <?= __('clients.current_balance') ?>: 
                <strong>¥<?= number_format($client['current_balance_rmb'], 2) ?></strong>
                <?php if (($client['current_balance_usd'] ?? 0) != 0): ?>
                    | <strong>$<?= number_format($client['current_balance_usd'], 2) ?></strong>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-generate client code if needed
document.addEventListener('DOMContentLoaded', function() {
    const clientCodeInput = document.getElementById('client_code');
    const nameInput = document.getElementById('name');
    
    <?php if (!$isEdit): ?>
    // Generate client code from name
    nameInput.addEventListener('input', function() {
        if (!clientCodeInput.value || clientCodeInput.value === generateCode(this.dataset.oldValue || '')) {
            clientCodeInput.value = generateCode(this.value);
        }
        this.dataset.oldValue = this.value;
    });
    
    function generateCode(name) {
        // Create code from first letters of words
        const words = name.trim().split(/\s+/);
        let code = '';
        
        if (words.length >= 2) {
            code = words.map(w => w.charAt(0).toUpperCase()).join('');
        } else if (words.length === 1 && words[0].length >= 3) {
            code = words[0].substring(0, 3).toUpperCase();
        }
        
        // Add random number if code is too short
        if (code.length < 3) {
            code += Math.floor(Math.random() * 1000);
        }
        
        return code + '-' + new Date().getFullYear();
    }
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>