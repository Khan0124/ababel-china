<?php
// app/Views/users/create.php
// Create User Page - Updated 2025-01-10

// Load helpers
if (!function_exists('__')) {
    require_once BASE_PATH . '/app/Core/helpers.php';
}

$title = __('users.add_new');
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-person-plus"></i> <?= __('users.add_new') ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/dashboard"><?= __('nav.dashboard') ?></a></li>
                            <li class="breadcrumb-item"><a href="/users"><?= __('users.title') ?></a></li>
                            <li class="breadcrumb-item active"><?= __('users.add_new') ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="/users" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> <?= __('back') ?>
                    </a>
                </div>
            </div>

            <!-- Error Messages -->
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <?= h($_SESSION['error'] ?? '') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <?php if (isset($_SESSION['errors'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <strong><?= __('validation.required') ?>:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                    <li><?= h($error ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['errors']); endif; ?>

            <!-- Create User Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-person-badge"></i> 
                                <?= __('users.add_new') ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="createUserForm" method="POST" action="/users/create">
                                <?= csrf_field() ?>
                                <div class="row">
                                    <!-- Basic Information -->
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">
                                            <i class="bi bi-info-circle"></i> 
                                            <?= __('loadings.basic_information') ?>
                                        </h6>
                                        
                                        <div class="mb-3">
                                            <label for="name" class="form-label">
                                                <?= __('users.full_name') ?> <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?= htmlspecialchars($_SESSION['old']['name'] ?? '') ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="username" class="form-label">
                                                <?= __('users.username') ?> <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   value="<?= htmlspecialchars($_SESSION['old']['username'] ?? '') ?>" required>
                                            <div class="form-text"><?= __('users.username_hint') ?></div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="email" class="form-label">
                                                <?= __('users.email') ?> <span class="text-danger">*</span>
                                            </label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?= htmlspecialchars($_SESSION['old']['email'] ?? '') ?>" required>
                                        </div>
                                    </div>

                                    <!-- Role & Password -->
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">
                                            <i class="bi bi-shield-check"></i> 
                                            <?= __('users.permissions') ?>
                                        </h6>
                                        
                                        <div class="mb-3">
                                            <label for="role" class="form-label">
                                                <?= __('users.role') ?> <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" id="role" name="role" required>
                                                <option value=""><?= __('common.select') ?>...</option>
                                                <option value="user" <?= ($_SESSION['old']['role'] ?? '') === 'user' ? 'selected' : '' ?>>
                                                    <?= __('users.role_user') ?>
                                                </option>
                                                <option value="manager" <?= ($_SESSION['old']['role'] ?? '') === 'manager' ? 'selected' : '' ?>>
                                                    <?= __('users.role_manager') ?>
                                                </option>
                                                <option value="accountant" <?= ($_SESSION['old']['role'] ?? '') === 'accountant' ? 'selected' : '' ?>>
                                                    <?= __('users.role_accountant') ?>
                                                </option>
                                                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                                                <option value="admin" <?= ($_SESSION['old']['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                                                    <?= __('users.role_admin') ?>
                                                </option>
                                                <?php endif; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="status" class="form-label">
                                                <?= __('users.status') ?> <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="active" <?= ($_SESSION['old']['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>
                                                    <?= __('users.status_active') ?>
                                                </option>
                                                <option value="inactive" <?= ($_SESSION['old']['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                                                    <?= __('users.status_inactive') ?>
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Role Description -->
                                        <div class="alert alert-info" id="roleDescription">
                                            <small id="roleDescText"><?= __('users.select_role_to_see_permissions') ?></small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Password Section -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h6 class="text-primary mb-3">
                                            <i class="bi bi-key"></i> 
                                            <?= __('settings.change_password') ?>
                                        </h6>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="password" class="form-label">
                                                        <?= __('users.password') ?> <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="password" class="form-control" id="password" name="password" 
                                                           required autocomplete="new-password">
                                                    <div class="form-text"><?= __('users.password_hint') ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="confirm_password" class="form-label">
                                                        <?= __('users.confirm_password') ?> <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="password" class="form-control" id="confirm_password" 
                                                           name="confirm_password" required autocomplete="new-password">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <hr>
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-plus-lg"></i> <?= __('users.add_new') ?>
                                                </button>
                                                <a href="/users" class="btn btn-secondary ms-2">
                                                    <i class="bi bi-x-lg"></i> <?= __('cancel') ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-info-circle"></i> 
                                <?= __('info') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <h6><?= __('users.role_permissions') ?></h6>
                            
                            <div class="mb-3">
                                <strong><?= __('users.role_admin') ?>:</strong><br>
                                <small class="text-muted"><?= __('users.role_admin_desc') ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <strong><?= __('users.role_accountant') ?>:</strong><br>
                                <small class="text-muted"><?= __('users.role_accountant_desc') ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <strong><?= __('users.role_manager') ?>:</strong><br>
                                <small class="text-muted"><?= __('users.role_manager_desc') ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <strong><?= __('users.role_user') ?>:</strong><br>
                                <small class="text-muted"><?= __('users.role_user_desc') ?></small>
                            </div>
                            
                            <hr>
                            
                            <h6><?= __('users.password_hint') ?></h6>
                            <ul class="small text-muted">
                                <li>استخدم كلمة مرور قوية</li>
                                <li>لا تقل عن 8 أحرف</li>
                                <li>امزج بين الأحرف والأرقام</li>
                                <li>أضف رموز خاصة إن أمكن</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const roleDescText = document.getElementById('roleDescText');
    
    // Role descriptions
    const roleDescriptions = {
        'admin': '<?= addslashes(__('users.role_admin_permissions')) ?>',
        'accountant': '<?= addslashes(__('users.role_accountant_permissions')) ?>',
        'manager': '<?= addslashes(__('users.role_manager_permissions')) ?>',
        'user': '<?= addslashes(__('users.role_user_permissions')) ?>'
    };
    
    // Update role description
    function updateRoleDescription() {
        const selectedRole = roleSelect.value;
        roleDescText.textContent = roleDescriptions[selectedRole] || '<?= addslashes(__('users.select_role_to_see_permissions')) ?>';
    }
    
    roleSelect.addEventListener('change', updateRoleDescription);
    updateRoleDescription(); // Initial load
    
    // Form validation
    const form = document.getElementById('createUserForm');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    form.addEventListener('submit', function(e) {
        let isValid = true;
        let errorMsg = '';
        
        // Password validation
        if (password.value.length < 8) {
            isValid = false;
            errorMsg += '<?= addslashes(__('users.password_too_short')) ?>\n';
        }
        
        if (password.value !== confirmPassword.value) {
            isValid = false;
            errorMsg += '<?= addslashes(__('users.passwords_not_match')) ?>\n';
        }
        
        if (!isValid) {
            e.preventDefault();
            alert(errorMsg);
            return false;
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> <?= addslashes(__('loading')) ?>...';
    });
});
</script>

<?php 
// Clear old form data
unset($_SESSION['old']); 
?>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>