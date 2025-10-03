<?php

use App\Helpers;

$csrf = Helpers::csrfToken();
?>
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Premium Modül Satın Almaları</h5>
        <a href="/admin/premium-modules.php" class="btn btn-outline-secondary btn-sm">Modülleri Yönet</a>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= Helpers::sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success mb-4"><?= Helpers::sanitize($success) ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Kullanıcı</th>
                    <th>Modül</th>
                    <th>Durum</th>
                    <th>Lisans</th>
                    <th>Oluşturma</th>
                    <th class="text-end">İşlemler</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($purchases)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Henüz satın alma kaydı bulunmuyor.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($purchases as $purchase): ?>
                        <tr>
                            <td><?= (int) $purchase['id'] ?></td>
                            <td>
                                <strong><?= Helpers::sanitize($purchase['user_name']) ?></strong><br>
                                <small class="text-muted"><?= Helpers::sanitize($purchase['user_email']) ?></small>
                            </td>
                            <td><?= Helpers::sanitize($purchase['module_name']) ?></td>
                            <td>
                                <?php if ($purchase['payment_status'] === 'paid'): ?>
                                    <span class="badge bg-success">Ödendi</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Beklemede</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($purchase['license_key'])): ?>
                                    <code><?= Helpers::sanitize($purchase['license_key']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">Oluşturulmadı</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d.m.Y H:i', strtotime($purchase['created_at'])) ?></td>
                            <td class="text-end">
                                <?php if ($purchase['payment_status'] !== 'paid'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="mark_paid">
                                        <input type="hidden" name="purchase_id" value="<?= (int) $purchase['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrf) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Ödeme Onayla</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">Aktif</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
