<?php
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_not_authenticated();
$title = 'Bakiye Yönetimi';
$user = current_user();
require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h1 class="h4 mb-3">Bakiye Durumu</h1>
                <p class="display-5 text-primary">₺<?= sanitize(number_format((float)$user['balance'], 2)) ?></p>
                <p class="text-muted">Bakiye yüklemeleri manuel olarak onaylanmaktadır. Aşağıdaki formu kullanarak talepte bulunun.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Bakiye Yükle</h2>
                <div class="alert alert-danger d-none" id="payment-error"></div>
                <div class="alert alert-success d-none" id="payment-success"></div>
                <form id="payment-form">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Yükleme Tutarı (₺)</label>
                        <input type="number" min="10" step="0.01" class="form-control" id="amount" name="amount" required>
                    </div>
                    <div class="mb-3">
                        <label for="method" class="form-label">Ödeme Yöntemi</label>
                        <select class="form-select" id="method" name="method">
                            <option value="PayTR">PayTR (API placeholder)</option>
                            <option value="Manuel">Manuel Havale/EFT</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Talep Gönder</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
