<?php
// app/Views/settings/index.php
include __DIR__ . '/../layouts/header.php';
?>

<div class="col-md-12 p-4">
    <h1 class="mb-4"><?= __('settings.title') ?></h1>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="/settings/save">
        <div class="row">
            <!-- General Settings -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> <?= __('settings.general') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="company_name" class="form-label"><?= __('company_name') ?></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="company_name" 
                                   name="company_name" 
                                   value="<?= $settings['company_name'] ?? __('company_name') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="company_phone" class="form-label"><?= __('clients.phone') ?></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="company_phone" 
                                   name="company_phone" 
                                   value="<?= $settings['company_phone'] ?? '' ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="company_address" class="form-label"><?= __('clients.address') ?></label>
                            <textarea class="form-control" 
                                      id="company_address" 
                                      name="company_address" 
                                      rows="3"><?= $settings['company_address'] ?? '' ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Exchange Rates -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-currency-exchange"></i> <?= __('settings.exchange_rates') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="exchange_rate_usd_rmb" class="form-label">USD → RMB</label>
                            <div class="input-group">
                                <span class="input-group-text">1 USD =</span>
                                <input type="number" 
                                       class="form-control" 
                                       id="exchange_rate_usd_rmb" 
                                       name="exchange_rate_usd_rmb" 
                                       step="0.0001" 
                                       value="<?= $exchangeRates['USD_RMB'] ?>">
                                <span class="input-group-text">RMB</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="exchange_rate_sdg_rmb" class="form-label">SDG → RMB</label>
                            <div class="input-group">
                                <span class="input-group-text">1 SDG =</span>
                                <input type="number" 
                                       class="form-control" 
                                       id="exchange_rate_sdg_rmb" 
                                       name="exchange_rate_sdg_rmb" 
                                       step="0.0001" 
                                       value="<?= $exchangeRates['SDG_RMB'] ?>">
                                <span class="input-group-text">RMB</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="exchange_rate_aed_rmb" class="form-label">AED → RMB</label>
                            <div class="input-group">
                                <span class="input-group-text">1 AED =</span>
                                <input type="number" 
                                       class="form-control" 
                                       id="exchange_rate_aed_rmb" 
                                       name="exchange_rate_aed_rmb" 
                                       step="0.0001" 
                                       value="<?= $exchangeRates['AED_RMB'] ?>">
                                <span class="input-group-text">RMB</span>
                            </div>
                        </div>
                        
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            <?= __('settings.exchange_rates_note') ?>
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Password & Security -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-shield-lock"></i> <?= __('settings.change_password') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="current_password" class="form-label"><?= __('settings.current_password') ?></label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label"><?= __('settings.new_password') ?></label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label"><?= __('settings.confirm_password') ?></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            <?= __('settings.password_note') ?>
                        </small>
                    </div>
                </div>
                
                <!-- System Actions -->
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-server"></i> <?= __('settings.system') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="/settings/backup" class="btn btn-info">
                                <i class="bi bi-download"></i> <?= __('settings.backup') ?>
                            </a>
                            
                            <a href="/users" class="btn btn-secondary">
                                <i class="bi bi-people"></i> <?= __('settings.users') ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle"></i> <?= __('save') ?>
            </button>
        </div>
    </form>
</div>

<script>
// Password validation
document.getElementById('new_password').addEventListener('input', function() {
    const newPass = this.value;
    const confirmPass = document.getElementById('confirm_password');
    
    if (newPass && confirmPass.value) {
        if (newPass !== confirmPass.value) {
            confirmPass.setCustomValidity('<?= __('validation.password_mismatch') ?>');
        } else {
            confirmPass.setCustomValidity('');
        }
    }
});

document.getElementById('confirm_password').addEventListener('input', function() {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = this.value;
    
    if (newPass !== confirmPass) {
        this.setCustomValidity('<?= __('validation.password_mismatch') ?>');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>