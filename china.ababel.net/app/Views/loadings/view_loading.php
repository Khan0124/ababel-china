<?php
// app/Views/loadings/view_loading.php
include __DIR__ . '/../layouts/header.php';

// Office list - based on actual enum values in database
$offices = [
    'port_sudan' => __('offices.port_sudan'),
    'uae' => __('offices.uae'),
    'tanzania' => __('offices.tanzania'),
    'egypt' => __('offices.egypt'),
];

// Status list
$statuses = [
    'pending' => ['label' => __('loadings.status.pending'), 'class' => 'warning'],
    'shipped' => ['label' => __('loadings.status.shipped'), 'class' => 'info'],
    'arrived' => ['label' => __('loadings.status.arrived'), 'class' => 'primary'],
    'cleared' => ['label' => __('loadings.status.cleared'), 'class' => 'success'],
    'cancelled' => ['label' => __('loadings.status.cancelled'), 'class' => 'danger'],
];

// Check if user can edit (not from Port Sudan)
$canEdit = ($loading['office'] !== 'port_sudan' || $_SESSION['user_role'] === 'admin');

// Get exchange rate for display
$db = \App\Core\Database::getInstance();
$stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'exchange_rate_usd_rmb'");
$rate = $stmt->fetch();
$usdToRmb = $rate ? floatval($rate['setting_value']) : 200;
$shippingRmb = $loading['shipping_usd'] * $usdToRmb;
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2><?= __('loadings.view_details') ?></h2>
        <div class="btn-group">
            <!-- Port Sudan read-only notice -->
            <?php if ($loading['office'] === 'port_sudan' && !$canEdit): ?>
                <span class="badge bg-info me-3">
                    <i class="bi bi-lock"></i> <?= __('loadings.port_sudan_readonly') ?>
                </span>
            <?php endif; ?>
            
            <!-- Issue Bill of Lading button (moved here from edit page) -->
            <?php if (!$loading['bol_number'] && $canEdit): ?>
                <button class="btn btn-primary" onclick="issueBillOfLading()">
                    <i class="bi bi-file-earmark-text"></i> <?= __('loadings.issue_bol') ?>
                </button>
            <?php elseif ($loading['bol_number']): ?>
                <span class="btn btn-success disabled">
                    <i class="bi bi-check-circle"></i> BOL: <?= htmlspecialchars($loading['bol_number']) ?>
                </span>
            <?php endif; ?>
            
            <!-- Mark as Shipped button (now active) -->
            <?php if ($loading['status'] === 'pending' && $canEdit): ?>
                <button class="btn btn-success" onclick="updateStatus('shipped')">
                    <i class="bi bi-truck"></i> <?= __('loadings.mark_as_shipped') ?>
                </button>
            <?php endif; ?>
            
            <!-- Edit button -->
            <?php if ($canEdit): ?>
                <a href="/loadings/edit/<?= $loading['id'] ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> <?= __('edit') ?>
                </a>
            <?php endif; ?>
            
            <!-- Delete button (admin only) -->
            <?php if ($_SESSION['user_role'] === 'admin' && $loading['sync_status'] !== 'synced'): ?>
                <button class="btn btn-danger" onclick="deleteLoading()">
                    <i class="bi bi-trash"></i> <?= __('delete') ?>
                </button>
            <?php endif; ?>
            
            <!-- Print button -->
            <button class="btn btn-info" onclick="window.print()">
                <i class="bi bi-printer"></i> <?= __('print') ?>
            </button>
            
            <!-- Back button -->
            <a href="/loadings" class="btn btn-secondary">
                <i class="bi bi-arrow-<?= isRTL() ? 'right' : 'left' ?>"></i> <?= __('back') ?>
            </a>
        </div>
    </div>
    
    <!-- Success/Error Messages -->
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
    
    <div class="row">
        <!-- Main Details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <?= __('loadings.container_no') ?>: <?= $loading['container_no'] ?>
                        <span class="badge bg-<?= $statuses[$loading['status']]['class'] ?> float-end">
                            <?= $statuses[$loading['status']]['label'] ?>
                        </span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><?= __('loadings.shipping_date') ?>:</strong>
                            <?= date('d/m/Y', strtotime($loading['shipping_date'])) ?>
                        </div>
                        <div class="col-md-6">
                            <strong><?= __('loadings.arrival_date') ?>:</strong>
                            <?= $loading['arrival_date'] ? date('d/m/Y', strtotime($loading['arrival_date'])) : '-' ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><?= __('loadings.claim_number') ?>:</strong>
                            <?= $loading['claim_number'] ?: '-' ?>
                            <?php if ($loading['claim_number']): ?>
                                <a href="/transactions?claim_number=<?= urlencode($loading['claim_number']) ?>" 
                                   class="btn btn-sm btn-link">
                                    <i class="bi bi-search"></i> <?= __('transactions.view_transactions') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <strong><?= __('loadings.office') ?>:</strong>
                            <span class="badge bg-<?= $loading['office'] === 'port_sudan' ? 'primary' : 'secondary' ?>">
                                <?= $loading['office'] ? ($offices[$loading['office']] ?? $loading['office']) : '-' ?>
                            </span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6><?= __('loadings.client_info') ?></h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong><?= __('loadings.client_code') ?>:</strong>
                            <?= $loading['client_code'] ?>
                        </div>
                        <div class="col-md-8">
                            <strong><?= __('loadings.client_name') ?>:</strong>
                            <?= $loading['client_name'] ?? $loading['client_name_db'] ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong><?= __('loadings.cartons_count') ?>:</strong>
                            <?= number_format($loading['cartons_count']) ?>
                        </div>
                        <div class="col-md-8">
                            <strong><?= __('loadings.item_description') ?>:</strong>
                            <?= $loading['item_description'] ?: '-' ?>
                        </div>
                    </div>
                    
                    <?php if ($loading['notes']): ?>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <strong><?= __('notes') ?>:</strong>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($loading['notes'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sync Status (if applicable) -->
            <?php if ($loading['office'] === 'port_sudan'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><?= __('loadings.sync_status') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong><?= __('loadings.sync_status') ?>:</strong>
                            <?php
                            $syncClass = [
                                'pending' => 'warning',
                                'synced' => 'success',
                                'failed' => 'danger'
                            ][$loading['sync_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $syncClass ?>">
                                <?= __('loadings.sync_' . $loading['sync_status']) ?>
                            </span>
                        </div>
                        <?php if ($loading['sync_attempts'] > 0): ?>
                        <div class="col-md-4">
                            <strong><?= __('loadings.sync_attempts') ?>:</strong>
                            <?= $loading['sync_attempts'] ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($loading['last_sync_at']): ?>
                        <div class="col-md-4">
                            <strong><?= __('loadings.last_sync') ?>:</strong>
                            <?= date('Y-m-d H:i', strtotime($loading['last_sync_at'])) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($loading['port_sudan_id']): ?>
                        <div class="col-md-4 mt-2">
                            <strong><?= __('loadings.port_sudan_id') ?>:</strong>
                            <span class="badge bg-info">#<?= $loading['port_sudan_id'] ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Bill of Lading Info -->
            <?php if ($loading['bol_number']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><?= __('loadings.bol_info') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong><?= __('loadings.bol_number') ?>:</strong>
                            <?= htmlspecialchars($loading['bol_number']) ?>
                        </div>
                        <div class="col-md-4">
                            <strong><?= __('loadings.bol_issued_date') ?>:</strong>
                            <?= date('Y-m-d', strtotime($loading['bol_issued_date'])) ?>
                        </div>
                        <div class="col-md-4">
                            <strong><?= __('loadings.bol_issued_by') ?>:</strong>
                            <?php
                            // Get issuer name
                            if ($loading['bol_issued_by']) {
                                $stmt = $db->query("SELECT full_name FROM users WHERE id = ?", [$loading['bol_issued_by']]);
                                $issuer = $stmt->fetch();
                                echo htmlspecialchars($issuer['full_name'] ?? '-');
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Financial Summary -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= __('loadings.financial_details') ?></h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><?= __('loadings.purchase') ?></td>
                            <td class="text-end">¥<?= number_format($loading['purchase_amount'], 2) ?></td>
                        </tr>
                        <tr>
                            <td><?= __('loadings.commission') ?></td>
                            <td class="text-end">¥<?= number_format($loading['commission_amount'], 2) ?></td>
                        </tr>
                        <tr class="border-top">
                            <th><?= __('loadings.total') ?></th>
                            <th class="text-end">¥<?= number_format($loading['total_amount'], 2) ?></th>
                        </tr>
                        <tr>
                            <td><?= __('loadings.shipping') ?> (USD)</td>
                            <td class="text-end">$<?= number_format($loading['shipping_usd'], 2) ?></td>
                        </tr>
                        <tr>
                            <td><?= __('loadings.shipping') ?> (RMB)</td>
                            <td class="text-end">¥<?= number_format($shippingRmb, 2) ?></td>
                        </tr>
                        <tr class="border-top border-bottom">
                            <th><?= __('loadings.grand_total') ?></th>
                            <th class="text-end text-primary">¥<?= number_format($loading['total_with_shipping'], 2) ?></th>
                        </tr>
                    </table>
                    
                    <small class="text-muted">
                        <?= __('settings.exchange_rates') ?>: 1 USD = <?= $usdToRmb ?> RMB
                    </small>
                </div>
            </div>
            
            <!-- Metadata -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><?= __('info') ?></h6>
                </div>
                <div class="card-body">
                    <small>
                        <p class="mb-1">
                            <strong><?= __('created_by') ?>:</strong><br>
                            <?php
                            if ($loading['created_by']) {
                                $stmt = $db->query("SELECT full_name FROM users WHERE id = ?", [$loading['created_by']]);
                                $creator = $stmt->fetch();
                                echo htmlspecialchars($creator['full_name'] ?? '-');
                            } else {
                                echo '-';
                            }
                            ?>
                        </p>
                        <p class="mb-1">
                            <strong><?= __('created_at') ?>:</strong><br>
                            <?= date('Y-m-d H:i', strtotime($loading['created_at'])) ?>
                        </p>
                        <?php if ($loading['updated_by']): ?>
                        <p class="mb-0">
                            <strong><?= __('updated_by') ?>:</strong><br>
                            <?php
                            $stmt = $db->query("SELECT full_name FROM users WHERE id = ?", [$loading['updated_by']]);
                            $updater = $stmt->fetch();
                            echo htmlspecialchars($updater['full_name'] ?? '-');
                            ?>
                            <br>
                            <?= date('Y-m-d H:i', strtotime($loading['updated_at'])) ?>
                        </p>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for actions -->
<script>
function updateStatus(newStatus) {
    if (confirm('<?= __('messages.confirm_status_update') ?>')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/loadings/update-status/<?= $loading['id'] ?>';
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = newStatus;
        form.appendChild(statusInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function issueBillOfLading() {
    if (confirm('<?= __('messages.confirm_issue_bol') ?>')) {
        window.location.href = '/loadings/issue-bol/<?= $loading['id'] ?>';
    }
}

function deleteLoading() {
    if (confirm('<?= __('messages.confirm_delete_loading') ?>\n\n<?= __('messages.this_action_cannot_be_undone') ?>')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/loadings/delete/<?= $loading['id'] ?>';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>