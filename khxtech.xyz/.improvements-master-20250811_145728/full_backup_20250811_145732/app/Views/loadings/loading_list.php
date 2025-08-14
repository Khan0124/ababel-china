<?php
// app/Views/loadings/loading_list.php
include __DIR__ . '/../layouts/header.php';

$db = \App\Core\Database::getInstance();

// Get filter parameters
$filters = [
    'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'client_code' => $_GET['client_code'] ?? '',
    'container_no' => $_GET['container_no'] ?? '',
    'office' => $_GET['office'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// Build query
$sql = "SELECT l.*, c.name as client_name_db, c.name_ar as client_name_ar,
        u.full_name as created_by_name
        FROM loadings l
        LEFT JOIN clients c ON l.client_id = c.id
        LEFT JOIN users u ON l.created_by = u.id
        WHERE 1=1";

$params = [];

if ($filters['date_from']) {
    $sql .= " AND l.shipping_date >= ?";
    $params[] = $filters['date_from'];
}

if ($filters['date_to']) {
    $sql .= " AND l.shipping_date <= ?";
    $params[] = $filters['date_to'];
}

if ($filters['client_code']) {
    $sql .= " AND l.client_code LIKE ?";
    $params[] = '%' . $filters['client_code'] . '%';
}

if ($filters['container_no']) {
    $sql .= " AND l.container_no LIKE ?";
    $params[] = '%' . $filters['container_no'] . '%';
}

if ($filters['office']) {
    $sql .= " AND l.office = ?";
    $params[] = $filters['office'];
}

if ($filters['status']) {
    $sql .= " AND l.status = ?";
    $params[] = $filters['status'];
}

$sql .= " ORDER BY l.shipping_date DESC, l.id DESC";

$stmt = $db->query($sql, $params);
$loadings = $stmt->fetchAll();

// Calculate totals
$totals = [
    'cartons' => 0,
    'purchase' => 0,
    'commission' => 0,
    'total' => 0,
    'shipping' => 0,
    'total_with_shipping' => 0
];

foreach ($loadings as $loading) {
    $totals['cartons'] += $loading['cartons_count'];
    $totals['purchase'] += $loading['purchase_amount'];
    $totals['commission'] += $loading['commission_amount'];
    $totals['total'] += $loading['total_amount'];
    $totals['shipping'] += $loading['shipping_usd'];
    $totals['total_with_shipping'] += $loading['total_with_shipping'];
}

// Office list
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

// Payment methods
$paymentMethods = [
    'cash' => __('payment.cash'),
    'transfer' => __('payment.transfer'),
    'check' => __('payment.check'),
    'credit' => __('payment.credit'),
];
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <i class="bi bi-box-seam"></i> <?= __('loadings.title') ?>
            <span class="badge bg-secondary"><?= count($loadings) ?></span>
        </h2>
        <a href="/loadings/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> <?= __('loadings.add_new') ?>
        </a>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-funnel"></i> <?= __('filter') ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="/loadings">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label for="date_from" class="form-label"><?= __('from') ?></label>
                        <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" 
                               value="<?= $filters['date_from'] ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_to" class="form-label"><?= __('to') ?></label>
                        <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" 
                               value="<?= $filters['date_to'] ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="client_code" class="form-label"><?= __('loadings.client_code') ?></label>
                        <input type="text" class="form-control form-control-sm" id="client_code" name="client_code" 
                               value="<?= $filters['client_code'] ?>" placeholder="<?= __('search') ?>...">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="container_no" class="form-label"><?= __('loadings.container_no') ?></label>
                        <input type="text" class="form-control form-control-sm" id="container_no" name="container_no" 
                               value="<?= $filters['container_no'] ?>" placeholder="<?= __('search') ?>...">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="loading_no" class="form-label"><?= __('loadings.loading_no') ?></label>
                        <input type="text" class="form-control form-control-sm" id="loading_no" name="loading_no" 
                               value="<?= $_GET['loading_no'] ?? '' ?>" placeholder="<?= __('search') ?>...">
                    </div>
                    
                </div>
                
                <div class="row g-3 mt-1">
                    <div class="col-md-2">
                        <label for="office" class="form-label"><?= __('loadings.office') ?></label>
                        <select class="form-select form-select-sm" id="office" name="office">
                            <option value=""><?= __('all') ?></option>
                            <?php foreach ($offices as $key => $office): ?>
                                <option value="<?= $key ?>" <?= $filters['office'] == $key ? 'selected' : '' ?>>
                                    <?= $office ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label"><?= __('status') ?></label>
                        <select class="form-select form-select-sm" id="status" name="status">
                            <option value=""><?= __('all') ?></option>
                            <?php foreach ($statuses as $key => $status): ?>
                                <option value="<?= $key ?>" <?= $filters['status'] == $key ? 'selected' : '' ?>>
                                    <?= $status['label'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-search"></i> <?= __('search') ?>
                        </button>
                        <a href="/loadings" class="btn btn-secondary btn-sm">
                            <i class="bi bi-x-circle"></i> <?= __('reset') ?>
                        </a>
                        <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                            <i class="bi bi-file-earmark-excel"></i> <?= __('export') ?> Excel
                        </button>
                        <button type="button" class="btn btn-info btn-sm" onclick="window.print()">
                            <i class="bi bi-printer"></i> <?= __('print') ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted"><?= __('loadings.total_containers') ?></h6>
                    <h3 class="mb-0"><?= count($loadings) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted"><?= __('loadings.total_cartons') ?></h6>
                    <h3 class="mb-0"><?= number_format($totals['cartons']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted"><?= __('loadings.purchase') ?></h6>
                    <h5 class="mb-0">¥<?= number_format($totals['purchase'], 2) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted"><?= __('loadings.commission') ?></h6>
                    <h5 class="mb-0">¥<?= number_format($totals['commission'], 2) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="card-title text-muted"><?= __('loadings.shipping') ?></h6>
                    <h5 class="mb-0">$<?= number_format($totals['shipping'], 2) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title"><?= __('loadings.grand_total') ?></h6>
                    <h5 class="mb-0">¥<?= number_format($totals['total_with_shipping'], 2) ?></h5>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Data Table -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm" id="loadings-table">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th><?= __('loadings.loading_no') ?></th>
                    <th><?= __('loadings.shipping_date') ?></th>
                    <th><?= __('loadings.container_no') ?></th>
                    <th><?= __('loadings.client_code') ?></th>
                    <th><?= __('loadings.client_name') ?></th>
                    <th><?= __('loadings.cartons') ?></th>
                    <th><?= __('loadings.purchase') ?></th>
                    <th><?= __('loadings.commission') ?></th>
                    <th><?= __('loadings.total') ?></th>
                    <th><?= __('loadings.shipping') ?></th>
                    <th><?= __('loadings.grand_total') ?></th>
                    <th><?= __('status') ?></th>
                    <th><?= __('loadings.office') ?></th>
                    <th class="no-print"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($loadings)): ?>
                    <tr>
                        <td colspan="15" class="text-center py-4">
                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                            <p class="mt-2"><?= __('no_data') ?></p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($loadings as $index => $loading): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td class="text-center">
                                <span class="badge bg-primary"><?= $loading['loading_no'] ?></span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($loading['shipping_date'])) ?></td>
                            <td>
                                <span class="badge bg-secondary"><?= $loading['container_no'] ?></span>
                                <?php if ($loading['claim_number']): ?>
                                    <br><small class="text-muted"><?= $loading['claim_number'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= $loading['client_code'] ?></td>
                            <td>
                                <?= $loading['client_name'] ?? $loading['client_name_db'] ?>
                                <?php if ($loading['item_description']): ?>
                                    <br><small class="text-muted"><?= $loading['item_description'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= number_format($loading['cartons_count']) ?></td>
                            <td class="text-end">¥<?= number_format($loading['purchase_amount'], 2) ?></td>
                            <td class="text-end">¥<?= number_format($loading['commission_amount'], 2) ?></td>
                            <td class="text-end">¥<?= number_format($loading['total_amount'], 2) ?></td>
                            <td class="text-end">$<?= number_format($loading['shipping_usd'], 2) ?></td>
                            <td class="text-end fw-bold">¥<?= number_format($loading['total_with_shipping'], 2) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $statuses[$loading['status']]['class'] ?>">
                                    <?= $statuses[$loading['status']]['label'] ?>
                                </span>
                            </td>
                            <td><?= $loading['office'] ? ($offices[$loading['office']] ?? $loading['office']) : '-' ?></td>
<td class="no-print">
    <div class="btn-group btn-group-sm" role="group">
        <a href="/loadings/show/<?= $loading['id'] ?>" 
           class="btn btn-info" 
           title="<?= __('view') ?>">
            <i class="bi bi-eye"></i>
        </a>
        
        <?php if ($loading['office'] !== 'port_sudan' || $_SESSION['user_role'] === 'admin'): ?>
            <a href="/loadings/edit/<?= $loading['id'] ?>" 
               class="btn btn-warning" 
               title="<?= __('edit') ?>">
                <i class="bi bi-pencil"></i>
            </a>
        <?php endif; ?>
        
        <?php if ($_SESSION['user_role'] === 'admin' && $loading['sync_status'] !== 'synced'): ?>
            <button class="btn btn-danger" 
                    onclick="deleteLoading(<?= $loading['id'] ?>, '<?= htmlspecialchars($loading['container_no']) ?>')"
                    title="<?= __('delete') ?>">
                <i class="bi bi-trash"></i>
            </button>
        <?php endif; ?>
        
        <?php if ($loading['status'] === 'shipped'): ?>
            <button class="btn btn-success" 
                    onclick="updateStatus(<?= $loading['id'] ?>, 'arrived')"
                    title="<?= __('loadings.mark_arrived') ?>">
                <i class="bi bi-check-circle"></i>
            </button>
        <?php endif; ?>
    </div>
</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-secondary fw-bold">
                <tr>
                    <td colspan="6" class="text-end"><?= __('total') ?></td>
                    <td class="text-center"><?= number_format($totals['cartons']) ?></td>
                    <td class="text-end">¥<?= number_format($totals['purchase'], 2) ?></td>
                    <td class="text-end">¥<?= number_format($totals['commission'], 2) ?></td>
                    <td class="text-end">¥<?= number_format($totals['total'], 2) ?></td>
                    <td class="text-end">$<?= number_format($totals['shipping'], 2) ?></td>
                    <td class="text-end">¥<?= number_format($totals['total_with_shipping'], 2) ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<style>
@media print {
    /* Hide everything except the table */
    body * {
        visibility: hidden;
    }
    
    /* Show only the table and its contents */
    #loadings-table, 
    #loadings-table * {
        visibility: visible;
    }
    
    /* Position table at top of page */
    #loadings-table {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    /* Hide action columns */
    .no-print {
        display: none !important;
    }
    
    /* Page setup */
    @page {
        size: landscape;
        margin: 1cm;
    }
    
    /* Table styling for print */
    table {
        font-size: 10px;
        border-collapse: collapse;
        width: 100%;
    }
    
    th, td {
        border: 1px solid #000 !important;
        padding: 4px !important;
    }
    
    thead {
        background-color: #f0f0f0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    tfoot {
        background-color: #e0e0e0 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* Remove Bootstrap badges styling for print */
    .badge {
        border: 1px solid #000 !important;
        padding: 2px 4px !important;
        background: none !important;
        color: #000 !important;
    }
}

.badge {
    font-size: 0.875rem;
}
</style>

<script>
function updateStatus(id, status) {
    if (confirm('<?= __("messages.confirm_status_change") ?>')) {
        fetch(`/loadings/update-status/${id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ status: status })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || '<?= __("messages.operation_failed") ?>');
            }
        });
    }
}

function exportToExcel() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    window.location.href = '/loadings/export?' + params.toString();
}

function deleteLoading(id, containerNo) {
    if (confirm('<?= __('messages.confirm_delete_loading') ?>\n\nContainer: ' + containerNo + '\n\n<?= __('messages.this_action_cannot_be_undone') ?>')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/loadings/delete/' + id;
        document.body.appendChild(form);
        form.submit();
    }
}

function updateStatus(id, status) {
    if (confirm('<?= __('messages.confirm_status_update') ?>')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/loadings/update-status/' + id;
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = status;
        form.appendChild(statusInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function exportToExcel() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    window.location.href = '/loadings/export?' + params.toString();
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>