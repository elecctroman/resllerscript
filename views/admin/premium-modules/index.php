<?php

use App\Helpers;

$csrf = Helpers::csrfToken();
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Premium Modül</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrf) ?>">
                    <div class="mb-3">
                        <label class="form-label">Modül Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fiyat</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" name="price" class="form-control" required>
                            <span class="input-group-text"><?= Helpers::sanitize(Helpers::activeCurrency()) ?></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Modül ZIP Dosyası</label>
                        <input type="file" name="module_file" accept=".zip" class="form-control" required>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="module-status" name="status" value="1" checked>
                        <label class="form-check-label" for="module-status">Aktif Olsun</label>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Modülü Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Modül Listesi</h5>
                <a href="/admin/premium-module-purchases.php" class="btn btn-outline-secondary btn-sm">Satın Almalar</a>
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
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Adı</th>
                            <th>Fiyat</th>
                            <th>Durum</th>
                            <th>Oluşturma</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($modules)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Henüz modül eklenmedi.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($modules as $module): ?>
                                <tr>
                                    <td><?= (int) $module['id'] ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($module['name']) ?></strong>
                                        <div class="small text-muted"><?= nl2br(Helpers::sanitize($module['description'])) ?></div>
                                    </td>
                                    <td><?= Helpers::formatCurrency((float) $module['price']) ?></td>
                                    <td>
                                        <?php if ((int)$module['status'] === 1): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= isset($module['created_at']) ? date('d.m.Y H:i', strtotime($module['created_at'])) : '-' ?></td>
                                    <td class="text-end">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="module_id" value="<?= (int)$module['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrf) ?>">
                                            <input type="hidden" name="status" value="<?= (int)$module['status'] === 1 ? '0' : '1' ?>">
                                            <button type="submit" class="btn btn-sm <?= (int)$module['status'] === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                                <?= (int)$module['status'] === 1 ? 'Pasifleştir' : 'Aktifleştir' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
