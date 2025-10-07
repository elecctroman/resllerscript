<?php
require __DIR__ . '/../bootstrap.php';

use App\AuditLog;
use App\Auth;
use App\Database;
use App\Helpers;
use App\Services\ProviderIntegrationService;

Auth::requireRoles(array('super_admin', 'admin'));

$pdo = Database::connection();
$service = new ProviderIntegrationService($pdo);
$currentUser = $_SESSION['user'];

$errors = array();
$success = '';
$testResult = null;
$productsFetchResult = null;

$providers = $service->listProviders();
$selectedProviderId = isset($_GET['provider_id']) ? (int) $_GET['provider_id'] : 0;

if ($selectedProviderId === 0 && $providers) {
    $selectedProviderId = (int) $providers[0]['id'];
}

$selectedProvider = $selectedProviderId ? $service->findProvider($selectedProviderId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Geçersiz istek. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        if ($action === 'create_provider') {
            $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            $baseUrl = isset($_POST['base_url']) ? trim((string) $_POST['base_url']) : '';
            $apiKey = isset($_POST['api_key']) ? trim((string) $_POST['api_key']) : '';
            $isActive = isset($_POST['is_active']);

            if ($name === '' || $baseUrl === '' || $apiKey === '') {
                $errors[] = 'Sağlayıcı adı, API adresi ve anahtar alanları zorunludur.';
            }

            if (!$errors) {
                $providerId = $service->createProvider($name, $baseUrl, $apiKey, $isActive);
                $success = 'Sağlayıcı eklendi.';
                AuditLog::record(
                    $currentUser['id'],
                    'provider.create',
                    'external_provider',
                    $providerId,
                    sprintf('Yeni sağlayıcı eklendi: %s', $name)
                );

                $selectedProviderId = $providerId;
                $selectedProvider = $service->findProvider($selectedProviderId);
                $providers = $service->listProviders();
            }
        } elseif ($action === 'update_provider') {
            $providerId = isset($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
            $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            $baseUrl = isset($_POST['base_url']) ? trim((string) $_POST['base_url']) : '';
            $apiKey = isset($_POST['api_key']) ? trim((string) $_POST['api_key']) : '';
            $isActive = isset($_POST['is_active']);

            if ($providerId <= 0 || !$service->findProvider($providerId)) {
                $errors[] = 'Seçili sağlayıcı bulunamadı.';
            }

            if ($name === '' || $baseUrl === '' || $apiKey === '') {
                $errors[] = 'Sağlayıcı adı, API adresi ve anahtar alanları zorunludur.';
            }

            if (!$errors) {
                $service->updateProvider($providerId, $name, $baseUrl, $apiKey, $isActive);
                $success = 'Sağlayıcı güncellendi.';
                AuditLog::record(
                    $currentUser['id'],
                    'provider.update',
                    'external_provider',
                    $providerId,
                    sprintf('Sağlayıcı güncellendi: %s', $name)
                );

                $selectedProviderId = $providerId;
                $selectedProvider = $service->findProvider($selectedProviderId);
                $providers = $service->listProviders();
            }
        } elseif ($action === 'delete_provider') {
            $providerId = isset($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
            if ($providerId > 0 && $service->findProvider($providerId)) {
                $service->deleteProvider($providerId);
                $success = 'Sağlayıcı silindi.';
                AuditLog::record(
                    $currentUser['id'],
                    'provider.delete',
                    'external_provider',
                    $providerId,
                    sprintf('Sağlayıcı silindi: #%d', $providerId)
                );
                $providers = $service->listProviders();
                if ($selectedProviderId === $providerId) {
                    $selectedProviderId = $providers ? (int) $providers[0]['id'] : 0;
                    $selectedProvider = $selectedProviderId ? $service->findProvider($selectedProviderId) : null;
                }
            }
        } elseif ($action === 'test_provider') {
            $providerId = isset($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
            if ($providerId > 0) {
                $selectedProviderId = $providerId;
                $selectedProvider = $service->findProvider($selectedProviderId);
                if ($selectedProvider) {
                    $testResult = $service->testConnection($selectedProvider);
                    $success = $testResult['success'] ? 'API bağlantısı başarılı.' : $success;
                    if (!$testResult['success']) {
                        $errors[] = $testResult['message'];
                    }
                } else {
                    $errors[] = 'Sağlayıcı bulunamadı.';
                }
            }
        } elseif ($action === 'fetch_products') {
            $providerId = isset($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
            if ($providerId > 0) {
                $selectedProviderId = $providerId;
                $selectedProvider = $service->findProvider($selectedProviderId);
                if ($selectedProvider) {
                    $productsFetchResult = $service->fetchProducts($selectedProvider);
                    if (!$productsFetchResult['success']) {
                        $errors[] = $productsFetchResult['message'];
                    } else {
                        $success = 'Ürün listesi güncellendi.';
                    }
                } else {
                    $errors[] = 'Sağlayıcı bulunamadı.';
                }
            }
        } elseif ($action === 'import_product') {
            $providerId = isset($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
            $remoteProductId = isset($_POST['remote_product_id']) ? trim((string) $_POST['remote_product_id']) : '';
            $name = isset($_POST['product_name']) ? trim((string) $_POST['product_name']) : '';
            $description = isset($_POST['product_description']) ? trim((string) $_POST['product_description']) : '';
            $remoteAmount = isset($_POST['remote_amount']) ? trim((string) $_POST['remote_amount']) : '';
            $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;

            $selectedProviderId = $providerId;
            $selectedProvider = $providerId > 0 ? $service->findProvider($providerId) : null;

            if (!$selectedProvider) {
                $errors[] = 'Sağlayıcı bulunamadı.';
            }

            $costSanitized = preg_replace('/[^0-9.,-]/', '', $remoteAmount);
            $costSanitized = str_replace(',', '.', (string) $costSanitized);
            $costPrice = $costSanitized !== '' ? (float) $costSanitized : 0.0;

            if ($remoteProductId === '' || $name === '' || $categoryId <= 0 || $costPrice <= 0) {
                $errors[] = 'Ürün adı, fiyatı ve kategori seçimi zorunludur.';
            }

            if (!$errors && $selectedProvider) {
                $salePrice = Helpers::priceFromCostTry($costPrice);

                $mapping = $service->findMapping($providerId, $remoteProductId);
                $skuSuffix = preg_replace('/[^A-Za-z0-9_-]/', '', $remoteProductId);
                if ($skuSuffix === '') {
                    $skuSuffix = (string) $remoteProductId;
                }
                $sku = substr('EXT-' . $providerId . '-' . $skuSuffix, 0, 150);

                if ($mapping) {
                    $productId = (int) $mapping['product_id'];
                    $stmt = $pdo->prepare('UPDATE products SET name = :name, category_id = :category_id, cost_price_try = :cost_price_try, price = :price, description = :description, updated_at = NOW() WHERE id = :id');
                    $stmt->execute(array(
                        'id' => $productId,
                        'name' => $name,
                        'category_id' => $categoryId,
                        'cost_price_try' => $costPrice,
                        'price' => $salePrice,
                        'description' => $description !== '' ? $description : null,
                    ));

                    $service->saveMapping($providerId, $remoteProductId, $productId);
                    $success = 'Ürün güncellendi.';

                    AuditLog::record(
                        $currentUser['id'],
                        'provider.product_update',
                        'product',
                        $productId,
                        sprintf('Sağlayıcı ürünü güncellendi: %s', $name)
                    );
                } else {
                    $stmt = $pdo->prepare('INSERT INTO products (name, category_id, cost_price_try, price, description, sku, status, automatic_delivery, created_at) VALUES (:name, :category_id, :cost_price_try, :price, :description, :sku, :status, :automatic_delivery, NOW())');
                    $stmt->execute(array(
                        'name' => $name,
                        'category_id' => $categoryId,
                        'cost_price_try' => $costPrice,
                        'price' => $salePrice,
                        'description' => $description !== '' ? $description : null,
                        'sku' => $sku,
                        'status' => 'active',
                        'automatic_delivery' => 0,
                    ));

                    $productId = (int) $pdo->lastInsertId();
                    $service->saveMapping($providerId, $remoteProductId, $productId);
                    $success = 'Ürün içe aktarıldı.';

                    AuditLog::record(
                        $currentUser['id'],
                        'provider.product_import',
                        'product',
                        $productId,
                        sprintf('Sağlayıcıdan ürün içe aktarıldı: %s', $name)
                    );
                }

                $productsFetchResult = $service->fetchProducts($selectedProvider);
            }
        }
    }
}

if (!$providers) {
    $providers = $service->listProviders();
}

if (!$selectedProvider && $selectedProviderId) {
    $selectedProvider = $service->findProvider($selectedProviderId);
}

$categories = array();
try {
    $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
    if ($stmt) {
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Throwable $exception) {
    $categories = array();
}

$pageTitle = 'Sağlayıcılar';
include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Sağlayıcı</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <input type="hidden" name="action" value="create_provider">
                    <div class="mb-3">
                        <label class="form-label">Sağlayıcı Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Adresi</label>
                        <input type="url" name="base_url" class="form-control" placeholder="https://partner.lotuslisans.com.tr" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Anahtarı</label>
                        <input type="text" name="api_key" class="form-control" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="create-provider-active" checked>
                        <label class="form-check-label" for="create-provider-active">Aktif</label>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Sağlayıcı Ekle</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Kayıtlı Sağlayıcılar</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php if (!$providers): ?>
                    <div class="list-group-item">Henüz kayıtlı sağlayıcı yok.</div>
                <?php else: ?>
                    <?php foreach ($providers as $provider): ?>
                        <?php $isActive = (int) $provider['is_active'] === 1; ?>
                        <a href="<?= Helpers::sanitize(Helpers::urlWithQuery(array('provider_id' => (int) $provider['id']))) ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= (int) $provider['id'] === $selectedProviderId ? 'active' : '' ?>">
                            <span>
                                <?= Helpers::sanitize($provider['name']) ?>
                                <small class="d-block text-muted"><?= Helpers::sanitize($provider['base_url']) ?></small>
                            </span>
                            <span class="badge bg-<?= $isActive ? 'success' : 'secondary' ?>"><?= $isActive ? 'Aktif' : 'Pasif' ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sağlayıcı Detayları</h5>
                <?php if ($selectedProvider): ?>
                    <small class="text-muted">Son test: <?= $selectedProvider['last_tested_at'] ? date('d.m.Y H:i', strtotime($selectedProvider['last_tested_at'])) : '—' ?></small>
                <?php endif; ?>
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

                <?php if ($selectedProvider): ?>
                    <form method="post" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                        <input type="hidden" name="action" value="update_provider">
                        <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Sağlayıcı Adı</label>
                            <input type="text" name="name" class="form-control" value="<?= Helpers::sanitize($selectedProvider['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">API Adresi</label>
                            <input type="url" name="base_url" class="form-control" value="<?= Helpers::sanitize($selectedProvider['base_url']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">API Anahtarı</label>
                            <input type="text" name="api_key" class="form-control" value="<?= Helpers::sanitize($selectedProvider['api_key']) ?>" required>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="provider-active" <?= (int) $selectedProvider['is_active'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="provider-active">Aktif</label>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                        </div>
                    </form>

                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
                        <form method="post" class="d-inline" onsubmit="return confirm('Bu sağlayıcıyı silmek istediğinizden emin misiniz?');">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete_provider">
                            <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger">Sil</button>
                        </form>
                        <div class="d-flex flex-wrap gap-2">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                <input type="hidden" name="action" value="test_provider">
                                <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                                <button type="submit" class="btn btn-outline-secondary">API Testi Yap</button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                <input type="hidden" name="action" value="fetch_products">
                                <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                                <button type="submit" class="btn btn-outline-primary">Ürünleri Getir</button>
                            </form>
                        </div>
                    </div>

                    <?php if ($testResult): ?>
                        <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= Helpers::sanitize($testResult['message']) ?></strong>
                                    <?php if (!empty($testResult['data'])): ?>
                                        <pre class="small bg-light p-3 rounded mt-2 mb-0"><?= Helpers::sanitize(json_encode($testResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-secondary">HTTP <?= (int) $testResult['status'] ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($productsFetchResult): ?>
                        <?php if (!$productsFetchResult['success']): ?>
                            <div class="alert alert-danger"><?= Helpers::sanitize($productsFetchResult['message']) ?></div>
                        <?php else: ?>
                            <div class="alert alert-info">Sağlayıcı ürünleri listelendi. İçe aktarmak istediğiniz ürünler için kategori seçip "İçe Aktar" butonuna basın.</div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Başlık</th>
                                        <th>Tutar (TRY)</th>
                                        <th>Stok</th>
                                        <th>Durum</th>
                                        <th class="text-end">İşlemler</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($productsFetchResult['data'] as $remoteProduct): ?>
                                        <tr>
                                            <td><?= Helpers::sanitize($remoteProduct['id']) ?></td>
                                            <td>
                                                <strong><?= Helpers::sanitize($remoteProduct['title']) ?></strong>
                                                <?php if (!empty($remoteProduct['content'])): ?>
                                                    <p class="small text-muted mb-0"><?= nl2br(Helpers::sanitize(mb_strimwidth($remoteProduct['content'], 0, 180, '…', 'UTF-8'))) ?></p>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= Helpers::sanitize($remoteProduct['amount']) ?></td>
                                            <td><?= (int) $remoteProduct['stock'] ?></td>
                                            <td>
                                                <?php if ($remoteProduct['available']): ?>
                                                    <span class="badge bg-success">Sipariş Verilebilir</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Pasif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($categories): ?>
                                                    <form method="post" class="d-flex gap-2 align-items-center justify-content-end">
                                                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                        <input type="hidden" name="action" value="import_product">
                                                        <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                                                        <input type="hidden" name="remote_product_id" value="<?= Helpers::sanitize($remoteProduct['id']) ?>">
                                                        <input type="hidden" name="product_name" value="<?= Helpers::sanitize($remoteProduct['title']) ?>">
                                                        <input type="hidden" name="product_description" value="<?= Helpers::sanitize($remoteProduct['content']) ?>">
                                                        <input type="hidden" name="remote_amount" value="<?= Helpers::sanitize($remoteProduct['amount']) ?>">
                                                        <select name="category_id" class="form-select form-select-sm" required>
                                                            <option value="">Kategori Seçin</option>
                                                            <?php foreach ($categories as $category): ?>
                                                                <option value="<?= (int) $category['id'] ?>"><?= Helpers::sanitize($category['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-primary">İçe Aktar</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">Kategori oluşturmalısınız.</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted mb-0">Öncelikle sol taraftan bir sağlayıcı seçin veya oluşturun.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
include __DIR__ . '/../templates/footer.php';
