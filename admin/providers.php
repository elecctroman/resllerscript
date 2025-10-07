<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Services\ProviderSyncService;

Auth::requireRoles(array('super_admin', 'admin'));

$provider = ProviderSyncService::getSource();
$errors = array();
$successMessages = array();
$infoMessages = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        try {
            if ($action === 'save_credentials') {
                $result = ProviderSyncService::saveCredentials(array(
                    'base_url' => $_POST['base_url'] ?? '',
                    'consumer_key' => $_POST['consumer_key'] ?? '',
                    'consumer_secret' => $_POST['consumer_secret'] ?? '',
                    'status' => $_POST['status'] ?? 'inactive',
                ));
                if ($result['success']) {
                    $successMessages[] = $result['message'];
                } else {
                    $errors[] = $result['message'];
                }
            } elseif ($action === 'test_connection') {
                $result = ProviderSyncService::testConnection();
                if ($result['success']) {
                    $successMessages[] = $result['message'];
                } else {
                    $errors[] = $result['message'];
                }
            } elseif ($action === 'sync_categories') {
                $result = ProviderSyncService::syncCategories();
                if ($result['success']) {
                    $counts = isset($result['counts']) ? $result['counts'] : array();
                    $summary = sprintf('Kategoriler senkronize edildi. %d yeni, %d güncellendi.', $counts['created'] ?? 0, $counts['updated'] ?? 0);
                    $successMessages[] = $summary;
                } else {
                    $errors[] = $result['message'];
                }
            } elseif ($action === 'sync_products') {
                $result = ProviderSyncService::syncProducts();
                if ($result['success']) {
                    $counts = isset($result['counts']) ? $result['counts'] : array();
                    $summary = sprintf('Ürünler senkronize edildi. %d yeni, %d güncellendi.', $counts['created'] ?? 0, $counts['updated'] ?? 0);
                    $successMessages[] = $summary;
                    if (!empty($result['new_products'])) {
                        $infoMessages[] = sprintf('%d yeni ürün bulundu. Listeyi aşağıdan inceleyebilirsiniz.', count($result['new_products']));
                    }
                } else {
                    $errors[] = $result['message'];
                }
            } elseif ($action === 'import_product') {
                $remoteId = isset($_POST['remote_id']) ? (int) $_POST['remote_id'] : 0;
                if ($remoteId <= 0) {
                    $errors[] = 'İçeri aktarılacak ürün seçilemedi.';
                } else {
                    $result = ProviderSyncService::importProduct($remoteId, (int) $_SESSION['user']['id']);
                    if ($result['success']) {
                        $message = isset($result['message']) ? $result['message'] : 'Ürün içeri aktarıldı.';
                        if (!empty($result['product_id'])) {
                            $message .= ' <a href="/admin/products.php?highlight=' . (int) $result['product_id'] . '" class="text-decoration-none">Yeni ürünü düzenle</a>.';
                        }
                        $successMessages[] = $message;
                    } else {
                        $errors[] = $result['message'];
                    }
                }
            }
        } catch (RuntimeException $runtimeException) {
            $errors[] = $runtimeException->getMessage();
        } catch (Throwable $throwable) {
            $errors[] = 'İşlem sırasında beklenmeyen bir hata oluştu: ' . $throwable->getMessage();
        }
    }

    $provider = ProviderSyncService::getSource();
}

$remoteProducts = ProviderSyncService::listRemoteProducts();
$remoteCategories = ProviderSyncService::listRemoteCategories();
$highlightRemote = isset($_GET['highlight']) ? (int) $_GET['highlight'] : 0;

