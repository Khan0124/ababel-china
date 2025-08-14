<?php include __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('transactions.approve') ?> - <?= $transaction['transaction_no'] ?></h3>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?= __('messages.approval_warning') ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?= __('transactions.details') ?></h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th><?= __('transactions.transaction_no') ?></th>
                                    <td><?= $transaction['transaction_no'] ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('transactions.transaction_date') ?></th>
                                    <td><?= date('d/m/Y', strtotime($transaction['transaction_date'])) ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('clients.client') ?></th>
                                    <td><?= $transaction['client_name'] ?></td>
                                </tr>
                                <tr>
                                    <th><?= __('transactions.description') ?></th>
                                    <td><?= $transaction['description'] ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h5><?= __('transactions.financial_details') ?></h5>
                            <table class="table table-bordered">
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
                    
                    <div class="text-center mt-4">
                        <form method="POST" action="/transactions/approve/<?= $transaction['id'] ?>" style="display: inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-success btn-lg" 
                                    onclick="return confirm('<?= __('messages.confirm_approval_action') ?>')">
                                <i class="bi bi-check-circle"></i> <?= __('transactions.approve') ?>
                            </button>
                        </form>
                        <a href="/transactions" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-circle"></i> <?= __('cancel') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>