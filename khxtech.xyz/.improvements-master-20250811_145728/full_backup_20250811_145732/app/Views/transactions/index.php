<?php
// app/Views/transactions/index.php
include __DIR__ . '/../layouts/header.php';
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= __('transactions.title') ?></h1>
        <div>
            <a href="/transactions/create" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> <?= __('transactions.add_new') ?>
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
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="/transactions" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label"><?= __('from') ?></label>
                    <input type="date" name="date_from" class="form-control" 
                           value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= __('to') ?></label>
                    <input type="date" name="date_to" class="form-control" 
                           value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('clients.client') ?></label>
                    <select name="client_id" class="form-select">
                        <option value=""><?= __('all') ?></option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" 
                                    <?= $filters['client_id'] == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['client_code']) ?> - 
                                <?= lang() == 'ar' ? ($client['name_ar'] ?? $client['name']) : $client['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= __('loadings.claim_number') ?></label>
                    <input type="text" name="claim_number" class="form-control" 
                           placeholder="<?= __('search') ?>..." 
                           value="<?= htmlspecialchars($filters['claim_number'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= __('status') ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?= __('all') ?></option>
                        <option value="pending" <?= $filters['status'] == 'pending' ? 'selected' : '' ?>>
                            <?= __('transactions.pending') ?>
                        </option>
                        <option value="approved" <?= $filters['status'] == 'approved' ? 'selected' : '' ?>>
                            <?= __('transactions.approved') ?>
                        </option>
                        <option value="cancelled" <?= $filters['status'] == 'cancelled' ? 'selected' : '' ?>>
                            <?= __('transactions.cancelled') ?>
                        </option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="bi bi-search"></i> <?= __('search') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Quick Claim Search -->
    <div class="card mb-4" id="claimSearchCard" style="<?= empty($filters['claim_number']) ? 'display:none;' : '' ?>">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-file-text"></i> 
                <?= __('loadings.claim_number') ?>: <span id="claimNumberDisplay"><?= htmlspecialchars($filters['claim_number'] ?? '') ?></span>
            </h5>
        </div>
        <div class="card-body">
            <div id="claimDetails">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Transactions Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><?= __('transactions.transaction_no') ?></th>
                            <th><?= __('date') ?></th>
                            <th><?= __('clients.client') ?></th>
                            <th><?= __('transactions.type') ?></th>
                            <th><?= __('transactions.total_amount') ?></th>
                            <th><?= __('transactions.payment') ?></th>
                            <th><?= __('balance') ?></th>
                            <th><?= __('status') ?></th>
                            <th><?= __('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td>
                                <a href="/transactions/view/<?= $transaction['id'] ?>">
                                    <?= htmlspecialchars($transaction['transaction_no']) ?>
                                </a>
                            </td>
                            <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                            <td>
                                <?php if ($transaction['client_id']): ?>
                                    <?= htmlspecialchars($transaction['client_code']) ?> - 
                                    <?= lang() == 'ar' ? ($transaction['client_name_ar'] ?? $transaction['client_name']) : $transaction['client_name'] ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($transaction['transaction_type_name']) ?></td>
                            <td>
                                <?php if ($transaction['total_amount_rmb'] > 0): ?>
                                    <div>¥<?= number_format($transaction['total_amount_rmb'], 2) ?></div>
                                <?php endif; ?>
                                <?php if ($transaction['shipping_usd'] > 0): ?>
                                    <div class="text-muted small">$<?= number_format($transaction['shipping_usd'], 2) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $payments = [];
                                if ($transaction['payment_rmb'] > 0) $payments[] = '¥' . number_format($transaction['payment_rmb'], 2);
                                if ($transaction['payment_usd'] > 0) $payments[] = '$' . number_format($transaction['payment_usd'], 2);
                                if ($transaction['payment_sdg'] > 0) $payments[] = 'SDG ' . number_format($transaction['payment_sdg'], 2);
                                if ($transaction['payment_aed'] > 0) $payments[] = 'AED ' . number_format($transaction['payment_aed'], 2);
                                echo !empty($payments) ? implode('<br>', $payments) : '-';
                                ?>
                            </td>
                            <td>
                                <?php 
                                $balances = [];
                                if ($transaction['balance_rmb'] > 0) {
                                    $balances[] = '<span class="text-danger">¥' . number_format($transaction['balance_rmb'], 2) . '</span>';
                                } elseif ($transaction['balance_rmb'] < 0) {
                                    $balances[] = '<span class="text-success">¥' . number_format(abs($transaction['balance_rmb']), 2) . ' (فائض)</span>';
                                }
                                
                                if ($transaction['balance_usd'] > 0) {
                                    $balances[] = '<span class="text-danger">$' . number_format($transaction['balance_usd'], 2) . '</span>';
                                } elseif ($transaction['balance_usd'] < 0) {
                                    $balances[] = '<span class="text-success">$' . number_format(abs($transaction['balance_usd']), 2) . ' (فائض)</span>';
                                }
                                
                                if ($transaction['balance_sdg'] > 0) {
                                    $balances[] = '<span class="text-danger">SDG ' . number_format($transaction['balance_sdg'], 2) . '</span>';
                                } elseif ($transaction['balance_sdg'] < 0) {
                                    $balances[] = '<span class="text-success">SDG ' . number_format(abs($transaction['balance_sdg']), 2) . ' (فائض)</span>';
                                }
                                
                                if ($transaction['balance_aed'] > 0) {
                                    $balances[] = '<span class="text-danger">AED ' . number_format($transaction['balance_aed'], 2) . '</span>';
                                } elseif ($transaction['balance_aed'] < 0) {
                                    $balances[] = '<span class="text-success">AED ' . number_format(abs($transaction['balance_aed']), 2) . ' (فائض)</span>';
                                }
                                
                                echo !empty($balances) ? implode('<br>', $balances) : '<span class="text-muted">مدفوع بالكامل</span>';
                                ?>
                            </td>
                            <td>
                                <?php if ($transaction['status'] == 'approved'): ?>
                                    <span class="badge bg-success"><?= __('transactions.approved') ?></span>
                                <?php elseif ($transaction['status'] == 'pending'): ?>
                                    <span class="badge bg-warning"><?= __('transactions.pending') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><?= __('transactions.cancelled') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="/transactions/view/<?= $transaction['id'] ?>" 
                                       class="btn btn-info" title="<?= __('view') ?>">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($transaction['status'] == 'pending' && ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'accountant')): ?>
                                        <a href="/transactions/approve/<?= $transaction['id'] ?>" 
                                           class="btn btn-success" title="<?= __('transactions.approve') ?>">
                                            <i class="bi bi-check-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($transaction['balance_rmb'] > 0 || $transaction['balance_usd'] > 0 || 
                                             $transaction['balance_sdg'] > 0 || $transaction['balance_aed'] > 0): ?>
                                        <a href="/transactions/partial-payment/<?= $transaction['id'] ?>" 
                                           class="btn btn-warning" 
                                           title="<?= __('transactions.make_payment') ?>">
                                            <i class="bi bi-cash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal (Removed - using partial payment page instead) -->

