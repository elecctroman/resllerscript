<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Database;

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    Helpers::redirect('/');
}

$pdo = Database::connection();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $initialBalance = (float)($_POST['initial_balance'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $features = trim($_POST['features'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!$name || $price <= 0) {
            $errors[] = 'Paket adı ve fiyatı zorunludur.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO packages (name, price, initial_balance, description, features, is_active, created_at) VALUES (:name, :price, :initial_balance, :description, :features, :is_active, NOW())');
            $stmt->execute([
                'name' => $name,
                'price' => $price,
                'initial_balance' => $initialBalance,
                'description' => $description,
                'features' => $features,
                'is_active' => $isActive,
            ]);
            $success = 'Paket başarıyla oluşturuldu.';
        }
    } elseif ($action === 'update') {
        $packageId = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $initialBalance = (float)($_POST['initial_balance'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $features = trim($_POST['features'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($packageId <= 0) {
            $errors[] = 'Geçersiz paket.';
        }

        if (!$name || $price <= 0) {
            $errors[] = 'Paket adı ve fiyatı zorunludur.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('UPDATE packages SET name = :name, price = :price, initial_balance = :initial_balance, description = :description, features = :features, is_active = :is_active, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                'id' => $packageId,
                'name' => $name,
                'price' => $price,
                'initial_balance' => $initialBalance,
                'description' => $description,
                'features' => $features,
                'is_active' => $isActive,
            ]);
            $success = 'Paket güncellendi.';
        }
    } elseif ($action === 'delete') {
        $packageId = (int)($_POST['id'] ?? 0);
        if ($packageId > 0) {
            $stmt = $pdo->prepare('DELETE FROM packages WHERE id = :id');
            $stmt->execute(['id' => $packageId]);
            $success = 'Paket silindi.';
        }
    }
}

$packages = $pdo->query('SELECT * FROM packages ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Paket Yönetimi';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Paket Oluştur</h5>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Paket Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">

                        <input type="number" step="0.01" name="price" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Başlangıç Bakiyesi</label>
                        <input type="number" step="0.01" name="initial_balance" class="form-control" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Paket Açıklaması</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Öne Çıkan Özellikler</label>
                        <textarea name="features" class="form-control" rows="3" placeholder="Her satıra bir özellik yazın"></textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="createActive" name="is_active" checked>
                        <label class="form-check-label" for="createActive">Paket aktif olsun</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Paket Oluştur</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Mevcut Paketler</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Paket</th>

                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($packages as $package): ?>
                            <tr>
                                <td><?= (int)$package['id'] ?></td>
                                <td>
                                    <strong><?= Helpers::sanitize($package['name']) ?></strong><br>
                                    <small class="text-muted"><?= Helpers::sanitize($package['description'] ?? '') ?></small>
                                </td>

                                <td>
                                    <?php if ($package['is_active']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPackage<?= (int)$package['id'] ?>">Düzenle</button>
                                    <form action="" method="post" class="d-inline" onsubmit="return confirm('Paketi silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$package['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editPackage<?= (int)$package['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Paketi Düzenle</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="id" value="<?= (int)$package['id'] ?>">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Paket Adı</label>
                                                        <input type="text" name="name" class="form-control" value="<?= Helpers::sanitize($package['name']) ?>" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Ücret</label>
                                                        <input type="number" step="0.01" name="price" class="form-control" value="<?= Helpers::sanitize($package['price']) ?>" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Başlangıç Bakiyesi</label>
                                                        <input type="number" step="0.01" name="initial_balance" class="form-control" value="<?= Helpers::sanitize($package['initial_balance']) ?>">
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Açıklama</label>
                                                        <textarea name="description" class="form-control" rows="3"><?= Helpers::sanitize($package['description'] ?? '') ?></textarea>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Özellikler</label>
                                                        <textarea name="features" class="form-control" rows="3"><?= Helpers::sanitize($package['features'] ?? '') ?></textarea>
                                                    </div>
                                                    <div class="col-12 form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="packageActive<?= (int)$package['id'] ?>" name="is_active" <?= $package['is_active'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="packageActive<?= (int)$package['id'] ?>">Aktif</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                                <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
