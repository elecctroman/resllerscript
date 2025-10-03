<?php
require __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Logger;
use App\Migrations\Schema;
use App\LotusClient;
use App\LotusClientCurl;

Auth::requireRoles(array('super_admin', 'admin'));

Schema::ensure();

$pdo = Database::connection();

$providerStmt = $pdo->prepare('SELECT * FROM providers WHERE name = :name LIMIT 1');
$providerStmt->execute(array(':name' => 'Lotus'));
$provider = $providerStmt->fetch();

$apiUrl = $provider ? trim((string) $provider['api_url']) : '';
$apiKey = $provider ? trim((string) $provider['api_key']) : '';
$timeoutMs = $provider && isset($provider['timeout_ms']) ? (int) $provider['timeout_ms'] : 20000;
$connectTimeoutMs = min($timeoutMs, 10000);

$logger = new Logger(__DIR__ . '/../../storage/lotus.log');

$errors = array();
$products = array();
$existing = array();
$categories = array();

$categoryStmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
$categories = $categoryStmt->fetchAll();

$mapStmt = $pdo->query('SELECT lotus_product_id, local_product_id FROM lotus_products_map');
foreach ($mapStmt->fetchAll() as $row) {
    $existing[(int) $row['lotus_product_id']] = (int) $row['local_product_id'];
}