<script>
// Search by claim number
document.addEventListener('DOMContentLoaded', function() {
    const claimNumber = '<?= htmlspecialchars($filters['claim_number'] ?? '') ?>';
    if (claimNumber) {
        searchClaim(claimNumber);
    }
});

function searchClaim(claimNumber) {
    if (!claimNumber) return;
    
    document.getElementById('claimSearchCard').style.display = 'block';
    document.getElementById('claimNumberDisplay').textContent = claimNumber;
    
    fetch(`/transactions/search-by-claim?claim_number=${encodeURIComponent(claimNumber)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayClaimDetails(data);
            } else {
                document.getElementById('claimDetails').innerHTML = 
                    `<div class="alert alert-warning">${data.message}</div>`;
            }
        });
}

function displayClaimDetails(data) {
    const loading = data.loading;
    const balances = data.balances;
    
    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6><?= __('loadings.loading_details') ?></h6>
                <table class="table table-sm">
                    <tr>
                        <th><?= __('loadings.loading_no') ?>:</th>
                        <td>${loading.loading_no}</td>
                    </tr>
                    <tr>
                        <th><?= __('loadings.container_no') ?>:</th>
                        <td>${loading.container_no}</td>
                    </tr>
                    <tr>
                        <th><?= __('clients.client') ?>:</th>
                        <td>${loading.client_code} - ${loading.client_name}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6><?= __('transactions.outstanding_balances') ?></h6>
                <table class="table table-sm">
                    ${balances.RMB > 0 ? `<tr><th>RMB:</th><td class="text-danger">¥${balances.RMB.toFixed(2)}</td></tr>` : ''}
                    ${balances.USD > 0 ? `<tr><th>USD:</th><td class="text-danger">$${balances.USD.toFixed(2)}</td></tr>` : ''}
                    ${balances.SDG > 0 ? `<tr><th>SDG:</th><td class="text-danger">${balances.SDG.toFixed(2)}</td></tr>` : ''}
                    ${balances.AED > 0 ? `<tr><th>AED:</th><td class="text-danger">${balances.AED.toFixed(2)}</td></tr>` : ''}
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('claimDetails').innerHTML = html;
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>