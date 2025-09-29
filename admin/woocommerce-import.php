<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Database;
use App\Helpers;
use App\Importers\WooCommerceImporter;

Auth::requireRoles(array('super_admin', 'admin', 'content'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
$errors = array();
$success = '';
$warning = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Geçersiz istek. Lütfen tekrar deneyin.';
    }

    if (!$errors) {
        $result = WooCommerceImporter::import($pdo, isset($_FILES['csv_file']) ? $_FILES['csv_file'] : array());
        $errors = array_merge($errors, $result['errors']);

        if (!$result['errors']) {
            if ($result['imported'] > 0 || $result['updated'] > 0) {
                $success = sprintf(
                    'WooCommerce CSV içe aktarımı tamamlandı. %d yeni ürün eklendi, %d ürün güncellendi.',
                    $result['imported'],
                    $result['updated']
                );

                AuditLog::record(
                    $currentUser['id'],
                    'product.import_csv',
                    'product',
                    null,
                    sprintf('WooCommerce CSV içe aktarıldı: %d yeni, %d güncellendi', $result['imported'], $result['updated'])
                );
            } elseif (!empty($result['warning'])) {
                $warning = $result['warning'];
            }
        }
    }
}

$recentProducts = $pdo->query(
    "SELECT pr.name, pr.sku, pr.price, pr.status, pr.created_at, pr.updated_at, cat.name AS category_name
     FROM products pr
     INNER JOIN categories cat ON pr.category_id = cat.id
     ORDER BY COALESCE(pr.updated_at, pr.created_at) DESC
     LIMIT 10"
)->fetchAll();

$pageTitle = 'WooCommerce İçe Aktar';

include __DIR__ . '/../templates/header.php';
?>
<div class="import-layout container-xl">
    <div class="row g-4 align-items-start">
        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">WooCommerce CSV İçe Aktar</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">WooCommerce ürün dışa aktarım dosyanızı yükleyerek kategorileriniz ve ürünleriniz otomatik olarak güncellenecektir.</p>

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

                    <?php if ($warning): ?>
                        <div class="alert alert-warning"><?= Helpers::sanitize($warning) ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="vstack gap-3">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                        <div>
                            <label class="form-label">WooCommerce CSV Dosyası</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        <div class="import-supported">
                            <span class="import-supported-title">Desteklenen sütunlar</span>
                            <span class="import-supported-list">Name, Categories, Regular price/Price (TRY), SKU, Description, Short description, Status.</span>
                        </div>
                        <p class="text-muted small mt-2 mb-0">Regular price alanındaki değerler TL olarak yorumlanır; sistem güncel kur ve komisyon oranınızı kullanarak USD satış fiyatını otomatik hesaplar.</p>
                        <button type="submit" class="btn btn-primary">Dosyayı İçe Aktar</button>
                    </form>

                    <p class="text-muted small mt-4 mb-0">Mevcut ürün listenizi dışa aktarmak için <a href="/admin/woocommerce-export.php">WooCommerce dışa aktarma aracını</a> kullanabilirsiniz.</p>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">Nasıl Çalışır?</h5>
                </div>
                <div class="card-body">
                    <ol class="text-muted small mb-0 import-steps">
                        <li>WooCommerce yönetim panelinizde <em>Ürünler &gt; Dışa Aktar</em> adımlarını izleyin.</li>
                        <li>Varsayılan alanları seçili bırakın ve CSV dosyasını dışa aktarın.</li>
                        <li>Bu ekrandan dosyanızı yükleyin; kategori ve ürünler eşleşerek güncellenecektir.</li>
                        <li>Aynı SKU veya başlığa sahip ürünler güncellenir, diğerleri yeni olarak eklenir.</li>
                        <li>Regular price sütunundaki rakamların TL olduğundan emin olun; sistem USD satış fiyatını ve komisyonu otomatik hesaplar.</li>
                        <li>İşlem tamamlandığında burada bildirim alırsınız.</li>
                    </ol>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Son Güncellenen Ürünler</h5>
                    <a href="/admin/products.php" class="btn btn-sm btn-outline-primary">Ürün Yönetimine Git</a>
                </div>
                <div class="card-body">
                    <?php if (!$recentProducts): ?>
                        <p class="text-muted mb-0">Henüz ürün bulunmuyor.</p>
                    <?php else: ?>
                        <div class="table-responsive import-recent-table">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Ürün</th>
                                    <th>Kategori</th>
                                    <th>Fiyat</th>
                                    <th>Durum</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentProducts as $product): ?>
                                    <tr>
                                        <td>
                                            <strong><?= Helpers::sanitize($product['name']) ?></strong><br>
                                            <small class="text-muted">SKU: <?= Helpers::sanitize(isset($product['sku']) ? $product['sku'] : '-') ?></small>
                                        </td>
                                        <td><?= Helpers::sanitize($product['category_name']) ?></td>
                                        <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$product['price'])) ?></td>
                                        <td>
                                            <?php if ($product['status'] === 'active'): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
