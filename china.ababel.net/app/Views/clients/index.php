<?php
// app/Views/clients/index.php
include __DIR__ . '/../layouts/header.php';
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= __('clients.title') ?></h1>
        <a href="/clients/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> <?= __('clients.add_new') ?>
        </a>
    </div>
    
    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" 
                               class="form-control" 
                               id="client-search" 
                               placeholder="<?= __('search') ?>...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="status-filter">
                        <option value=""><?= __('all') ?></option>
                        <option value="active"><?= __('active') ?></option>
                        <option value="inactive"><?= __('inactive') ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-success w-100" onclick="exportToExcel('clients-table', 'clients')">
                        <i class="bi bi-file-excel"></i> <?= __('export') ?> Excel
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Clients Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($clients)): ?>
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> <?= __('clients.no_clients') ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="clients-table">
                        <thead>
                            <tr>
                                <th><?= __('clients.client_code') ?></th>
                                <th><?= __('clients.name') ?></th>
                                <th><?= __('clients.phone') ?></th>
                                <th><?= __('clients.current_balance') ?> (RMB)</th>
                                <th><?= __('clients.current_balance') ?> (USD)</th>
                                <th><?= __('clients.transaction_count') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                            <tr>
                                <td class="client-code"><?= htmlspecialchars($client['client_code']) ?></td>
                                <td class="client-name">
                                    <?php if (lang() == 'ar'): ?>
                                        <?= htmlspecialchars($client['name_ar'] ?? $client['name']) ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($client['name']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($client['phone']) ?></td>
                                <td class="text-<?= $client['balance_rmb'] >= 0 ? 'success' : 'danger' ?>">
                                    ¥<?= number_format($client['balance_rmb'], 2) ?>
                                </td>
                                <td class="text-<?= $client['balance_usd'] >= 0 ? 'success' : 'danger' ?>">
                                    $<?= number_format($client['balance_usd'], 2) ?>
                                </td>
                                <td><?= $client['transaction_count'] ?? 0 ?></td>
                                <td>
                                    <?php if ($client['status'] == 'active'): ?>
                                        <span class="badge bg-success"><?= __('active') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= __('inactive') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="/clients/statement/<?= $client['id'] ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="<?= __('clients.statement') ?>">
                                            <i class="bi bi-file-text"></i>
                                        </a>
                                        <a href="/clients/edit/<?= $client['id'] ?>" 
                                           class="btn btn-sm btn-warning" 
                                           title="<?= __('edit') ?>">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?= $client['id'] ?>, '<?= htmlspecialchars($client['name']) ?>')" 
                                                class="btn btn-sm btn-danger" 
                                                title="<?= __('delete') ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
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

<script>
function confirmDelete(id, name) {
    if (confirm('<?= __('clients.confirm_delete') ?>\n' + name)) {
        window.location.href = '/clients/delete/' + id;
    }
}

// Client search functionality
document.getElementById('client-search').addEventListener('input', function() {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll('#clients-table tbody tr');
    
    rows.forEach(row => {
        const name = row.querySelector('.client-name').textContent.toLowerCase();
        const code = row.querySelector('.client-code').textContent.toLowerCase();
        
        if (name.includes(searchValue) || code.includes(searchValue)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Status filter
document.getElementById('status-filter').addEventListener('change', function() {
    const filterValue = this.value;
    const rows = document.querySelectorAll('#clients-table tbody tr');
    
    rows.forEach(row => {
        const badge = row.querySelector('.badge');
        const isActive = badge.classList.contains('bg-success');
        
        if (filterValue === '' || 
            (filterValue === 'active' && isActive) || 
            (filterValue === 'inactive' && !isActive)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>