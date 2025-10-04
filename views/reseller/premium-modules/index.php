<?php

use App\Helpers;

$csrf = Helpers::csrfToken();
?>
<div class="row g-4">
    <?php if (!empty($errors)): ?>
        <div class="col-12">
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= Helpers::sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="col-12">
            <div class="alert alert-success mb-0"><?= Helpers::sanitize($success) ?></div>
        </div>
    <?php endif; ?>

    <?php if (empty($modules)): ?>
        <div class="col-12">
            <div class="alert alert-info mb-0">Şu anda satışta premium modül bulunmuyor.</div>
        </div>
    <?php else: ?>
        <?php foreach ($modules as $module): ?>
            <?php $purchase = isset($module['purchase']) ? $module['purchase'] : null; ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="card-title mb-0"><?= Helpers::sanitize($module['name']) ?></h5>
                            <span class="badge bg-primary fs-6"><?= Helpers::formatCurrency((float) $module['price']) ?></span>
                        </div>
                        <p class="card-text text-muted flex-grow-1"><?= nl2br(Helpers::sanitize($module['description'])) ?></p>

                        <?php if ($purchase && $purchase['payment_status'] === 'paid'): ?>
                            <div class="alert alert-success py-2 px-3">
                                <div class="fw-semibold">Aktif</div>
                                <small>Lisans anahtarınız: <code><?= Helpers::sanitize($purchase['license_key']) ?></code></small>
                            </div>
                            <form method="post" class="d-grid gap-2" action="/premium-modules.php">
                                <input type="hidden" name="action" value="download">
                                <input type="hidden" name="purchase_id" value="<?= (int) $purchase['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrf) ?>">
                                <button type="submit" class="btn btn-outline-primary">Modülü İndir</button>
                            </form>
                        <?php elseif ($purchase && $purchase['payment_status'] === 'pending'): ?>
                            <div class="alert alert-warning text-dark py-2 px-3">
                                <div class="fw-semibold">Ödeme Bekleniyor</div>
                                <small>Ödemeniz onaylandığında indirilebilir olacaktır.</small>
                            </div>
                        <?php else: ?>
                            <form method="post" class="d-grid gap-3" action="/premium-modules.php">
                                <input type="hidden" name="action" value="purchase">
                                <input type="hidden" name="module_id" value="<?= (int) $module['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrf) ?>">
                                <div>
                                    <label class="form-label">Ödeme Yöntemi</label>
                                    <select name="payment_method" class="form-select" required>
                                        <option value="balance">Cüzdan Bakiyesi</option>
                                        <option value="bank_transfer">Banka Havalesi</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">Satın Al</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
