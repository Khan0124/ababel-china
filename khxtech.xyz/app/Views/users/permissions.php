<?php
// app/Views/users/permissions.php
// User Permissions Page - Created 2025-01-10

// Load helpers
if (!function_exists('__')) {
    require_once BASE_PATH . '/app/Core/helpers.php';
}

$title = __('users.role_permissions');
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-shield-check"></i> <?= __('users.role_permissions') ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/dashboard"><?= __('nav.dashboard') ?></a></li>
                            <li class="breadcrumb-item"><a href="/users"><?= __('users.title') ?></a></li>
                            <li class="breadcrumb-item active"><?= __('users.permissions') ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="/users" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> <?= __('back') ?>
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- User Info Card -->
                <?php if (isset($user)): ?>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-person-badge"></i> 
                                <?= __('users.view') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="avatar-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; border-radius: 50%; font-size: 24px;">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                            </div>
                            
                            <h5 class="text-center mb-3"><?= h($user['name'] ?? '') ?></h5>
                            
                            <div class="mb-2">
                                <strong><?= __('users.username') ?>:</strong> <?= h($user['username'] ?? '') ?>
                            </div>
                            <div class="mb-2">
                                <strong><?= __('users.email') ?>:</strong> <?= h($user['email'] ?? '') ?>
                            </div>
                            <div class="mb-2">
                                <strong><?= __('users.role') ?>:</strong> 
                                <span class="badge bg-primary"><?= __('users.role_' . $user['role']) ?></span>
                            </div>
                            <div class="mb-2">
                                <strong><?= __('users.status') ?>:</strong> 
                                <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= __('users.status_' . $user['status']) ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <strong><?= __('users.last_login') ?>:</strong><br>
                                <small class="text-muted">
                                    <?= $user['last_login'] ? date('Y-m-d H:i:s', strtotime($user['last_login'])) : __('users.never') ?>
                                </small>
                            </div>
                            
                            <hr>
                            
                            <div class="d-grid gap-2">
                                <a href="/users/edit/<?= $user['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> <?= __('edit') ?>
                                </a>
                                <a href="/users/activity/<?= $user['id'] ?>" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-clock-history"></i> <?= __('users.activity_log') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Permissions Content -->
                <div class="col-lg-<?= isset($user) ? '8' : '12' ?>">
                    <?php if (isset($user)): ?>
                    <!-- Individual User Permissions -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-key"></i> 
                                <?= __('users.permissions') ?>: <?= h($user['name'] ?? '') ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <?= __('users.role_' . $user['role'] . '_desc') ?>
                            </div>
                            
                            <div class="mb-4">
                                <h6 class="text-primary"><?= __('users.role_permissions') ?></h6>
                                <p><?= __('users.role_' . $user['role'] . '_permissions') ?></p>
                            </div>
                            
                            <!-- Current Permissions List -->
                            <div id="permissionsList">
                                <!-- Will be populated by JavaScript based on role -->
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Role-Based Permissions Overview -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-shield-check"></i> 
                                <?= __('users.role_permissions') ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                <?= __('users.select_role_to_see_permissions') ?>
                            </p>
                            
                            <!-- Role Selector -->
                            <div class="mb-4">
                                <label for="roleSelector" class="form-label"><?= __('users.role') ?></label>
                                <select class="form-select" id="roleSelector">
                                    <option value=""><?= __('common.select') ?>...</option>
                                    <option value="admin"><?= __('users.role_admin') ?></option>
                                    <option value="accountant"><?= __('users.role_accountant') ?></option>
                                    <option value="manager"><?= __('users.role_manager') ?></option>
                                    <option value="user"><?= __('users.role_user') ?></option>
                                </select>
                            </div>
                            
                            <!-- Permissions Display -->
                            <div id="permissionsDisplay" style="display: none;">
                                <div class="alert alert-primary" id="roleDescription"></div>
                                <div id="permissionsList"></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- System Permissions Reference -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-list-check"></i> 
                                System Permissions Reference
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-success">Core Permissions</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-check-circle text-success"></i> Dashboard Access</li>
                                        <li><i class="bi bi-check-circle text-success"></i> Profile Management</li>
                                        <li><i class="bi bi-check-circle text-success"></i> Language Settings</li>
                                        <li><i class="bi bi-check-circle text-success"></i> Basic Reports View</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary">Advanced Permissions</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-shield text-primary"></i> User Management</li>
                                        <li><i class="bi bi-shield text-primary"></i> System Settings</li>
                                        <li><i class="bi bi-shield text-primary"></i> Financial Operations</li>
                                        <li><i class="bi bi-shield text-primary"></i> Data Export/Import</li>
                                    </ul>
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
    // Define permissions for each role
    const rolePermissions = {
        admin: {
            description: '<?= addslashes(__('users.role_admin_desc')) ?>',
            permissions: [
                { name: '<?= addslashes(__('users.title')) ?>', level: 'full', icon: 'people' },
                { name: '<?= addslashes(__('nav.dashboard')) ?>', level: 'full', icon: 'speedometer2' },
                { name: '<?= addslashes(__('nav.clients')) ?>', level: 'full', icon: 'people-fill' },
                { name: '<?= addslashes(__('nav.transactions')) ?>', level: 'full', icon: 'receipt' },
                { name: '<?= addslashes(__('nav.cashbox')) ?>', level: 'full', icon: 'cash-stack' },
                { name: '<?= addslashes(__('nav.reports')) ?>', level: 'full', icon: 'file-earmark-text' },
                { name: '<?= addslashes(__('nav.settings')) ?>', level: 'full', icon: 'gear' },
                { name: 'System Management', level: 'full', icon: 'tools' }
            ]
        },
        accountant: {
            description: '<?= addslashes(__('users.role_accountant_desc')) ?>',
            permissions: [
                { name: '<?= addslashes(__('users.title')) ?>', level: 'view', icon: 'people' },
                { name: '<?= addslashes(__('nav.dashboard')) ?>', level: 'view', icon: 'speedometer2' },
                { name: '<?= addslashes(__('nav.clients')) ?>', level: 'full', icon: 'people-fill' },
                { name: '<?= addslashes(__('nav.transactions')) ?>', level: 'full', icon: 'receipt' },
                { name: '<?= addslashes(__('nav.cashbox')) ?>', level: 'full', icon: 'cash-stack' },
                { name: '<?= addslashes(__('nav.reports')) ?>', level: 'full', icon: 'file-earmark-text' },
                { name: 'Financial Settings', level: 'edit', icon: 'calculator' }
            ]
        },
        manager: {
            description: '<?= addslashes(__('users.role_manager_desc')) ?>',
            permissions: [
                { name: '<?= addslashes(__('nav.dashboard')) ?>', level: 'view', icon: 'speedometer2' },
                { name: '<?= addslashes(__('nav.clients')) ?>', level: 'edit', icon: 'people-fill' },
                { name: '<?= addslashes(__('nav.transactions')) ?>', level: 'edit', icon: 'receipt' },
                { name: '<?= addslashes(__('nav.reports')) ?>', level: 'view', icon: 'file-earmark-text' },
                { name: 'Operations Management', level: 'edit', icon: 'diagram-3' }
            ]
        },
        user: {
            description: '<?= addslashes(__('users.role_user_desc')) ?>',
            permissions: [
                { name: '<?= addslashes(__('nav.dashboard')) ?>', level: 'view', icon: 'speedometer2' },
                { name: '<?= addslashes(__('nav.clients')) ?>', level: 'view', icon: 'people-fill' },
                { name: '<?= addslashes(__('nav.transactions')) ?>', level: 'view', icon: 'receipt' },
                { name: 'Basic Reports', level: 'view', icon: 'file-earmark-text' }
            ]
        }
    };

    // Function to display permissions
    function displayPermissions(role) {
        const roleData = rolePermissions[role];
        if (!roleData) return;

        const permissionsList = document.getElementById('permissionsList');
        const roleDescription = document.getElementById('roleDescription');

        if (roleDescription) {
            roleDescription.textContent = roleData.description;
        }

        let html = '<div class="row">';
        roleData.permissions.forEach((permission, index) => {
            if (index > 0 && index % 2 === 0) html += '</div><div class="row">';
            
            const levelColor = {
                'full': 'success',
                'edit': 'warning', 
                'view': 'info'
            }[permission.level] || 'secondary';
            
            const levelText = {
                'full': 'Full Access',
                'edit': 'Edit Access',
                'view': 'View Only'
            }[permission.level] || permission.level;

            html += `
                <div class="col-md-6 mb-3">
                    <div class="d-flex align-items-center p-3 border rounded">
                        <div class="me-3">
                            <i class="bi bi-${permission.icon} text-primary fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${permission.name}</h6>
                            <span class="badge bg-${levelColor}">${levelText}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        permissionsList.innerHTML = html;
    }

    // If user is set, display their permissions
    <?php if (isset($user)): ?>
    displayPermissions('<?= $user['role'] ?>');
    <?php else: ?>
    // Role selector functionality
    const roleSelector = document.getElementById('roleSelector');
    const permissionsDisplay = document.getElementById('permissionsDisplay');

    roleSelector.addEventListener('change', function() {
        const selectedRole = this.value;
        if (selectedRole) {
            displayPermissions(selectedRole);
            permissionsDisplay.style.display = 'block';
        } else {
            permissionsDisplay.style.display = 'none';
        }
    });
    <?php endif; ?>
});
</script>

<style>
.avatar-circle {
    font-weight: bold;
}

.border.rounded {
    transition: all 0.2s;
}

.border.rounded:hover {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}
</style>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>