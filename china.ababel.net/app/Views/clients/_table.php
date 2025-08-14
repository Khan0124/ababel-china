<div class="table-responsive">
    <table class="table table-hover table-striped" id="clients-table">
        <thead>
            <tr>
                <th><?= __('clients.code') ?></th>
                <th><?= __('clients.name') ?></th>
                <th><?= __('clients.phone') ?></th>
                <th><?= __('clients.balance') ?></th>
                <th><?= __('common.transactions') ?></th>
                <th><?= __('common.status') ?></th>
                <th><?= __('common.actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clients)): ?>
                <tr>
                    <td colspan="7" class="text-center"><?= __('common.no_data') ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td class="client-code"><?= h($client['client_code'] ?? '') ?></td>
                        <td class="client-name">
                            <?= h($client['name'] ?? '') ?>
                            <?php if ($client['name_ar']): ?>
                                <br><small class="text-muted"><?= h($client['name_ar'] ?? '') ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($client['phone'] ?? '') ?></td>
                        <td>
                            <?php if ($client['balance_rmb'] != 0): ?>
                                <div class="text-<?= $client['balance_rmb'] > 0 ? 'danger' : 'success' ?>">
                                    ¥ <?= number_format($client['balance_rmb'], 2) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($client['balance_usd'] != 0): ?>
                                <div class="text-<?= $client['balance_usd'] > 0 ? 'danger' : 'success' ?>">
                                    $ <?= number_format($client['balance_usd'], 2) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($client['balance_aed'] != 0): ?>
                                <div class="text-<?= $client['balance_aed'] > 0 ? 'danger' : 'success' ?>">
                                    AED <?= number_format($client['balance_aed'], 2) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($client['balance_rmb'] == 0 && $client['balance_usd'] == 0 && $client['balance_aed'] == 0): ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info"><?= $client['transaction_count'] ?? 0 ?></span>
                        </td>
                        <td>
                            <?php if ($client['status'] === 'active'): ?>
                                <span class="badge bg-success"><?= __('common.active') ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= __('common.inactive') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="/clients/statement/<?= $client['id'] ?>" 
                                   class="btn btn-info ajax-link" 
                                   title="<?= __('clients.statement') ?>">
                                    <i class="bi bi-file-text"></i>
                                </a>
                                <a href="/clients/edit/<?= $client['id'] ?>" 
                                   class="btn btn-warning ajax-link" 
                                   title="<?= __('common.edit') ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <a href="/clients/delete/<?= $client['id'] ?>" 
                                       class="btn btn-danger delete-confirm" 
                                       title="<?= __('common.delete') ?>">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>