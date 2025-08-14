<?php
// app/Views/users/index.php
// Users List Page - Updated 2025-01-10

// Load helpers
if (!function_exists('__')) {
    require_once BASE_PATH . '/app/Core/helpers.php';
}

$title = __('users.title');
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-people"></i> <?= __('users.title') ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/dashboard"><?= __('nav.dashboard') ?></a></li>
                            <li class="breadcrumb-item active"><?= __('users.title') ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="/users/create" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> <?= __('users.add_new') ?>
                    </a>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i>
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <!-- Users Statistics -->
            <?php if (isset($roleStats) && !empty($roleStats)): ?>
            <div class="row mb-4">
                <?php foreach ($roleStats as $stat): ?>
                <div class="col-md-3 mb-3">
                    <div class="card text-center border-<?= getRoleColor($stat['role']) ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-center align-items-center mb-2">
                                <div class="rounded-circle bg-<?= getRoleColor($stat['role']) ?> text-white d-flex justify-content-center align-items-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-<?= getRoleIcon($stat['role']) ?>"></i>
                                </div>
                            </div>
                            <h4 class="text-<?= getRoleColor($stat['role']) ?>"><?= $stat['count'] ?></h4>
                            <p class="text-muted small mb-0"><?= __('users.role_' . $stat['role']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-list"></i> 
                            <?= __('users.list') ?>
                        </h5>
                        <div class="d-flex gap-2">
                            <input type="search" class="form-control form-control-sm" 
                                   placeholder="<?= __('search') ?>..." id="userSearch" style="width: 200px;">
                            <select class="form-select form-select-sm" id="roleFilter" style="width: 150px;">
                                <option value=""><?= __('all') ?> <?= __('users.role') ?>s</option>
                                <option value="admin"><?= __('users.role_admin') ?></option>
                                <option value="accountant"><?= __('users.role_accountant') ?></option>
                                <option value="manager"><?= __('users.role_manager') ?></option>
                                <option value="user"><?= __('users.role_user') ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3"><?= __('no_data') ?></h5>
                        <p class="text-muted"><?= __('users.add_new') ?></p>
                        <a href="/users/create" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> <?= __('users.add_new') ?>
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="usersTable">
                            <thead class="table-light">
                                <tr>
                                    <th><?= __('users.id') ?></th>
                                    <th><?= __('users.name') ?></th>
                                    <th><?= __('users.username') ?></th>
                                    <th><?= __('users.email') ?></th>
                                    <th><?= __('users.role') ?></th>
                                    <th><?= __('users.status') ?></th>
                                    <th><?= __('users.last_login') ?></th>
                                    <th class="text-center"><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr data-role="<?= htmlspecialchars($user['role'] ?? 'user') ?>" 
                                    data-name="<?= htmlspecialchars($user['display_name'] ?? $user['full_name'] ?? '') ?>">
                                    <td>
                                        <span class="badge bg-secondary">#<?= $user['id'] ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-primary text-white me-3" 
                                                 style="width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
                                                <?= strtoupper(substr($user['display_name'] ?? $user['full_name'] ?? $user['username'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($user['display_name'] ?? $user['full_name'] ?? '') ?></div>
                                                <small class="text-muted">ID: <?= $user['id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <code><?= htmlspecialchars($user['username']) ?></code>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($user['email']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= getRoleColor($user['role'] ?? 'user') ?>">
                                            <i class="bi bi-<?= getRoleIcon($user['role'] ?? 'user') ?>"></i>
                                            <?= __('users.role_' . ($user['role'] ?? 'user')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = $user['status'] ?? ($user['is_active'] ? 'active' : 'inactive');
                                        $statusColor = $status === 'active' ? 'success' : 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>">
                                            <i class="bi bi-<?= $status === 'active' ? 'check-circle' : 'pause-circle' ?>"></i>
                                            <?= $status === 'active' ? __('users.status_active') : __('users.status_inactive') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : __('users.never') ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="/users/edit/<?= $user['id'] ?>" 
                                               class="btn btn-outline-primary" title="<?= __('edit') ?>">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="/users/permissions/<?= $user['id'] ?>" 
                                               class="btn btn-outline-info" title="<?= __('users.permissions') ?>">
                                                <i class="bi bi-shield-check"></i>
                                            </a>
                                            <a href="/users/activity/<?= $user['id'] ?>" 
                                               class="btn btn-outline-secondary" title="<?= __('users.activity_log') ?>">
                                                <i class="bi bi-clock-history"></i>
                                            </a>
                                            <?php if ($user['id'] != ($_SESSION['user_id'] ?? 0)): ?>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="confirmDeleteUser(<?= $user['id'] ?>)" 
                                                    title="<?= __('delete') ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
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
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('userSearch');
    const roleFilter = document.getElementById('roleFilter');
    const tableRows = document.querySelectorAll('#usersTable tbody tr');
    
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedRole = roleFilter.value;
        
        tableRows.forEach(row => {
            const name = row.dataset.name.toLowerCase();
            const role = row.dataset.role;
            const username = row.querySelector('code').textContent.toLowerCase();
            const email = row.querySelector('a[href^="mailto"]').textContent.toLowerCase();
            
            const matchesSearch = !searchTerm || 
                                name.includes(searchTerm) || 
                                username.includes(searchTerm) || 
                                email.includes(searchTerm);
            
            const matchesRole = !selectedRole || role === selectedRole;
            
            if (matchesSearch && matchesRole) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    searchInput.addEventListener('input', filterTable);
    roleFilter.addEventListener('change', filterTable);
});

// Delete user function
function confirmDeleteUser(userId) {
    if (confirm('<?= addslashes(__('users.delete_confirmation')) ?>')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/users/delete/' + userId;
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<style>
.avatar-circle {
    min-width: 35px;
    min-height: 35px;
}
</style>

<?php 
// Helper functions for role colors and icons
function getRoleColor($role) {
    $colors = [
        'admin' => 'danger',
        'accountant' => 'primary',
        'manager' => 'warning',
        'user' => 'success'
    ];
    return $colors[$role] ?? 'secondary';
}

function getRoleIcon($role) {
    $icons = [
        'admin' => 'shield-fill-check',
        'accountant' => 'calculator',
        'manager' => 'person-gear',
        'user' => 'person'
    ];
    return $icons[$role] ?? 'person';
}
?>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>