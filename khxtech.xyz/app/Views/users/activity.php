<?php
// app/Views/users/activity.php
// User Activity Log Page - Created 2025-01-10

// Load helpers
if (!function_exists('__')) {
    require_once BASE_PATH . '/app/Core/helpers.php';
}

$title = __('users.activity_log');
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-clock-history"></i> <?= __('users.activity_log') ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/dashboard"><?= __('nav.dashboard') ?></a></li>
                            <li class="breadcrumb-item"><a href="/users"><?= __('users.title') ?></a></li>
                            <li class="breadcrumb-item active"><?= __('users.activity_log') ?></li>
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
                <!-- User Info Sidebar -->
                <?php if (isset($user)): ?>
                <div class="col-lg-3">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-person-badge"></i> 
                                User Details
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="avatar-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                                     style="width: 50px; height: 50px; border-radius: 50%; font-size: 20px;">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                            </div>
                            
                            <h6 class="text-center mb-3"><?= h($user['name'] ?? '') ?></h6>
                            
                            <div class="small">
                                <div class="mb-2">
                                    <strong><?= __('users.username') ?>:</strong><br>
                                    <span class="text-muted"><?= h($user['username'] ?? '') ?></span>
                                </div>
                                <div class="mb-2">
                                    <strong><?= __('users.role') ?>:</strong><br>
                                    <span class="badge bg-primary"><?= __('users.role_' . $user['role']) ?></span>
                                </div>
                                <div class="mb-2">
                                    <strong><?= __('users.status') ?>:</strong><br>
                                    <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= __('users.status_' . $user['status']) ?>
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <strong><?= __('users.last_login') ?>:</strong><br>
                                    <span class="text-muted">
                                        <?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : __('users.never') ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="/users/edit/<?= $user['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> <?= __('edit') ?>
                                </a>
                                <a href="/users/permissions/<?= $user['id'] ?>" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-shield-check"></i> <?= __('users.permissions') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Activity Log Content -->
                <div class="col-lg-<?= isset($user) ? '9' : '12' ?>">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-list-ul"></i> 
                                    <?= isset($user) ? __('users.activity_log') . ': ' . h($user['name'] ?? '') : __('users.activity_log') ?>
                                </h5>
                                <div>
                                    <!-- Filter Options -->
                                    <div class="btn-group" role="group">
                                        <input type="radio" class="btn-check" name="activityFilter" id="filterAll" value="all" checked>
                                        <label class="btn btn-outline-primary btn-sm" for="filterAll"><?= __('all') ?></label>
                                        
                                        <input type="radio" class="btn-check" name="activityFilter" id="filterLogin" value="login">
                                        <label class="btn btn-outline-primary btn-sm" for="filterLogin">Logins</label>
                                        
                                        <input type="radio" class="btn-check" name="activityFilter" id="filterActions" value="actions">
                                        <label class="btn btn-outline-primary btn-sm" for="filterActions">Actions</label>
                                        
                                        <input type="radio" class="btn-check" name="activityFilter" id="filterErrors" value="errors">
                                        <label class="btn btn-outline-primary btn-sm" for="filterErrors">Errors</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!isset($activities) || empty($activities)): ?>
                            <!-- No Activity Message -->
                            <div class="text-center py-5">
                                <i class="bi bi-clock-history text-muted" style="font-size: 3rem;"></i>
                                <h5 class="text-muted mt-3">No Activity Found</h5>
                                <p class="text-muted">No activity records found for this user.</p>
                            </div>
                            <?php else: ?>
                            <!-- Activity Timeline -->
                            <div class="activity-timeline">
                                <?php foreach ($activities as $activity): ?>
                                <div class="activity-item" data-type="<?= h($activity['type'] ?? '') ?>" data-date="<?= $activity['created_at'] ?>">
                                    <div class="activity-marker bg-<?= getActivityColor($activity['type']) ?>">
                                        <i class="bi bi-<?= getActivityIcon($activity['type']) ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= h($activity['action'] ?? '') ?></h6>
                                                <p class="text-muted mb-2">
                                                    <?php 
                                                    $description = $activity['description'] ?? '';
                                                    // إذا كان النص JSON، استخرج البيانات منه
                                                    if (is_string($description) && (strpos($description, '{') === 0 || strpos($description, '[') === 0)) {
                                                        $jsonData = json_decode($description, true);
                                                        if ($jsonData) {
                                                            if (isset($jsonData['description'])) {
                                                                echo h($jsonData['description'] ?? '');
                                                            } elseif (isset($jsonData['bol_number'])) {
                                                                echo "إصدار بوليصة الشحن: " . h($jsonData['bol_number'] ?? '');
                                                            } else {
                                                                echo htmlspecialchars(implode(', ', $jsonData));
                                                            }
                                                        } else {
                                                            echo h($description ?? '');
                                                        }
                                                    } else {
                                                        echo h($description ?? '');
                                                    }
                                                    ?>
                                                </p>
                                                <div class="small text-muted">
                                                    <i class="bi bi-geo-alt"></i> IP: <?= htmlspecialchars($activity['ip_address'] ?? 'Unknown') ?>
                                                    <?php if (!empty($activity['user_agent'])): ?>
                                                    <br><i class="bi bi-device-hdd"></i> <?= h($activity['user_agent'] ?? '') ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted">
                                                    <?= date('M j, Y', strtotime($activity['created_at'])) ?><br>
                                                    <?= date('H:i:s', strtotime($activity['created_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Activity Statistics -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-bar-chart"></i> 
                                Activity Statistics
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 col-lg-3">
                                    <div class="border rounded p-3">
                                        <h4 class="text-success mb-1" id="loginCount">0</h4>
                                        <small class="text-muted">Logins</small>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="border rounded p-3">
                                        <h4 class="text-primary mb-1" id="actionCount">0</h4>
                                        <small class="text-muted">Actions</small>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="border rounded p-3">
                                        <h4 class="text-warning mb-1" id="errorCount">0</h4>
                                        <small class="text-muted">Errors</small>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="border rounded p-3">
                                        <h4 class="text-info mb-1" id="totalCount">0</h4>
                                        <small class="text-muted">Total</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.activity-timeline {
    position: relative;
    padding-left: 2rem;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 1rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.activity-item {
    position: relative;
    margin-bottom: 2rem;
}

.activity-marker {
    position: absolute;
    left: -2rem;
    top: 0.5rem;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
}

.activity-content {
    background: #f8f9fa;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-left: 1rem;
    border: 1px solid #e9ecef;
}

.activity-item.filtered-out {
    display: none;
}

.avatar-circle {
    font-weight: bold;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sample activity data (in real implementation, this would come from PHP)
    const sampleActivities = [
        { type: 'login', action: 'User Login', description: 'Successfully logged into the system', created_at: '2025-01-10 09:15:23', ip_address: '192.168.1.100' },
        { type: 'action', action: 'Client Created', description: 'Created new client: ABC Company', created_at: '2025-01-10 10:30:15', ip_address: '192.168.1.100' },
        { type: 'action', action: 'Transaction Approved', description: 'Approved transaction #12345', created_at: '2025-01-10 11:45:30', ip_address: '192.168.1.100' },
        { type: 'error', action: 'Failed Login Attempt', description: 'Invalid password provided', created_at: '2025-01-09 14:20:10', ip_address: '192.168.1.105' },
        { type: 'action', action: 'Report Generated', description: 'Generated daily financial report', created_at: '2025-01-09 16:00:00', ip_address: '192.168.1.100' }
    ];

    // Update activity statistics
    function updateStatistics() {
        const activities = document.querySelectorAll('.activity-item:not(.filtered-out)');
        let loginCount = 0, actionCount = 0, errorCount = 0;

        activities.forEach(item => {
            const type = item.dataset.type;
            switch(type) {
                case 'login': loginCount++; break;
                case 'action': actionCount++; break;
                case 'error': errorCount++; break;
            }
        });

        document.getElementById('loginCount').textContent = loginCount;
        document.getElementById('actionCount').textContent = actionCount;
        document.getElementById('errorCount').textContent = errorCount;
        document.getElementById('totalCount').textContent = loginCount + actionCount + errorCount;
    }

    // Filter functionality
    const filterButtons = document.querySelectorAll('input[name="activityFilter"]');
    filterButtons.forEach(button => {
        button.addEventListener('change', function() {
            const filterType = this.value;
            const activities = document.querySelectorAll('.activity-item');
            
            activities.forEach(item => {
                if (filterType === 'all' || item.dataset.type === filterType) {
                    item.classList.remove('filtered-out');
                } else {
                    item.classList.add('filtered-out');
                }
            });
            
            updateStatistics();
        });
    });

    // Initial statistics update
    updateStatistics();
    
    // If no activities exist, create sample timeline
    const timeline = document.querySelector('.activity-timeline');
    if (timeline && timeline.children.length === 0) {
        sampleActivities.forEach(activity => {
            const activityItem = document.createElement('div');
            activityItem.className = 'activity-item';
            activityItem.dataset.type = activity.type;
            activityItem.dataset.date = activity.created_at;
            
            const color = getActivityColor(activity.type);
            const icon = getActivityIcon(activity.type);
            
            activityItem.innerHTML = `
                <div class="activity-marker bg-${color}">
                    <i class="bi bi-${icon}"></i>
                </div>
                <div class="activity-content">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${activity.action}</h6>
                            <p class="text-muted mb-2">${activity.description}</p>
                            <div class="small text-muted">
                                <i class="bi bi-geo-alt"></i> IP: ${activity.ip_address}
                            </div>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                ${new Date(activity.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}<br>
                                ${new Date(activity.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                            </small>
                        </div>
                    </div>
                </div>
            `;
            
            timeline.appendChild(activityItem);
        });
        
        updateStatistics();
    }
});

// Helper functions (should match PHP functions)
function getActivityColor(type) {
    const colors = {
        'login': 'success',
        'action': 'primary', 
        'error': 'danger',
        'warning': 'warning'
    };
    return colors[type] || 'secondary';
}

function getActivityIcon(type) {
    const icons = {
        'login': 'box-arrow-in-right',
        'action': 'check-circle',
        'error': 'exclamation-triangle',
        'warning': 'exclamation-circle'
    };
    return icons[type] || 'info-circle';
}
</script>

<?php 
// Helper functions for activity colors and icons
function getActivityColor($type) {
    $colors = [
        'login' => 'success',
        'action' => 'primary',
        'error' => 'danger',
        'warning' => 'warning'
    ];
    return $colors[$type] ?? 'secondary';
}

function getActivityIcon($type) {
    $icons = [
        'login' => 'box-arrow-in-right',
        'action' => 'check-circle', 
        'error' => 'exclamation-triangle',
        'warning' => 'exclamation-circle'
    ];
    return $icons[$type] ?? 'info-circle';
}
?>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>