$pageTitle = 'Sağlayıcılar';
include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">WooCommerce Bağlantısı</h5>
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

                <?php foreach ($successMessages as $message): ?>
                    <div class="alert alert-success"><?= $message ?></div>
                <?php endforeach; ?>

                <?php foreach ($infoMessages as $message): ?>
                    <div class="alert alert-info"><?= Helpers::sanitize($message) ?></div>
                <?php endforeach; ?>

                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="save_credentials">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">

                    <div>
                        <label class="form-label">WordPress Site Adresi</label>
                        <input type="url" name="base_url" class="form-control" placeholder="https://ornekmagaza.com" value="<?= Helpers::sanitize($provider['base_url'] ?? '') ?>" required>
                        <small class="text-muted">WooCommerce kurulu sitenizin ana domain adresini girin.</small>
                    </div>

                    <div>
                        <label class="form-label">Consumer Key</label>
                        <input type="text" name="consumer_key" class="form-control" value="<?= Helpers::sanitize($provider['consumer_key'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label class="form-label">Consumer Secret</label>
                        <input type="text" name="consumer_secret" class="form-control" value="<?= Helpers::sanitize($provider['consumer_secret'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label class="form-label">Durum</label>
                        <select name="status" class="form-select">
                            <option value="inactive" <?= isset($provider['status']) && $provider['status'] !== 'active' ? 'selected' : '' ?>>Pasif</option>
                            <option value="active" <?= isset($provider['status']) && $provider['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                        <button type="submit" name="action" value="test_connection" class="btn btn-outline-secondary">Bağlantıyı Test Et</button>
                    </div>
                </form>

                <hr>

                <div class="vstack gap-2">
                    <form method="post">
                        <input type="hidden" name="action" value="sync_categories">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                        <button type="submit" class="btn btn-outline-primary w-100">Kategorileri Senkronize Et</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="action" value="sync_products">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                        <button type="submit" class="btn btn-outline-success w-100">Ürünleri Senkronize Et</button>
                    </form>
                </div>

                <hr>

                <h6>Adım Adım Entegrasyon</h6>
                <ol class="small ps-3">
                    <li>WooCommerce panelinizde <strong>WooCommerce &gt; Ayarlar &gt; Gelişmiş &gt; REST API</strong> alanına gidin.</li>
                    <li><em>Okuma / Yazma</em> izinli yeni bir anahtar oluşturun ve <strong>Consumer Key</strong> ile <strong>Secret</strong> değerlerini kopyalayın.</li>
                    <li>Bu sayfada site adresi ve anahtarları kaydedin, ardından bağlantıyı test edin.</li>
                    <li>Kategorileri ve ürünleri senkronize ettikten sonra yeni ürünler otomatik olarak duyuru şeklinde bildirilecektir.</li>
                </ol>

                <div class="alert alert-secondary small">
                    <strong>Postman Koleksiyonu:</strong><br>
                    Entegrasyonu manuel test etmek için <a href="/integrations/woocommerce/postman_collection.json" download>hazırlanan Postman koleksiyonunu</a> içeri aktarabilirsiniz.
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sağlayıcı Ürünleri</h5>
                <span class="text-muted small">Toplam <?= count($remoteProducts) ?> kayıt</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Ürün</th>
                            <th>Kategori</th>
                            <th>Fiyat</th>
                            <th>Stok</th>
                            <th>Durum</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$remoteProducts): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Henüz senkronize edilmiş ürün bulunmuyor.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($remoteProducts as $remote): ?>
                            <?php
                            $rowClasses = array();
                            if ($highlightRemote && (int) $remote['remote_id'] === $highlightRemote) {
                                $rowClasses[] = 'table-warning';
                            }
                            ?>
                            <tr class="<?= implode(' ', $rowClasses) ?>">
                                <td><?= (int) $remote['remote_id'] ?></td>
                                <td>
                                    <strong><?= Helpers::sanitize($remote['name'] ?? 'Ürün') ?></strong><br>
                                    <small class="text-muted">WooCommerce: <?= Helpers::sanitize($remote['slug'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($remote['mapped_category_name'])): ?>
                                        <span class="badge bg-secondary"><?= Helpers::sanitize($remote['mapped_category_name']) ?></span>
                                    <?php elseif (!empty($remote['remote_category_name'])): ?>
                                        <span class="text-muted"><?= Helpers::sanitize($remote['remote_category_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Kategori yok</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($remote['price']) && $remote['price'] !== null): ?>
                                        <?= Helpers::sanitize(number_format((float) $remote['price'], 2, ',', '.')) ?> <?= Helpers::sanitize($remote['currency'] ?? 'TRY') ?>
                                    <?php else: ?>
                                        <span class="text-muted">Belirtilmemiş</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($remote['stock_quantity'] !== null): ?>
                                        <span class="badge <?= (int) $remote['stock_quantity'] > 0 ? 'bg-success' : 'bg-danger' ?>"><?= (int) $remote['stock_quantity'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Bilinmiyor</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($remote['imported_product_id'])): ?>
                                        <span class="badge bg-success">İçe Aktarıldı</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Beklemede</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (empty($remote['imported_product_id'])): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                            <input type="hidden" name="action" value="import_product">
                                            <input type="hidden" name="remote_id" value="<?= (int) $remote['remote_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">İçeri Aktar</button>
                                        </form>
                                    <?php else: ?>
                                        <a href="/admin/products.php?highlight=<?= (int) $remote['imported_product_id'] ?>" class="btn btn-sm btn-outline-secondary">Ürünü Gör</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Kategori Eşleştirmeleri</h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sağlayıcı Kategorisi</th>
                            <th>Üst Kategori</th>
                            <th>Eşleşen Yerel Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$remoteCategories): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Henüz kategori senkronize edilmedi.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($remoteCategories as $category): ?>
                            <tr>
                                <td><?= (int) $category['remote_id'] ?></td>
                                <td><?= Helpers::sanitize($category['name'] ?? '-') ?></td>
                                <td><?= $category['parent_remote_id'] ? (int) $category['parent_remote_id'] : '-' ?></td>
                                <td>
                                    <?php if (!empty($category['mapped_category_name'])): ?>
                                        <span class="badge bg-secondary"><?= Helpers::sanitize($category['mapped_category_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Eşleşme yok</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
include __DIR__ . '/../templates/footer.php';
