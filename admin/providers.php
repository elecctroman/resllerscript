<?php
require __DIR__ . '/../bootstrap.php';

use App\AuditLog;
use App\Auth;
use App\Database;
use App\Helpers;
use App\Services\ProviderManager;

Auth::requireRoles(array('super_admin', 'admin'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();

$errors = array();
$success = Helpers::getFlash('providers.success', '');
$errorFlash = Helpers::getFlash('providers.error');
if ($errorFlash) {
    $errors[] = (string) $errorFlash;
}
$success = is_string($success) ? $success : '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Geçersiz istek. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        if ($action === 'create_provider') {
            $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            $codeInput = isset($_POST['code']) ? trim((string) $_POST['code']) : '';
            $baseUrl = isset($_POST['base_url']) ? trim((string) $_POST['base_url']) : '';
            $apiKey = isset($_POST['api_key']) ? trim((string) $_POST['api_key']) : '';
            $status = isset($_POST['status']) && $_POST['status'] === 'active' ? 'active' : 'inactive';
            $productsEndpoint = isset($_POST['products_endpoint']) ? trim((string) $_POST['products_endpoint']) : '/api/products';
            $ordersEndpoint = isset($_POST['orders_endpoint']) ? trim((string) $_POST['orders_endpoint']) : '/api/orders';


            $code = strtolower(str_replace(' ', '-', $codeInput));
            $code = preg_replace('/[^a-z0-9_-]/', '', $code);


            if ($code === '') {
                $errors[] = 'Sağlayıcı kodu yalnızca harf, rakam, tire ve alt çizgi içerebilir.';
            }

            if ($baseUrl === '') {
                $errors[] = 'API adresi zorunludur.';
            } elseif (!preg_match('/^https?:\/\//i', $baseUrl)) {
                $errors[] = 'API adresi http:// veya https:// ile başlamalıdır.';
            }

            if ($apiKey === '') {
                $errors[] = 'API anahtarı zorunludur.';
            }



            if (!$errors) {
                $check = $pdo->prepare('SELECT id FROM providers WHERE code = :code LIMIT 1');
                $check->execute(array('code' => $code));
                if ($check->fetch(PDO::FETCH_ASSOC)) {
                    $errors[] = 'Bu kod ile kayıtlı bir sağlayıcı zaten mevcut.';
                }
            }

            if (!$errors) {
                $settings = array(

                    'base_url' => rtrim($baseUrl),
                    'api_key' => $apiKey,
                    'status' => $status,
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ));

                $providerId = (int) $pdo->lastInsertId();

                AuditLog::record(
                    $currentUser['id'],
                    'provider.create',
                    'provider',
                    $providerId,
                    sprintf('Sağlayıcı eklendi: %s (%s)', $name, $code)
                );

                Helpers::redirectWithFlash('/admin/providers.php?provider_id=' . $providerId, array('providers.success' => 'Sağlayıcı başarıyla eklendi.'));
                exit;
            }
        } elseif ($action === 'update_provider') {
            $providerId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            $codeInput = isset($_POST['code']) ? trim((string) $_POST['code']) : '';
            $baseUrl = isset($_POST['base_url']) ? trim((string) $_POST['base_url']) : '';
            $apiKey = isset($_POST['api_key']) ? trim((string) $_POST['api_key']) : '';
            $status = isset($_POST['status']) && $_POST['status'] === 'active' ? 'active' : 'inactive';
            $productsEndpoint = isset($_POST['products_endpoint']) ? trim((string) $_POST['products_endpoint']) : '/api/products';
            $ordersEndpoint = isset($_POST['orders_endpoint']) ? trim((string) $_POST['orders_endpoint']) : '/api/orders';

            $code = strtolower(str_replace(' ', '-', $codeInput));
            $code = preg_replace('/[^a-z0-9_-]/', '', $code);

            if ($providerId <= 0) {
                $errors[] = 'Düzenlenecek sağlayıcı bulunamadı.';
            }

            $provider = $providerId > 0 ? ProviderManager::find($providerId) : null;
            if (!$provider) {
                $errors[] = 'Sağlayıcı kaydı bulunamadı.';
            }


            if ($code === '') {
                $errors[] = 'Sağlayıcı kodu yalnızca harf, rakam, tire ve alt çizgi içerebilir.';
            }

            if ($baseUrl === '') {
                $errors[] = 'API adresi zorunludur.';
            } elseif (!preg_match('/^https?:\/\//i', $baseUrl)) {
                $errors[] = 'API adresi http:// veya https:// ile başlamalıdır.';
            }

            if ($apiKey === '') {
                $errors[] = 'API anahtarı zorunludur.';
            }



            if (!$errors) {
                $check = $pdo->prepare('SELECT id FROM providers WHERE code = :code AND id <> :id LIMIT 1');
                $check->execute(array('code' => $code, 'id' => $providerId));
                if ($check->fetch(PDO::FETCH_ASSOC)) {
                    $errors[] = 'Bu kod farklı bir sağlayıcı tarafından kullanılıyor.';
                }
            }

            if (!$errors) {
                $settings = array(

                $stmt->execute(array(
                    'id' => $providerId,
                    'name' => $name,
                    'code' => $code,

                    'base_url' => rtrim($baseUrl),
                    'api_key' => $apiKey,
                    'status' => $status,
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ));

                AuditLog::record(
                    $currentUser['id'],
                    'provider.update',
                    'provider',
                    $providerId,
                    sprintf('Sağlayıcı güncellendi: %s (%s)', $name, $code)
                );

                Helpers::redirectWithFlash('/admin/providers.php?provider_id=' . $providerId, array('providers.success' => 'Sağlayıcı güncellendi.'));
                exit;
            }
        } elseif ($action === 'delete_provider') {
            $providerId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            if ($providerId <= 0) {
                $errors[] = 'Silinecek sağlayıcı seçilemedi.';
            } else {
                $provider = ProviderManager::find($providerId);
                if (!$provider) {
                    $errors[] = 'Sağlayıcı kaydı bulunamadı.';
                } else {
                    $code = isset($provider['code']) ? (string) $provider['code'] : '';
                    if ($code !== '') {
                        $usageCheck = $pdo->prepare('SELECT COUNT(*) FROM products WHERE provider_code = :code');
                        $usageCheck->execute(array('code' => $code));
                        $usage = (int) $usageCheck->fetchColumn();
                        if ($usage > 0) {
                            $errors[] = 'Bu sağlayıcıya bağlı ' . $usage . ' ürün bulunduğu için silinemez.';
                        }
                    }

                    if (!$errors) {
                        $delete = $pdo->prepare('DELETE FROM providers WHERE id = :id');
                        $delete->execute(array('id' => $providerId));

                        AuditLog::record(
                            $currentUser['id'],
                            'provider.delete',
                            'provider',
                            $providerId,
                            sprintf('Sağlayıcı silindi: %s', isset($provider['name']) ? $provider['name'] : ('#' . $providerId))
                        );

                        Helpers::redirectWithFlash('/admin/providers.php', array('providers.success' => 'Sağlayıcı kaldırıldı.'));
                        exit;
                    }
                }
            }
        } elseif ($action === 'sync_products') {
            $providerId = isset($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
            $provider = $providerId > 0 ? ProviderManager::find($providerId) : null;
            if (!$provider) {
                $errors[] = 'Senkronize edilecek sağlayıcı bulunamadı.';
            } else {
                $syncResult = ProviderManager::syncProducts($provider);
                if (!empty($syncResult['success'])) {
                    $count = isset($syncResult['count']) ? (int) $syncResult['count'] : 0;
                    Helpers::redirectWithFlash('/admin/providers.php?provider_id=' . $providerId, array('providers.success' => sprintf('Sağlayıcı ürünleri senkronize edildi. Toplam %d kayıt güncellendi.', $count)));
                    exit;
                }

                $errors[] = isset($syncResult['error']) ? (string) $syncResult['error'] : 'Senkronizasyon başarısız oldu.';
            }
        } elseif ($action === 'import_products') {
            $providerId = isset($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
            $selectedProducts = isset($_POST['selected_products']) && is_array($_POST['selected_products']) ? $_POST['selected_products'] : array();
            $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
            $markupPercent = isset($_POST['markup_percent']) ? (float) $_POST['markup_percent'] : 0.0;
            $activateProducts = isset($_POST['activate_products']) ? (int) $_POST['activate_products'] === 1 : true;

            $provider = $providerId > 0 ? ProviderManager::find($providerId) : null;
            if (!$provider) {
                $errors[] = 'İçe aktarım için sağlayıcı bulunamadı.';
            } else {
                $result = ProviderManager::importProducts($provider, $selectedProducts, $categoryId, $markupPercent, $activateProducts);
                if (!empty($result['success'])) {
                    $imported = isset($result['imported']) && is_array($result['imported']) ? $result['imported'] : array();
                    $skipped = isset($result['skipped']) && is_array($result['skipped']) ? $result['skipped'] : array();
                    $importedCount = count($imported);
                    $messageParts = array();

                    if ($importedCount > 0) {
                        foreach ($imported as $importedProduct) {
                            AuditLog::record(
                                $currentUser['id'],
                                'product.import',
                                'product',
                                isset($importedProduct['id']) ? (int) $importedProduct['id'] : 0,
                                sprintf('Sağlayıcı ürünü içe aktarıldı: %s (%s)', isset($importedProduct['name']) ? $importedProduct['name'] : 'Ürün', isset($provider['name']) ? $provider['name'] : (string) $providerId)
                            );
                        }

                        $messageParts[] = sprintf('%d ürün içe aktarıldı.', $importedCount);
                    }

                    if (!empty($skipped)) {
                        $messageParts[] = sprintf('%d ürün zaten ekli olduğu için atlandı.', count($skipped));
                    }

                    if (!empty($result['errors'])) {
                        foreach ($result['errors'] as $importError) {
                            $errors[] = (string) $importError;
                        }
                    }

                    if ($messageParts) {
                        Helpers::redirectWithFlash('/admin/providers.php?provider_id=' . $providerId, array('providers.success' => implode(' ', $messageParts)));
                        exit;
                    }

                    if (!$errors) {
                        Helpers::redirectWithFlash('/admin/providers.php?provider_id=' . $providerId, array('providers.success' => 'Seçilen ürünler içe aktarıldı.'));
                        exit;
                    }
                } else {
                    $errors[] = isset($result['error']) ? (string) $result['error'] : 'Ürünler içe aktarılırken hata oluştu.';
                }
            }

        }
    }
}

$providers = ProviderManager::all();
$selectedProviderId = isset($_GET['provider_id']) ? (int) $_GET['provider_id'] : 0;
$selectedProvider = $selectedProviderId > 0 ? ProviderManager::find($selectedProviderId) : null;
if (!$selectedProvider && $selectedProviderId > 0) {
    $errors[] = 'Seçilen sağlayıcı bulunamadı.';
}

$providerProducts = $selectedProvider ? ProviderManager::cachedProducts((int) $selectedProvider['id']) : array();

$categoryStmt = $pdo->query('SELECT id, parent_id, name FROM categories ORDER BY name ASC');
$categoryRows = $categoryStmt ? $categoryStmt->fetchAll(PDO::FETCH_ASSOC) : array();
$categoryMap = array();
foreach ($categoryRows as $category) {
    $categoryMap[(int) $category['id']] = array(
        'id' => (int) $category['id'],
        'parent_id' => isset($category['parent_id']) ? (int) $category['parent_id'] : null,
        'name' => isset($category['name']) ? (string) $category['name'] : '',
    );
}

$categoryChildren = array();
foreach ($categoryMap as $category) {
    $parentId = $category['parent_id'] ? (int) $category['parent_id'] : 0;
    if (!isset($categoryChildren[$parentId])) {
        $categoryChildren[$parentId] = array();
    }
    $categoryChildren[$parentId][] = $category;
}

foreach ($categoryChildren as &$childList) {
    usort($childList, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
}
unset($childList);

$flattenedCategories = array();
$walker = function ($parentId, $depth) use (&$walker, &$flattenedCategories, $categoryChildren) {
    if (!isset($categoryChildren[$parentId])) {
        return;
    }

    foreach ($categoryChildren[$parentId] as $category) {
        $flattenedCategories[] = array(
            'id' => $category['id'],
            'name' => $category['name'],
            'depth' => $depth,
        );

        $walker($category['id'], $depth + 1);
    }
};
$walker(0, 0);

$pageTitle = 'Sağlayıcılar';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Sağlayıcı</h5>
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
                    <div class="alert alert-success mb-3"><?= Helpers::sanitize($success) ?></div>
                <?php endif; ?>


                    <button type="submit" class="btn btn-primary">Sağlayıcıyı Kaydet</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Kayıtlı Sağlayıcılar</h5>
                <small class="text-muted">Senkronizasyonları buradan başlatabilirsiniz.</small>
            </div>
            <div class="card-body p-0">
                <?php if (!$providers): ?>
                    <div class="p-4 text-muted">Henüz sağlayıcı eklenmemiş. Yeni sağlayıcı ekleyerek başlayın.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Adı</th>
                                    <th>Kod</th>

                                    <th>Son Senkron</th>
                                    <th class="text-end">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($providers as $providerItem): ?>
                                    <tr>

                                        <td>
                                            <strong><?= Helpers::sanitize(isset($providerItem['name']) ? $providerItem['name'] : 'Sağlayıcı') ?></strong>
                                        </td>
                                        <td><code><?= Helpers::sanitize(isset($providerItem['code']) ? $providerItem['code'] : '') ?></code></td>
                                        <td>

                                            <?php if (isset($providerItem['status']) && $providerItem['status'] === 'active'): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= Helpers::sanitize(isset($providerItem['base_url']) ? $providerItem['base_url'] : '#') ?>" target="_blank" rel="noopener noreferrer">
                                                <?= Helpers::sanitize(isset($providerItem['base_url']) ? $providerItem['base_url'] : '') ?>
                                            </a>
                                        </td>
                                        <td>

                                            <?php if (!empty($providerItem['last_synced_at'])): ?>
                                                <span class="text-muted small"><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($providerItem['last_synced_at']))) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="/admin/providers.php?provider_id=<?= (int) $providerItem['id'] ?>" class="btn btn-sm btn-outline-secondary">Ürünler</a>
                                            <form method="post" class="d-inline">

                                                <input type="hidden" name="action" value="sync_products">
                                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                <input type="hidden" name="provider_id" value="<?= (int) $providerItem['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Senkronize Et</button>
                                            </form>
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProvider<?= (int) $providerItem['id'] ?>">Düzenle</button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Sağlayıcıyı silmek istediğinize emin misiniz?');">
                                                <input type="hidden" name="action" value="delete_provider">
                                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $providerItem['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editProvider<?= (int) $providerItem['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content">

                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Sağlayıcıyı Düzenle</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update_provider">
                                                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                        <input type="hidden" name="id" value="<?= (int) $providerItem['id'] ?>">

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                                        <button type="submit" class="btn btn-primary">Kaydet</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($selectedProvider): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Sağlayıcı Ürünleri</h5>
                <small class="text-muted"><?= Helpers::sanitize(isset($selectedProvider['name']) ? $selectedProvider['name'] : 'Sağlayıcı') ?> için en son çekilen ürünler.</small>
            </div>
            <form method="post" class="d-inline">
                <input type="hidden" name="action" value="sync_products">
                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                <button type="submit" class="btn btn-outline-primary">Tekrar Senkronize Et</button>
            </form>
        </div>
        <div class="card-body">
            <?php if (!$providerProducts): ?>
                <div class="text-muted">Bu sağlayıcı için henüz ürün alınmamış. Önce senkronizasyon yapın.</div>
            <?php else: ?>
                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="import_products">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <input type="hidden" name="provider_id" value="<?= (int) $selectedProvider['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Kategori</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Kategori seçin</option>
                                <?php foreach ($flattenedCategories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>"><?= str_repeat('— ', (int) $category['depth']) . Helpers::sanitize($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ekstra Marj (%)</label>
                            <input type="number" name="markup_percent" step="0.01" class="form-control" value="0" placeholder="Örn: 10">
                            <small class="text-muted">Varsayılan fiyatlandırmanın üzerine ek marj uygulamak için.</small>
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="activateProducts" name="activate_products" value="1" checked>
                                <label class="form-check-label" for="activateProducts">Ürünleri aktif olarak ekle</label>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th><input type="checkbox" id="selectAllProviderProducts"></th>
                                    <th>Sağlayıcı ID</th>
                                    <th>Ürün</th>
                                    <th>Fiyat</th>
                                    <th>Stok</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($providerProducts as $productRow): ?>
                                    <?php
                                    $externalId = isset($productRow['external_id']) ? (string) $productRow['external_id'] : '';
                                    $isAvailable = isset($productRow['is_available']) ? (int) $productRow['is_available'] === 1 : false;
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input" name="selected_products[]" value="<?= Helpers::sanitize($externalId) ?>" data-provider-product-checkbox></td>
                                        <td><code><?= Helpers::sanitize($externalId) ?></code></td>
                                        <td>
                                            <strong><?= Helpers::sanitize(isset($productRow['name']) ? $productRow['name'] : 'Ürün') ?></strong>
                                            <?php if (!empty($productRow['description'])): ?>
                                                <div class="text-muted small"><?= Helpers::sanitize($productRow['description']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($productRow['price'] !== null && $productRow['price'] !== ''): ?>
                                                <span class="badge bg-light text-dark">
                                                    <?= Helpers::sanitize(number_format((float) $productRow['price'], 2, ',', '.')) ?>
                                                    <?= Helpers::sanitize(isset($productRow['currency']) ? $productRow['currency'] : 'TRY') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= Helpers::sanitize(isset($productRow['stock']) ? (string) $productRow['stock'] : '-') ?></td>
                                        <td>
                                            <?php if ($isAvailable): ?>
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
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Toplam <?= count($providerProducts) ?> ürün listeleniyor.</span>
                        <button type="submit" class="btn btn-primary">Seçili Ürünleri İçe Aktar</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php
$GLOBALS['pageInlineScripts'][] = <<<JS
    document.addEventListener('DOMContentLoaded', function () {
        var master = document.getElementById('selectAllProviderProducts');
        if (master) {
            master.addEventListener('change', function () {
                var targets = document.querySelectorAll('[data-provider-product-checkbox]');
                targets.forEach(function (checkbox) {
                    checkbox.checked = master.checked;
                });
            });
        }


    });
JS;
include __DIR__ . '/../templates/footer.php';
