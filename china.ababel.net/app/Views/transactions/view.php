<?php
// app/Views/transactions/view.php
include __DIR__ . '/../layouts/header.php';

// Get company info for receipt
$db = \App\Core\Database::getInstance();
$stmt = $db->query("SELECT * FROM settings WHERE setting_key IN ('company_name', 'company_phone')");
$settingsRaw = $stmt->fetchAll();
$company = [];
foreach ($settingsRaw as $setting) {
    $company[str_replace('company_', '', $setting['setting_key'])] = $setting['setting_value'];
}

// Get client info if transaction has client
$client = null;
if ($transaction['client_id']) {
    $clientModel = new \App\Models\Client();
    $client = $clientModel->find($transaction['client_id']);
}
?>

<div class="col-md-12 p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h1><?= __('transactions.transaction_no') ?>: <?= $transaction['transaction_no'] ?></h1>
        <div>
            <?php if ($client && $client['phone']): ?>
            <button class="btn btn-success" onclick="shareWhatsApp()">
                <i class="bi bi-whatsapp"></i> <?= __('share') ?> WhatsApp
            </button>
            <?php endif; ?>
            
            <button class="btn btn-danger" onclick="window.print()">
                <i class="bi bi-printer"></i> <?= __('print') ?>
            </button>
            
            <a href="/transactions" class="btn btn-secondary">
                <i class="bi bi-arrow-<?= isRTL() ? 'right' : 'left' ?>"></i> <?= __('back') ?>
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Transaction Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><?= __('transactions.title') ?> <?= __('transactions.description') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><?= __('transactions.transaction_no') ?>:</strong>
                            <?= $transaction['transaction_no'] ?>
                        </div>
                        <div class="col-md-6">
                            <strong><?= __('date') ?>:</strong>
                            <?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><?= __('transactions.type') ?>:</strong>
                            <?= $transaction['transaction_type_name'] ?>
                        </div>
                        <div class="col-md-6">
                            <strong><?= __('status') ?>:</strong>
                            <?php if ($transaction['status'] == 'approved'): ?>
                                <span class="badge bg-success"><?= __('transactions.approved') ?></span>
                            <?php elseif ($transaction['status'] == 'pending'): ?>
                                <span class="badge bg-warning"><?= __('transactions.pending') ?></span>
                            <?php else: ?>
                                <span class="badge bg-danger"><?= __('transactions.cancelled') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($transaction['invoice_no']): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><?= __('transactions.invoice_no') ?>:</strong>
                            <?= $transaction['invoice_no'] ?>
                        </div>
                        <?php if ($transaction['loading_no']): ?>
                        <div class="col-md-6">
                            <strong><?= __('transactions.loading_no') ?>:</strong>
                            <?= $transaction['loading_no'] ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($transaction['description']): ?>
                    <div class="mb-3">
                        <strong><?= __('transactions.description') ?>:</strong>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($transaction['description'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Financial Details -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?= __('amount') ?></h5>
                </div>
                <div class="card-body">
                    <!-- RMB Section -->
                    <?php if ($transaction['total_amount_rmb'] > 0): ?>
                    <h6 class="text-muted">RMB</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm">
                            <tr>
                                <td><?= __('transactions.goods_amount') ?></td>
                                <td class="text-end">¥<?= number_format($transaction['goods_amount_rmb'], 2) ?></td>
                            </tr>
                            <tr>
                                <td><?= __('transactions.commission') ?></td>
                                <td class="text-end">¥<?= number_format($transaction['commission_rmb'], 2) ?></td>
                            </tr>
                            <tr class="table-secondary">
                                <th><?= __('total') ?></th>
                                <th class="text-end">¥<?= number_format($transaction['total_amount_rmb'], 2) ?></th>
                            </tr>
                            <tr>
                                <td><?= __('transactions.payment') ?></td>
                                <td class="text-end text-success">¥<?= number_format($transaction['payment_rmb'], 2) ?></td>
                            </tr>
                            <tr class="table-primary">
                                <th><?= __('balance') ?></th>
                                <th class="text-end">¥<?= number_format($transaction['balance_rmb'], 2) ?></th>
                            </tr>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- USD Section -->
                    <?php if ($transaction['shipping_usd'] > 0): ?>
                    <h6 class="text-muted">USD</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <tr>
                                <td><?= __('transactions.shipping') ?></td>
                                <td class="text-end">$<?= number_format($transaction['shipping_usd'], 2) ?></td>
                            </tr>
                            <tr>
                                <td><?= __('transactions.payment') ?></td>
                                <td class="text-end text-success">$<?= number_format($transaction['payment_usd'], 2) ?></td>
                            </tr>
                            <tr class="table-primary">
                                <th><?= __('balance') ?></th>
                                <th class="text-end">$<?= number_format($transaction['balance_usd'], 2) ?></th>
                            </tr>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- SDG Payment -->
                    <?php if ($transaction['payment_sdg'] > 0): ?>
                    <div class="alert alert-info">
                        <strong>SDG <?= __('transactions.payment') ?>:</strong> 
                        <?= number_format($transaction['payment_sdg'], 2) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Client Info -->
        <div class="col-md-4">
            <?php if ($client): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><?= __('transactions.client') ?></h5>
                </div>
                <div class="card-body">
                    <p><strong><?= __('clients.name') ?>:</strong><br>
                        <?= lang() == 'ar' ? ($client['name_ar'] ?? $client['name']) : $client['name'] ?>
                    </p>
                    <p><strong><?= __('clients.client_code') ?>:</strong> <?= $client['client_code'] ?></p>
                    <p><strong><?= __('clients.phone') ?>:</strong> <?= $client['phone'] ?></p>
                    <?php if ($client['email']): ?>
                    <p><strong><?= __('clients.email') ?>:</strong> <?= $client['email'] ?></p>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <a href="/clients/statement/<?= $client['id'] ?>" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-file-text"></i> <?= __('clients.statement') ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Metadata -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?= __('info') ?></h5>
                </div>
                <div class="card-body">
                    <p class="small mb-1">
                        <strong><?= __('created_by') ?>:</strong> 
                        <?= $transaction['created_by_name'] ?? '-' ?>
                    </p>
                    <p class="small mb-1">
                        <strong><?= __('created_at') ?>:</strong> 
                        <?= date('Y-m-d H:i', strtotime($transaction['created_at'])) ?>
                    </p>
                    <?php if ($transaction['approved_by']): ?>
                    <p class="small mb-0">
                        <strong><?= __('approved_by') ?>:</strong> 
                        <?= $transaction['approved_by_name'] ?? '-' ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Receipt Template -->
<div class="d-none d-print-block text-center">
    <img src="/assets/images/logo.png" alt="Logo" style="max-height: 80px;" class="mb-3">
    <h2><?= $company['name'] ?? __('company_name') ?></h2>
    <p><?= $company['phone'] ?? '' ?></p>
    <hr>
</div>

<script>
function shareWhatsApp() {
    <?php if ($client && $client['phone']): 
        // Prepare WhatsApp message
        $message = \App\Core\WhatsApp::formatReceiptMessage($transaction, $client, $company);
        $whatsappUrl = \App\Core\WhatsApp::sendReceipt($client['phone'], $message);
    ?>
    window.open('<?= $whatsappUrl ?>', '_blank');
    <?php endif; ?>
}
</script>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
    }
    
    body {
        font-size: 12px;
    }
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>