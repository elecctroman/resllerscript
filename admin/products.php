<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Currency;
use App\Database;
use App\Helpers;
use App\Settings;

Auth::requireRoles(array('super_admin', 'admin', 'content'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
$errors = array();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Geçersiz istek. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        if ($action === 'create_product') {
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            $costInput = isset($_POST['cost_price_try']) ? trim($_POST['cost_price_try']) : '';
            $sku = isset($_POST['sku']) ? trim($_POST['sku']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $status = isset($_POST['status']) ? 'active' : 'inactive';
            $providerCode = isset($_POST['provider_code']) ? strtolower(trim($_POST['provider_code'])) : '';
            $providerProductId = isset($_POST['provider_product_id']) ? trim($_POST['provider_product_id']) : '';

            $costSanitized = preg_replace('/[^0-9.,-]/', '', $costInput);
            $costSanitized = str_replace(',', '.', (string)$costSanitized);
            $costPriceTry = $costSanitized !== '' ? (float)$costSanitized : 0.0;
            if ($costPriceTry < 0) {
                $costPriceTry = 0.0;
            }

            if ($name === '' || $categoryId <= 0) {
                $errors[] = 'Ürün adı ve kategorisi zorunludur.';
            }

            if ($costPriceTry <= 0) {
                $errors[] = 'Alış fiyatı 0’dan büyük olmalıdır.';
            }

            if ($providerCode !== '' && $providerCode !== 'lotus') {
                $errors[] = 'Desteklenmeyen sağlayıcı seçildi.';
            }

            if ($providerCode !== '' && $providerProductId === '') {
                $errors[] = 'Sağlayıcı ürün kimliği zorunludur.';
            }

            if (!$errors) {
                $salePrice = Helpers::priceFromCostTry($costPriceTry);

                $stmt = $pdo->prepare('INSERT INTO products (name, category_id, cost_price_try, price, description, sku, status, provider_code, provider_product_id, created_at) VALUES (:name, :category_id, :cost_price_try, :price, :description, :sku, :status, :provider_code, :provider_product_id, NOW())');
                $stmt->execute(array(
                    'name' => $name,
                    'category_id' => $categoryId,
                    'cost_price_try' => $costPriceTry,
                    'price' => $salePrice,
                    'description' => $description !== '' ? $description : null,
                    'sku' => $sku !== '' ? $sku : null,
                    'status' => $status,
                    'provider_code' => $providerCode !== '' ? $providerCode : null,
                    'provider_product_id' => $providerProductId !== '' ? $providerProductId : null,
                ));

                $success = 'Ürün kaydedildi.';

                AuditLog::record(
                    $currentUser['id'],
                    'product.create',
                    'product',
                    (int)$pdo->lastInsertId(),
                    sprintf('Ürün eklendi: %s', $name)
                );
            }
        } elseif ($action === 'update_product') {
            $productId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            $costInput = isset($_POST['cost_price_try']) ? trim($_POST['cost_price_try']) : '';
            $sku = isset($_POST['sku']) ? trim($_POST['sku']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $status = isset($_POST['status']) ? 'active' : 'inactive';
            $providerCode = isset($_POST['provider_code']) ? strtolower(trim($_POST['provider_code'])) : '';
            $providerProductId = isset($_POST['provider_product_id']) ? trim($_POST['provider_product_id']) : '';

            $costSanitized = preg_replace('/[^0-9.,-]/', '', $costInput);
            $costSanitized = str_replace(',', '.', (string)$costSanitized);
            $costPriceTry = $costSanitized !== '' ? (float)$costSanitized : 0.0;
            if ($costPriceTry < 0) {
                $costPriceTry = 0.0;
            }

            if ($productId <= 0 || $name === '' || $categoryId <= 0) {
                $errors[] = 'Geçersiz ürün bilgisi gönderildi.';
            }

            if ($costPriceTry <= 0) {
                $errors[] = 'Alış fiyatı 0’dan büyük olmalıdır.';
            }

            if ($providerCode !== '' && $providerCode !== 'lotus') {
                $errors[] = 'Desteklenmeyen sağlayıcı seçildi.';
            }

            if ($providerCode !== '' && $providerProductId === '') {
                $errors[] = 'Sağlayıcı ürün kimliği zorunludur.';
            }

            if (!$errors) {
                $salePrice = Helpers::priceFromCostTry($costPriceTry);

                $stmt = $pdo->prepare('UPDATE products SET name = :name, category_id = :category_id, cost_price_try = :cost_price_try, price = :price, description = :description, sku = :sku, status = :status, provider_code = :provider_code, provider_product_id = :provider_product_id, updated_at = NOW() WHERE id = :id');
                $stmt->execute(array(
                    'id' => $productId,
                    'name' => $name,
                    'category_id' => $categoryId,
                    'cost_price_try' => $costPriceTry,
                    'price' => $salePrice,
                    'description' => $description !== '' ? $description : null,
                    'sku' => $sku !== '' ? $sku : null,
                    'status' => $status,
                    'provider_code' => $providerCode !== '' ? $providerCode : null,
                    'provider_product_id' => $providerProductId !== '' ? $providerProductId : null,
                ));

                $success = 'Ürün güncellendi.';

                AuditLog::record(
                    $currentUser['id'],
                    'product.update',
                    'product',
                    $productId,
                    sprintf('Ürün güncellendi: %s', $name)
                );
            }
        } elseif ($action === 'delete_product') {
            $productId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($productId > 0) {
                $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
                $stmt->execute(array('id' => $productId));

                $success = 'Ürün silindi.';

                AuditLog::record(
                    $currentUser['id'],
                    'product.delete',
                    'product',
                    $productId,
                    sprintf('Ürün silindi: #%d', $productId)
                );
            }
        }
    }
}

$categories = $pdo->query('SELECT id, parent_id, name FROM categories ORDER BY name ASC')->fetchAll();
$categoryMap = array();
foreach ($categories as $category) {
    $categoryMap[(int)$category['id']] = array(
        'id' => (int)$category['id'],
        'parent_id' => isset($category['parent_id']) ? (int)$category['parent_id'] : null,
        'name' => isset($category['name']) ? (string)$category['name'] : '',
    );
}

$categoryChildren = array();
foreach ($categoryMap as $category) {
    $parentId = $category['parent_id'] ? (int)$category['parent_id'] : 0;
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

$categoryPath = function ($categoryId) use (&$categoryMap) {
    $parts = array();
    $currentId = $categoryId;
    $guard = 0;

    while ($currentId && isset($categoryMap[$currentId]) && $guard < 20) {
        $parts[] = $categoryMap[$currentId]['name'];
        $currentId = $categoryMap[$currentId]['parent_id'];
        $guard++;
    }

    if (!$parts) {
        return 'Genel';
    }

    return implode(' / ', array_reverse($parts));
};

$products = $pdo->query('SELECT pr.*, cat.name AS category_name, (SELECT COUNT(*) FROM product_stock_items psi WHERE psi.product_id = pr.id AND psi.status = "available") AS available_stock FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id ORDER BY pr.created_at DESC')->fetchAll();

$rate = Currency::getRate('TRY', 'USD');
$tryPerUsd = $rate > 0 ? 1 / $rate : null;
$rateUpdatedAt = Settings::get('currency_rate_TRY_USD_updated');

$pageTitle = 'Ürünler';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Ürün</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Alış fiyatını TL olarak girin. Sistem güncel kur ve %<?= Helpers::sanitize(number_format(Helpers::commissionRate(), 2, ',', '.')) ?> komisyon oranını kullanarak USD satış fiyatını hesaplar.</p>

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

                <?php if (!$flattenedCategories): ?>
                    <div class="alert alert-warning">Ürün ekleyebilmek için önce <a href="/admin/categories.php" class="alert-link">kategori oluşturmanız</a> gerekir.</div>
                <?php endif; ?>

                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="create_product">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <div>
                        <label class="form-label">Ürün Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Kategori</label>
                        <select name="category_id" class="form-select" <?= $flattenedCategories ? '' : 'disabled' ?> required>
                            <option value="">Kategori seçin</option>
                            <?php foreach ($flattenedCategories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>"><?= str_repeat('— ', $category['depth']) . Helpers::sanitize($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Alış Fiyatı (₺)</label>
                        <input type="number" name="cost_price_try" step="0.01" min="0" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" placeholder="Opsiyonel">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Sağlayıcı</label>
                            <select name="provider_code" class="form-select">
                                <option value="">Panel (Stok Teslimatı)</option>
                            </select>
                            <small class="text-muted">"Stok" seçeneği fiziksel stoğu kullanır. Harici sağlayıcılar şu anda devre dışıdır.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sağlayıcı Ürün ID</label>
                            <input type="text" name="provider_product_id" class="form-control" placeholder="Örn: 57">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Opsiyonel"></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="createProductStatus" name="status" checked>
                        <label class="form-check-label" for="createProductStatus">Ürün aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary" <?= $flattenedCategories ? '' : 'disabled' ?>>Ürünü Kaydet</button>
                </form>
            </div>
            <div class="card-footer bg-white">
                <div class="small text-muted">
                    <div>Kur referansı: <?php if ($tryPerUsd): ?>1 USD ≈ <?= Helpers::sanitize(number_format($tryPerUsd, 2, ',', '.')) ?> ₺<?php else: ?>-<?php endif; ?></div>
                    <div>Son güncelleme: <?php if ($rateUpdatedAt): ?><?= Helpers::sanitize(date('d.m.Y H:i', (int)$rateUpdatedAt)) ?><?php else: ?>-<?php endif; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Ürün Listesi</h5>
                <a href="/admin/categories.php" class="btn btn-sm btn-outline-secondary">Kategorileri Yönet</a>
            </div>
            <div class="card-body">
                <?php if (!$products): ?>
                    <p class="text-muted mb-0">Henüz ürün bulunmuyor.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Ürün</th>
                                <th>Kategori</th>
                                <th>Alış Fiyatı (₺)</th>
                                <th>Satış Fiyatı ($)</th>
                                <th>Stok</th>
                                <th>Durum</th>
                                <th class="text-end">İşlemler</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= (int)$product['id'] ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($product['name']) ?></strong><br>
                                        <small class="text-muted">SKU: <?= Helpers::sanitize(isset($product['sku']) ? $product['sku'] : '-') ?></small>
                                        <?php if (!empty($product['provider_code'])): ?>
                                            <div class="text-muted small">Sağlayıcı: <?= Helpers::sanitize(strtoupper($product['provider_code'])) ?><?php if (!empty($product['provider_product_id'])): ?> #<?= Helpers::sanitize($product['provider_product_id']) ?><?php endif; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Helpers::sanitize($categoryPath((int)$product['category_id'])) ?></td>
                                    <td><?= isset($product['cost_price_try']) ? Helpers::sanitize(number_format((float)$product['cost_price_try'], 2, ',', '.')) : '-' ?></td>
                                    <td><?= Helpers::sanitize(number_format((float)$product['price'], 2, '.', ',')) ?></td>
                                    <td>
                                        <?php
                                        $provider = isset($product['provider_code']) ? strtolower((string)$product['provider_code']) : '';
                                        if ($provider === 'lotus') {
                                            echo '<span class="badge bg-info">Sağlayıcı</span>';
                                        } else {
                                            $availableStock = isset($product['available_stock']) ? (int)$product['available_stock'] : 0;
                                            if ($availableStock > 0) {
                                                echo '<span class="badge bg-success">' . (int)$availableStock . '</span>';
                                            } else {
                                                echo '<span class="badge bg-warning text-dark">Stok Yok</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($product['status'] === 'active'): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary" href="/admin/product-stock.php?product_id=<?= (int)$product['id'] ?>">Stok</a>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProduct<?= (int)$product['id'] ?>">Düzenle</button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Ürünü silmek istediğinize emin misiniz?');">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editProduct<?= (int)$product['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Ürün Düzenle</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update_product">
                                                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Ürün Adı</label>
                                                            <input type="text" name="name" class="form-control" value="<?= Helpers::sanitize($product['name']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Kategori</label>
                                                            <select name="category_id" class="form-select" required>
                                                                <?php foreach ($flattenedCategories as $category): ?>
                                                                    <option value="<?= (int)$category['id'] ?>" <?= $product['category_id'] == $category['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $category['depth']) . Helpers::sanitize($category['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Alış Fiyatı (₺)</label>
                                                            <input type="number" name="cost_price_try" step="0.01" min="0" class="form-control" value="<?= Helpers::sanitize(isset($product['cost_price_try']) ? $product['cost_price_try'] : 0) ?>" required>
                                                            <small class="text-muted">Mevcut satış fiyatı: <?= Helpers::sanitize(number_format((float)$product['price'], 2, '.', ',')) ?> $</small>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">SKU</label>
                                                            <input type="text" name="sku" class="form-control" value="<?= Helpers::sanitize(isset($product['sku']) ? $product['sku'] : '') ?>">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Sağlayıcı</label>
                                                            <select name="provider_code" class="form-select">
                                                                <option value="">Panel (Stok Teslimatı)</option>
                                                                <option value="stock" <?= isset($product['provider_code']) && $product['provider_code'] === 'stock' ? 'selected' : '' ?>>Stoktan Teslim</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Sağlayıcı Ürün ID</label>
                                                            <input type="text" name="provider_product_id" class="form-control" value="<?= Helpers::sanitize(isset($product['provider_product_id']) ? $product['provider_product_id'] : '') ?>" placeholder="Örn: 57">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-check form-switch pt-4">
                                                                <input class="form-check-input" type="checkbox" id="productStatus<?= (int)$product['id'] ?>" name="status" <?= $product['status'] === 'active' ? 'checked' : '' ?>>
                                                                <label class="form-check-label" for="productStatus<?= (int)$product['id'] ?>">Aktif</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Açıklama</label>
                                                            <textarea name="description" class="form-control" rows="3"><?= Helpers::sanitize(isset($product['description']) ? $product['description'] : '') ?></textarea>
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
<?php include __DIR__ . '/../templates/footer.php';
