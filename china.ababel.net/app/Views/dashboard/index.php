<?php
// app/Views/dashboard/index.php
include __DIR__ . '/../layouts/header.php'; 
?>

<div class="col-md-12 p-4">
    <h1 class="mb-4"><?= __('dashboard.title') ?></h1>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title"><?= __('dashboard.total_clients') ?></h5>
                    <h2 class="card-text"><?= $totalClients ?></h2>
                    <a href="/clients" class="text-white">
                        <?= __('dashboard.view_all') ?> 
                        <i class="bi bi-arrow-<?= isRTL() ? 'left' : 'right' ?>"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title"><?= __('dashboard.cashbox_balance') ?> (RMB)</h5>
                    <h2 class="card-text">¥<?= number_format($cashboxBalance['balance_rmb'] ?? 0, 2) ?></h2>
                    <a href="/cashbox" class="text-white">
                        <?= __('dashboard.view_all') ?> 
                        <i class="bi bi-arrow-<?= isRTL() ? 'left' : 'right' ?>"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5 class="card-title"><?= __('dashboard.cashbox_balance') ?> (USD)</h5>
                    <h2 class="card-text">$<?= number_format($cashboxBalance['balance_usd'] ?? 0, 2) ?></h2>
                    <a href="/cashbox" class="text-white">
                        <?= __('dashboard.view_all') ?> 
                        <i class="bi bi-arrow-<?= isRTL() ? 'left' : 'right' ?>"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5 class="card-title"><?= __('dashboard.today_transactions') ?></h5>
                    <h2 class="card-text"><?= count($todaySummary) ?></h2>
                    <a href="/transactions?date=today" class="text-white">
                        <?= __('dashboard.view_all') ?> 
                        <i class="bi bi-arrow-<?= isRTL() ? 'left' : 'right' ?>"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?= __('dashboard.recent_transactions') ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><?= __('transactions.transaction_no') ?></th>
                                    <th><?= __('date') ?></th>
                                    <th><?= __('transactions.client') ?></th>
                                    <th><?= __('transactions.type') ?></th>
                                    <th><?= __('amount') ?> (RMB)</th>
                                    <th><?= __('status') ?></th>
                                    <th><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransactions as $transaction): ?>
                                <tr>
                                    <td><?= $transaction['transaction_no'] ?></td>
                                    <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                                    <td><?= $transaction['client_name'] ?? '-' ?></td>
                                    <td><?= $transaction['transaction_type_name'] ?></td>
                                    <td>¥<?= number_format($transaction['total_amount_rmb'], 2) ?></td>
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
                                        <a href="/transactions/view/<?= $transaction['id'] ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="<?= __('view') ?>">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="/transactions" class="btn btn-primary">
                            <?= __('transactions.title') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Clients -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?= __('dashboard.top_clients') ?></h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php 
                        $topClients = array_slice($topClients, 0, 5);
                        foreach ($topClients as $client): 
                            if ($client['current_balance_rmb'] > 0):
                        ?>
                        <a href="/clients/statement/<?= $client['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">
                                    <?= lang() == 'ar' ? ($client['name_ar'] ?? $client['name']) : $client['name'] ?>
                                </h6>
                                <small>¥<?= number_format($client['current_balance_rmb'], 2) ?></small>
                            </div>
                            <p class="mb-1 small">
                                <?= __('clients.transaction_count') ?>: <?= $client['transaction_count'] ?>
                            </p>
                        </a>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="/clients" class="btn btn-primary">
                            <?= __('clients.title') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?= __('dashboard.quick_actions') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="/clients/create" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-person-plus"></i><br>
                                <?= __('dashboard.add_client') ?>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/transactions/create" class="btn btn-success btn-lg w-100">
                                <i class="bi bi-plus-circle"></i><br>
                                <?= __('dashboard.create_transaction') ?>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/cashbox/movement" class="btn btn-info btn-lg w-100">
                                <i class="bi bi-cash"></i><br>
                                <?= __('dashboard.cashbox_movement') ?>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/reports/daily" class="btn btn-warning btn-lg w-100">
                                <i class="bi bi-file-earmark-pdf"></i><br>
                                <?= __('dashboard.daily_report') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>