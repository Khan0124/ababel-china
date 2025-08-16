<?php
// app/Views/users/edit.php
// User Edit Page - Created 2025-01-10

// Load helpers
if (!function_exists('__')) {
    require_once BASE_PATH . '/app/Core/helpers.php';
}

$title = __('users.edit');
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-person-gear"></i> <?= __('users.edit') ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/dashboard"><?= __('nav.dashboard') ?></a></li>
                            <li class="breadcrumb-item"><a href="/users"><?= __('users.title') ?></a></li>
                            <li class="breadcrumb-item active"><?= __('users.edit') ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="/users" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> <?= __('back') ?>
                    </a>
                </div>
            </div>

            <!-- User Edit Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-pencil-square"></i> 
                                <?= __('users.edit') ?>: <?= h($user['name'] ?? '') ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="editUserForm" method="POST" action="/users/update/<?= $user['id'] ?>">
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
                                                   value="<?= htmlspecialchars($user['name'] ?? $user['full_name']) ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="username" class="form-label">
                                                <?= __('users.username') ?> <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   value="<?= h($user['username'] ?? '') ?>" required>
                                            <div class="form-text"><?= __('users.username_hint') ?></div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="email" class="form-label">
                                                <?= __('users.email') ?> <span class="text-danger">*</span>
                                            </label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?= h($user['email'] ?? '') ?>" required>
                                        </div>
                                    </div>

                                    <!-- Role & Status -->
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
                                                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>
                                                    <?= __('users.role_user') ?>
                                                </option>
                                                <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>
                                                    <?= __('users.role_manager') ?>
                                                </option>
                                                <option value="accountant" <?= $user['role'] === 'accountant' ? 'selected' : '' ?>>
                                                    <?= __('users.role_accountant') ?>
                                                </option>
                                                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>
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
                                                <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>
                                                    <?= __('users.status_active') ?>
                                                </option>
                                                <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>
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
                                        
                                        <div class="alert alert-warning">
                                            <i class="bi bi-info-circle"></i>
                                            <?= __('settings.password_note') ?>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="password" class="form-label">
                                                        <?= __('settings.new_password') ?>
                                                    </label>
                                                    <input type="password" class="form-control" id="password" name="password" 
                                                           autocomplete="new-password">
                                                    <div class="form-text"><?= __('users.password_hint') ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="confirm_password" class="form-label">
                                                        <?= __('users.confirm_password') ?>
                                                    </label>
                                                    <input type="password" class="form-control" id="confirm_password" 
                                                           name="confirm_password" autocomplete="new-password">
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
                                                    <i class="bi bi-check-lg"></i> <?= __('save') ?>
                                                </button>
                                                <a href="/users" class="btn btn-secondary ms-2">
                                                    <i class="bi bi-x-lg"></i> <?= __('cancel') ?>
                                                </a>
                                            </div>
                                            
                                            <?php if ($user['id'] != ($_SESSION['user_id'] ?? 0)): ?>
                                            <div>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="confirmDeleteUser(<?= $user['id'] ?>)">
                                                    <i class="bi bi-trash"></i> <?= __('users.delete') ?>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- User Information Sidebar -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-info-circle"></i> 
                                <?= __('users.view') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong><?= __('users.id') ?>:</strong> #<?= $user['id'] ?>
                            </div>
                            <div class="mb-3">
                                <strong><?= __('users.last_login') ?>:</strong><br>
                                <?= $user['last_login'] ? date('Y-m-d H:i:s', strtotime($user['last_login'])) : __('users.never') ?>
                            </div>
                            <div class="mb-3">
                                <strong><?= __('created_at') ?>:</strong><br>
                                <?= date('Y-m-d H:i:s', strtotime($user['created_at'])) ?>
                            </div>
                            <?php if (isset($user['updated_at']) && $user['updated_at']): ?>
                            <div class="mb-3">
                                <strong><?= __('updated_by') ?>:</strong><br>
                                <?= date('Y-m-d H:i:s', strtotime($user['updated_at'])) ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Quick Actions -->
                            <div class="mt-4">
                                <h6><?= __('actions') ?>:</h6>
                                <div class="d-grid gap-2">
                                    <a href="/users/permissions/<?= $user['id'] ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-shield-check"></i> <?= __('users.permissions') ?>
                                    </a>
                                    <a href="/users/activity/<?= $user['id'] ?>" class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-clock-history"></i> <?= __('users.activity_log') ?>
                                    </a>
                                    <?php if ($user['status'] === 'active'): ?>
                                    <button type="button" class="btn btn-outline-warning btn-sm" 
                                            onclick="toggleUserStatus(<?= $user['id'] ?>, 'inactive')">
                                        <i class="bi bi-pause"></i> <?= __('users.status_inactive') ?>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-outline-success btn-sm" 
                                            onclick="toggleUserStatus(<?= $user['id'] ?>, 'active')">
                                        <i class="bi bi-play"></i> <?= __('users.status_active') ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
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
    const form = document.getElementById('editUserForm');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    form.addEventListener('submit', function(e) {
        // Password validation
        if (password.value && password.value.length < 8) {
            e.preventDefault();
            alert('<?= addslashes(__('users.password_too_short')) ?>');
            return;
        }
        
        if (password.value !== confirmPassword.value) {
            e.preventDefault();
            alert('<?= addslashes(__('users.passwords_not_match')) ?>');
            return;
        }
    });
});

// Delete user function
function confirmDeleteUser(userId) {
    if (confirm('<?= addslashes(__('users.delete_confirmation')) ?>')) {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/users/delete/' + userId;
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_method';
        input.value = 'DELETE';
        form.appendChild(input);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Toggle user status
function toggleUserStatus(userId, newStatus) {
    const statusText = newStatus === 'active' ? '<?= addslashes(__('users.status_active')) ?>' : '<?= addslashes(__('users.status_inactive')) ?>';
    
    if (confirm('<?= addslashes(__('messages.are_you_sure')) ?> ' + statusText + '?')) {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/users/toggle-status/' + userId;
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'status';
        input.value = newStatus;
        form.appendChild(input);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>