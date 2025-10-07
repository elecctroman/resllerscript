<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Services\ProviderManager;

Auth::requireRoles(array('super_admin', 'admin'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
$errors = array();
$success = '';
$testDetails = null;

$selectedProviderId = isset($_GET['provider_id']) ? (int) $_GET['provider_id'] : 0;
$editProviderId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Geçersiz istek. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        if ($action === 'save_provider') {
            $data = array(
                'id' => isset($_POST['id']) ? (int) $_POST['id'] : 0,
                'name' => isset($_POST['name']) ? trim((string) $_POST['name']) : '',
                'slug' => isset($_POST['slug']) ? trim((string) $_POST['slug']) : '',
                'api_url' => isset($_POST['api_url']) ? trim((string) $_POST['api_url']) : '',
                'api_key' => isset($_POST['api_key']) ? trim((string) $_POST['api_key']) : '',
                'status' => isset($_POST['status']) ? 'active' : 'inactive',
            );

            $result = ProviderManager::saveProvider($data, $currentUser);
            if (!empty($result['success'])) {
                $success = $result['message'];
                if (!empty($result['id'])) {
                    $selectedProviderId = (int) $result['id'];
                }
                $editProviderId = 0;
            } else {
                $errors[] = $result['message'];
                if (!empty($result['id'])) {
                    $editProviderId = (int) $result['id'];
                }
            }
        } elseif ($action === 'delete_provider') {
            $providerId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $result = ProviderManager::deleteProvider($providerId, $currentUser);
            if (!empty($result['success'])) {
                $success = $result['message'];
                if ($selectedProviderId === $providerId) {
                    $selectedProviderId = 0;
                }
                if ($editProviderId === $providerId) {
                    $editProviderId = 0;
                }
            } else {
                $errors[] = $result['message'];
            }
        } elseif ($action === 'test_provider') {
            $providerId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $test = ProviderManager::testConnection($providerId);
            if (!empty($test['success'])) {
                $success = $test['message'];
            } else {
                $errors[] = $test['message'];
            }
            $testDetails = isset($test['data']) ? $test['data'] : null;
            if ($selectedProviderId === 0) {
                $selectedProviderId = $providerId;
            }
        } elseif ($action === 'sync_products') {
            $providerId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $sync = ProviderManager::syncProducts($providerId);
            if (!empty($sync['success'])) {
                $success = $sync['message'];
            } else {
                $errors[] = $sync['message'];
            }
            if ($selectedProviderId === 0) {
                $selectedProviderId = $providerId;
            }
        } elseif ($action === 'import_product') {
            $providerProductId = isset($_POST['provider_product_id']) ? (int) $_POST['provider_product_id'] : 0;
            $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
            $providerId = isset($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;

            $import = ProviderManager::importProviderProduct($providerProductId, $categoryId, $currentUser);
            if (!empty($import['success'])) {
                $success = $import['message'];
            } else {
                $errors[] = $import['message'];
            }
            if ($selectedProviderId === 0) {
                $selectedProviderId = $providerId;
            }
        }
    }
}

$providers = ProviderManager::listProviders();
if ($selectedProviderId === 0 && $providers) {
    $selectedProviderId = (int) $providers[0]['id'];
}

$selectedProvider = $selectedProviderId ? ProviderManager::findById($selectedProviderId) : null;
$providerProducts = $selectedProvider ? ProviderManager::listProviderProducts((int) $selectedProvider['id']) : array();

$editingProvider = $editProviderId ? ProviderManager::findById($editProviderId) : null;

$categoryRows = $pdo->query('SELECT id, parent_id, name FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$categoryMap = array();
foreach ($categoryRows as $category) {
    $categoryMap[(int) $category['id']] = array(
        'id' => (int) $category['id'],
        'parent_id' => isset($category['parent_id']) ? (int) $category['parent_id'] : 0,
        'name' => (string) $category['name'],
    );
}

$categoryChildren = array();
foreach ($categoryMap as $category) {
    $parentId = $category['parent_id'];
    if (!isset($categoryChildren[$parentId])) {
        $categoryChildren[$parentId] = array();
    }
    $categoryChildren[$parentId][] = $category;
}

foreach ($categoryChildren as &$children) {
    usort($children, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
}
unset($children);

$flattenedCategories = array();
$walkCategories = function (int $parentId, int $depth) use (&$walkCategories, &$flattenedCategories, $categoryChildren): void {
    if (!isset($categoryChildren[$parentId])) {
        return;
    }

    foreach ($categoryChildren[$parentId] as $category) {
        $flattenedCategories[] = array(
            'id' => $category['id'],
            'name' => $category['name'],
            'depth' => $depth,
        );
        $walkCategories($category['id'], $depth + 1);
    }
};
$walkCategories(0, 0);

Helpers::includeTemplate('header.php', array('pageTitle' => 'Sağlayıcılar'));
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1">Sağlayıcılar</h1>
            <p class="text-muted mb-0">Harici API sağlayıcılarını yönetebilir, bağlantı testleri yapabilir ve ürünleri içe aktarabilirsiniz.</p>
        </div>
        <a href="/admin/products.php" class="btn btn-outline-secondary"><i class="bi bi-box me-1"></i> Ürünlere Dön</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $error): ?>
                    <li><?= Helpers::sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-4"><?= Helpers::sanitize($success) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Kayıtlı Sağlayıcılar</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!$providers): ?>
                        <p class="text-muted text-center py-4 mb-0">Henüz sağlayıcı eklenmemiş.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($providers as $provider): ?>
                                <?php $isActive = isset($provider['status']) && $provider['status'] === 'active'; ?>
                                <a href="/admin/providers.php?provider_id=<?= (int) $provider['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $selectedProviderId === (int) $provider['id'] ? 'active' : '' ?>">
                                    <div>
                                        <div class="fw-semibold"><?= Helpers::sanitize($provider['name']) ?></div>
                                        <div class="small text-muted">Slug: <?= Helpers::sanitize($provider['slug']) ?></div>
                                    </div>
                                    <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>"><?= $isActive ? 'Aktif' : 'Pasif' ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Sağlayıcı Kaydı</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="action" value="save_provider">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                        <input type="hidden" name="id" value="<?= $editingProvider ? (int) $editingProvider['id'] : 0 ?>">

                        <div>
                            <label class="form-label">Sağlayıcı Adı</label>
                            <input type="text" name="name" class="form-control" value="<?= $editingProvider ? Helpers::sanitize($editingProvider['name']) : '' ?>" required>
                        </div>
                        <div>
                            <label class="form-label">Slug</label>
                            <input type="text" name="slug" class="form-control" value="<?= $editingProvider ? Helpers::sanitize($editingProvider['slug']) : '' ?>" required>
                            <small class="text-muted">Küçük harf ve tire kullanın. Örn: <code>lotus</code></small>
                        </div>
                        <div>
                            <label class="form-label">API URL</label>
                            <input type="url" name="api_url" class="form-control" placeholder="https://partner.lotuslisans.com.tr/api" value="<?= $editingProvider ? Helpers::sanitize($editingProvider['api_url']) : '' ?>" required>
                        </div>
                        <div>
                            <label class="form-label">API Key</label>
                            <input type="text" name="api_key" class="form-control" value="<?= $editingProvider ? Helpers::sanitize($editingProvider['api_key']) : '' ?>" placeholder="Sağlayıcı API anahtarınızı girin">
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="providerStatus" name="status" <?= $editingProvider && isset($editingProvider['status']) && $editingProvider['status'] === 'active' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="providerStatus">Sağlayıcı aktif</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">Kaydet</button>
                            <?php if ($editingProvider): ?>
                                <a href="/admin/providers.php?provider_id=<?= (int) $selectedProviderId ?>" class="btn btn-outline-secondary">Yeni</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <?php if (!$selectedProvider): ?>
                <div class="alert alert-info">Detay görmek için listeden bir sağlayıcı seçin.</div>
            <?php else: ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <h5 class="mb-1"><?= Helpers::sanitize($selectedProvider['name']) ?></h5>
                            <div class="small text-muted">API URL: <?= Helpers::sanitize($selectedProvider['api_url']) ?></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="test_provider">
                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $selectedProvider['id'] ?>">
                                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-plug me-1"></i> Bağlantıyı Test Et</button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="sync_products">
                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $selectedProvider['id'] ?>">
                                <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i> Ürünleri Senkronize Et</button>
                            </form>
                            <a class="btn btn-outline-info" href="/admin/providers.php?provider_id=<?= (int) $selectedProvider['id'] ?>&edit_id=<?= (int) $selectedProvider['id'] ?>"><i class="bi bi-pencil me-1"></i> Düzenle</a>
                            <form method="post" class="d-inline" onsubmit="return confirm('Bu sağlayıcıyı silmek istediğinize emin misiniz?');">
                                <input type="hidden" name="action" value="delete_provider">
                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $selectedProvider['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i> Sil</button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted text-uppercase small mb-2">Durum</div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge <?= $selectedProvider['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?> px-3"><?= $selectedProvider['status'] === 'active' ? 'Aktif' : 'Pasif' ?></span>
                                        <?php if (!empty($selectedProvider['last_synced_at'])): ?>
                                            <span class="small text-muted">Son senkronizasyon: <?= Helpers::sanitize(date('d.m.Y H:i', strtotime($selectedProvider['last_synced_at']))) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted text-uppercase small mb-2">Son Test</div>
                                    <?php if (!empty($selectedProvider['last_tested_at'])): ?>
                                        <div class="small"><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($selectedProvider['last_tested_at']))) ?></div>
                                    <?php else: ?>
                                        <div class="small text-muted">Henüz test yapılmamış.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($testDetails): ?>
                            <div class="alert alert-info mt-4">
                                <h6 class="fw-semibold">Test Yanıtı</h6>
                                <pre class="mb-0 small bg-white border rounded p-2"><?= Helpers::sanitize(json_encode($testDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                            </div>
                        <?php elseif (!empty($selectedProvider['last_test_response'])): ?>
                            <div class="alert alert-secondary mt-4">
                                <h6 class="fw-semibold">Son Test Sonucu</h6>
                                <pre class="mb-0 small bg-white border rounded p-2"><?= Helpers::sanitize($selectedProvider['last_test_response']) ?></pre>
                            </div>
                        <?php endif; ?>

                        <div class="alert alert-light border mt-4">
                            <h6 class="fw-semibold">Postman Örneği</h6>
                            <p class="small text-muted mb-2">Sağlayıcı uç noktalarını manuel test etmek için aşağıdaki isteği Postman veya benzeri bir araçta kullanabilirsiniz.</p>
                            <pre class="mb-0 small bg-white border rounded p-2">GET <?= Helpers::sanitize(rtrim($selectedProvider['api_url'], '/')) ?>/products?apikey=<?= Helpers::sanitize($selectedProvider['api_key'] !== '' ? $selectedProvider['api_key'] : 'API_KEYİNİZ') ?></pre>
                            <div class="small text-muted mt-2">Header alternatif: <code>X-API-Key: <?= Helpers::sanitize($selectedProvider['api_key'] !== '' ? $selectedProvider['api_key'] : 'API_KEYİNİZ') ?></code></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Sağlayıcı Ürünleri</h5>
                        <span class="badge bg-light text-dark">Toplam <?= $providerProducts ? count($providerProducts) : 0 ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (!$providerProducts): ?>
                            <p class="text-muted mb-0">Bu sağlayıcı için henüz ürün senkronize edilmedi.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Ürün</th>
                                        <th>Fiyat (₺)</th>
                                        <th>Stok</th>
                                        <th>Durum</th>
                                        <th>Bağlı Ürün</th>
                                        <th class="text-end">İşlem</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($providerProducts as $providerProduct): ?>
                                        <tr>
                                            <td><?= Helpers::sanitize($providerProduct['remote_id']) ?></td>
                                            <td><?= Helpers::sanitize($providerProduct['remote_title']) ?></td>
                                            <td><?= Helpers::sanitize(number_format((float) $providerProduct['remote_price'], 2, ',', '.')) ?></td>
                                            <td><?= (int) $providerProduct['remote_stock'] ?></td>
                                            <td>
                                                <span class="badge <?= !empty($providerProduct['remote_available']) ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= !empty($providerProduct['remote_available']) ? 'Satışta' : 'Pasif' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($providerProduct['product_id']) && !empty($providerProduct['local_name'])): ?>
                                                    <div class="fw-semibold"><?= Helpers::sanitize($providerProduct['local_name']) ?></div>
                                                    <div class="small text-muted">#<?= (int) $providerProduct['product_id'] ?></div>
                                                <?php else: ?>
                                                    <span class="text-muted">Eşlenmemiş</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if (empty($providerProduct['product_id'])): ?>
                                                    <form method="post" class="d-inline-flex align-items-center gap-2">
                                                        <input type="hidden" name="action" value="import_product">
                                                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                        <input type="hidden" name="provider_product_id" value="<?= (int) $providerProduct['id'] ?>">
                                                        <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                                                        <select name="category_id" class="form-select form-select-sm" required>
                                                            <option value="">Kategori</option>
                                                            <?php foreach ($flattenedCategories as $category): ?>
                                                                <option value="<?= (int) $category['id'] ?>"><?= str_repeat('— ', $category['depth']) . Helpers::sanitize($category['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-primary">İçe Aktar</button>
                                                    </form>
                                                <?php else: ?>
                                                    <a href="/admin/products.php#editProduct<?= (int) $providerProduct['product_id'] ?>" class="btn btn-sm btn-outline-secondary">Ürüne Git</a>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php Helpers::includeTemplate('footer.php');