if ($apiUrl === '' || $apiKey === '') {
    $errors[] = 'Lotus API bilgileri eksik. Lütfen önce ayarlar sayfasından yapılandırın.';
} else {
    try {
        if (class_exists('GuzzleHttp\\Client')) {
            $client = new LotusClient($apiUrl, $apiKey, $timeoutMs, $connectTimeoutMs, $logger);
        } else {
            $client = new LotusClientCurl($apiUrl, $apiKey, $timeoutMs, $connectTimeoutMs, $logger);
        }

        $response = $client->getProducts();
        if (!isset($response['success']) || !$response['success']) {
            $message = isset($response['message']) ? (string) $response['message'] : 'Sağlayıcıdan yanıt alınamadı.';
            $errors[] = 'Ürün listesi alınamadı: ' . $message;
        } else {
            $products = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
        }
    } catch (\Throwable $exception) {
        $errors[] = 'Sağlayıcı API isteği başarısız: ' . $exception->getMessage();
        $logger->error('Lotus ürün listesi alınamadı: ' . $exception->getMessage());
    }
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if ($search !== '' && $products) {
    $needle = mb_strtolower($search, 'UTF-8');
    $products = array_values(array_filter($products, static function ($item) use ($needle) {
        $title = isset($item['title']) ? (string) $item['title'] : '';
        return mb_strpos(mb_strtolower($title, 'UTF-8'), $needle) !== false;
    }));
}

$GLOBALS['pageScripts'] = isset($GLOBALS['pageScripts']) && is_array($GLOBALS['pageScripts']) ? $GLOBALS['pageScripts'] : array();
$GLOBALS['pageScripts'][] = '/public/assets/js/providers.js';

$pageTitle = 'Lotus Ürünleri';

include __DIR__ . '/../../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <h5 class="mb-0">Lotus Ürünleri</h5>
                    <small class="text-muted">Sağlayıcıdaki ürünleri görüntüleyin ve panelinize aktarın.</small>
                </div>
                <form class="d-flex gap-2" method="get" action="">
                    <input type="search" class="form-control" name="q" placeholder="Ürün ara" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-outline-secondary">Ara</button>
                </form>
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

                <?php if (!$categories): ?>
                    <div class="alert alert-warning mb-0">Ürün aktarımı yapabilmek için önce en az bir kategori oluşturmalısınız.</div>
                <?php elseif (!$products): ?>
                    <p class="text-muted mb-0">Gösterilecek ürün bulunamadı.</p>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="select-all-products" data-select-all>
                            <label class="form-check-label" for="select-all-products">Tümünü Seç</label>
                        </div>
                        <button type="button" class="btn btn-success btn-sm" id="bulk-import-button" data-action="open-bulk" disabled>Seçilenleri Ekle</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="lotus-products-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Başlık</th>
                                    <th>Lotus ID</th>
                                    <th>Fiyat</th>
                                    <th>Durum</th>
                                    <th class="text-end">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <?php
                                    $lotusId = isset($product['id']) ? (int) $product['id'] : 0;
                                    $title = isset($product['title']) ? (string) $product['title'] : 'Başlıksız';
                                    $amount = isset($product['amount']) ? (float) $product['amount'] : 0.0;
                                    $statusBadge = 'badge bg-secondary';
                                    $statusLabel = 'Beklemede';
                                    if (!empty($product['available'])) {
                                        $statusBadge = 'badge bg-success';
                                        $statusLabel = 'Aktif';
                                    }
                                    $already = isset($existing[$lotusId]) && $existing[$lotusId] > 0;
                                    $rowData = htmlspecialchars(json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr data-product='<?= $rowData ?>' data-lotus-id="<?= $lotusId ?>" data-imported="<?= $already ? '1' : '0' ?>">
                                        <td>
                                            <input type="checkbox" class="form-check-input" value="<?= $lotusId ?>" <?= $already ? 'disabled' : '' ?> data-product-select>
                                        </td>
                                        <td>
                                            <strong><?= Helpers::sanitize($title) ?></strong>
                                            <?php if ($already): ?>
                                                <span class="badge bg-info ms-2">Zaten eklendi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= Helpers::sanitize((string) $lotusId) ?></code></td>
                                        <td><?= Helpers::sanitize(number_format($amount, 2)) ?> USD</td>
                                        <td><span class="<?= $statusBadge ?>"><?= Helpers::sanitize($statusLabel) ?></span></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-action="import-single" data-lotus-id="<?= $lotusId ?>" <?= $already ? 'disabled' : '' ?>>Ekle</button>
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

<div class="modal fade" id="singleImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="single-import-form" autocomplete="off">
                <input type="hidden" name="action" value="import_single">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Helpers::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="lotus_product_id" id="single-lotus-id">
                <input type="hidden" name="snapshot" id="single-snapshot">
                <div class="modal-header">
                    <h5 class="modal-title">Lotus Ürününü Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="single-title" class="form-label">Ürün Başlığı</label>
                        <input type="text" class="form-control" id="single-title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="single-category" class="form-label">Kategori</label>
                        <select class="form-select" id="single-category" name="category_id" required>
                            <option value="" disabled selected>Seçin</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"><?= Helpers::sanitize($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="single-price" class="form-label">Satış Fiyatı (USD)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="single-price" name="price" required>
                        <div class="form-text">Varsayılan olarak Lotus fiyatı atanır, dilerseniz güncelleyebilirsiniz.</div>
                    </div>
                    <div class="mb-3">
                        <label for="single-description" class="form-label">Ürün Açıklaması</label>
                        <textarea class="form-control" rows="4" id="single-description" name="description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary">Ürünü Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="bulk-import-form" autocomplete="off">
                <input type="hidden" name="action" value="import_bulk">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Helpers::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="items" id="bulk-items">
                <div class="modal-header">
                    <h5 class="modal-title">Seçilen Ürünleri Aktar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="bulk-category" class="form-label">Kategori</label>
                        <select class="form-select" id="bulk-category" name="category_id" required>
                            <option value="" disabled selected>Seçin</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"><?= Helpers::sanitize($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fiyatlandırma</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="pricing_mode" id="pricing-copy" value="copy" checked>
                            <label class="form-check-label" for="pricing-copy">Lotus fiyatını aynen kullan</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="pricing_mode" id="pricing-percentage" value="percentage">
                            <label class="form-check-label" for="pricing-percentage">Yüzde kar marjı ekle</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="pricing_mode" id="pricing-fixed" value="fixed">
                            <label class="form-check-label" for="pricing-fixed">Sabit tutar ekle</label>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label for="bulk-percentage" class="form-label">Kar Marjı (%)</label>
                            <input type="number" step="0.01" class="form-control" id="bulk-percentage" name="percentage" value="10">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="bulk-fixed" class="form-label">Sabit Ek Tutar</label>
                            <input type="number" step="0.01" class="form-control" id="bulk-fixed" name="fixed" value="0">
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="bulk-skip-existing" name="skip_existing" value="1" checked>
                        <label class="form-check-label" for="bulk-skip-existing">Zaten eklenmiş ürünleri atla</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-success">Aktarımı Başlat</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php';
