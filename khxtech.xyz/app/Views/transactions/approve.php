<?php
// app/Views/transactions/approve.php
include __DIR__ . '/../layouts/header.php';
?>

<div class="col-md-12 p-4">
    <div class="card">
        <div class="card-header">
            <h3><?= __('transactions.approve') ?> - <?= __('transactions.transaction_no') ?>: <?= $transaction['transaction_no'] ?></h3>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong><?= __('messages.confirm_approval') ?></strong>
                <p><?= __('messages.approval_warning') ?></p>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5><?= __('transactions.details') ?></h5>
                    <table class="table table-bordered">
                        <tr>
                            <th><?= __('transactions.transaction_no') ?></th>
                            <td><?= $transaction['transaction_no'] ?></td>
                        </tr>
                        <tr>
                            <th><?= __('transactions.transaction_date') ?></th>
                            <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                        </tr>
                        <tr>
                            <th><?= __('clients.name') ?></th>
                            <td><?= $transaction['client_name'] ?? '-' ?></td>
                        </tr>
                        <tr>
                            <th><?= __('transactions.type') ?></th>
                            <td><?= $transaction['type_name'] ?? '-' ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <h5><?= __('transactions.amounts') ?></h5>
                    <table class="table table-bordered">
                        <?php if ($transaction['total_amount_rmb'] > 0): ?>
                        <tr>
                            <th><?= __('transactions.total_amount') ?> (RMB)</th>
                            <td>¥<?= number_format($transaction['total_amount_rmb'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($transaction['payment_rmb'] > 0): ?>
                        <tr>
                            <th><?= __('transactions.payment') ?> (RMB)</th>
                            <td>¥<?= number_format($transaction['payment_rmb'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($transaction['payment_usd'] > 0): ?>
                        <tr>
                            <th><?= __('transactions.payment') ?> (USD)</th>
                            <td>$<?= number_format($transaction['payment_usd'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($transaction['payment_sdg'] > 0): ?>
                        <tr>
                            <th><?= __('transactions.payment') ?> (SDG)</th>
                            <td><?= number_format($transaction['payment_sdg'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <form method="POST" action="/transactions/approve/<?= $transaction['id'] ?>" class="text-center">
                <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('<?= __('messages.confirm_approval_action') ?>')">
                    <i class="bi bi-check-circle"></i> <?= __('transactions.approve') ?>
                </button>
                <a href="/transactions/view/<?= $transaction['id'] ?>" class="btn btn-secondary btn-lg">
                    <i class="bi bi-x-circle"></i> <?= __('cancel') ?>
                </a>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>