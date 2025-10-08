<?php
require __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Helpers;

Auth::requireAdmin(array('super_admin', 'admin'));

$pageTitle = 'Yeni Üye Oluştur';
$csrfToken = Helpers::csrfToken();

$roleOptions = array(
    'user' => 'Üye',
    'reseller' => 'Bayi',
    'admin' => 'Admin',
);

$statusOptions = array(
    'active' => 'Aktif',
    'suspended' => 'Askıya Alındı',
    'pending' => 'Onay Bekliyor',
);

include __DIR__ . '/../../templates/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Yeni Üye Oluştur</h1>
        <p class="text-muted mb-0">Platforma yeni bir üye ekleyin ve rolünü belirleyin.</p>
    </div>
    <a href="/admin/users/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Üye listesine dön
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="post" action="/admin/users/create.php" class="row g-4">
            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
            <div class="col-12 col-md-6">
                <label for="user-name" class="form-label">Ad Soyad</label>
                <input type="text" class="form-control" id="user-name" name="name" placeholder="Örn. Ayşe Yılmaz" required>
            </div>
            <div class="col-12 col-md-6">
                <label for="user-email" class="form-label">E-posta</label>
                <input type="email" class="form-control" id="user-email" name="email" placeholder="ornek@site.com" required>
            </div>
            <div class="col-12 col-md-6">
                <label for="user-password" class="form-label">Parola</label>
                <input type="password" class="form-control" id="user-password" name="password" placeholder="En az 8 karakter" required>
            </div>
            <div class="col-12 col-md-6">
                <label for="user-phone" class="form-label">Telefon</label>
                <input type="tel" class="form-control" id="user-phone" name="phone" placeholder="5XX XXX XX XX">
            </div>
            <div class="col-12 col-md-6">
                <label for="user-company" class="form-label">Firma</label>
                <input type="text" class="form-control" id="user-company" name="company" placeholder="Şirket adı (opsiyonel)">
            </div>
            <div class="col-12 col-md-6">
                <label for="user-role" class="form-label">Rol</label>
                <select id="user-role" name="role" class="form-select" required>
                    <?php foreach ($roleOptions as $value => $label): ?>
                        <option value="<?= Helpers::sanitize($value) ?>"><?= Helpers::sanitize($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label for="user-status" class="form-label">Durum</label>
                <select id="user-status" name="status" class="form-select" required>
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= Helpers::sanitize($value) ?>"><?= Helpers::sanitize($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label for="user-balance" class="form-label">Başlangıç Bakiyesi</label>
                <div class="input-group">
                    <span class="input-group-text">₺</span>
                    <input type="number" step="0.01" min="0" class="form-control" id="user-balance" name="balance" value="0.00">
                </div>
                <small class="text-muted">İsteğe bağlı. Bakiye hareketi kaydı oluşturulacaktır.</small>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Kaydet
                </button>
                <button type="button" class="btn btn-outline-secondary" disabled>Kaydet ve davet gönder (yakında)</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

