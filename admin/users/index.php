<?php
require __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Helpers;

Auth::requireAdmin(array('super_admin', 'admin'));

$pageTitle = 'Üyeler';
$roleFilter = isset($_GET['role']) ? trim((string) $_GET['role']) : '';
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$roleOptions = array(
    '' => 'Tüm Roller',
    'admin' => 'Admin',
    'reseller' => 'Bayi',
    'user' => 'Üye',
);

$statusOptions = array(
    '' => 'Tüm Durumlar',
    'active' => 'Aktif',
    'suspended' => 'Askıya Alındı',
    'pending' => 'Onay Bekliyor',
);

if (!array_key_exists($roleFilter, $roleOptions)) {
    $roleFilter = '';
}

if (!array_key_exists($statusFilter, $statusOptions)) {
    $statusFilter = '';
}

include __DIR__ . '/../../templates/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Üyeler</h1>
        <p class="text-muted mb-0">Platformunuzdaki tüm üyeleri görüntüleyin, filtreleyin ve yönetin.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/admin/users/create.php" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>
            Yeni Üye Oluştur
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label for="filter-role" class="form-label">Rol</label>
                <select id="filter-role" name="role" class="form-select">
                    <?php foreach ($roleOptions as $value => $label): ?>
                        <option value="<?= Helpers::sanitize($value) ?>"<?= $value === $roleFilter ? ' selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label for="filter-status" class="form-label">Durum</label>
                <select id="filter-status" name="status" class="form-select">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= Helpers::sanitize($value) ?>"<?= $value === $statusFilter ? ' selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label for="filter-search" class="form-label">Ara</label>
                <input type="search" id="filter-search" name="q" class="form-control" value="<?= Helpers::sanitize($searchQuery) ?>" placeholder="Ad, e-posta veya şirket">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-grow-1">Filtrele</button>
                <a href="/admin/users/index.php" class="btn btn-light" title="Filtreleri temizle">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-3">
        <h2 class="h5 mb-0">Üye Listesi</h2>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                <i class="bi bi-download me-1"></i>CSV Dışa Aktar (yakında)
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                Toplu İşlem
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width: 40px;">
                        <input class="form-check-input" type="checkbox" disabled>
                    </th>
                    <th scope="col">Ad Soyad</th>
                    <th scope="col">E-posta</th>
                    <th scope="col">Rol</th>
                    <th scope="col">Durum</th>
                    <th scope="col" class="text-end">Bakiye</th>
                    <th scope="col">Kayıt Tarihi</th>
                    <th scope="col" class="text-end">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <input class="form-check-input" type="checkbox" disabled>
                    </td>
                    <td>
                        <div class="fw-semibold">Örnek Üye</div>
                        <div class="text-muted small">Demo verisi</div>
                    </td>
                    <td>demo@example.com</td>
                    <td><span class="badge bg-primary-subtle text-primary">Üye</span></td>
                    <td><span class="badge bg-success-subtle text-success">Aktif</span></td>
                    <td class="text-end">₺0,00</td>
                    <td>01.01.2024</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Üye işlemleri">
                            <button type="button" class="btn btn-outline-secondary" disabled>Düzenle</button>
                            <button type="button" class="btn btn-outline-secondary" disabled>Rol Değiştir</button>
                            <button type="button" class="btn btn-outline-danger" disabled>Sil</button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        Gerçek veriler çok yakında burada görünecek.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">Toplam 0 kayıt</small>
        <nav aria-label="Üye sayfaları">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item disabled"><span class="page-link">Önceki</span></li>
                <li class="page-item active" aria-current="page"><span class="page-link">1</span></li>
                <li class="page-item disabled"><span class="page-link">Sonraki</span></li>
            </ul>
        </nav>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

