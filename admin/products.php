<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Helpers;
use App\Database;
use App\Importers\WooCommerceImporter;

Auth::requireRoles(array('super_admin', 'admin', 'content'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
$errors = array();
$success = '';
$warning = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create_category') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;

        if (!$name) {
            $errors[] = 'Kategori adı zorunludur.';
        } else {
            $parent = null;
            if ($parentId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $parentId]);
                $parent = $stmt->fetchColumn() ? $parentId : null;
            }

            $pdo->prepare('INSERT INTO categories (name, parent_id, description, created_at) VALUES (:name, :parent_id, :description, NOW())')->execute([
                'name' => $name,
                'parent_id' => $parent,
                'description' => $description,
            ]);
            $success = 'Kategori oluşturuldu.';

            AuditLog::record(
                $currentUser['id'],
                'product_category.create',
                'category',
                (int)$pdo->lastInsertId(),
                sprintf('Kategori oluşturuldu: %s', $name)
            );
        }
    } elseif ($action === 'delete_category') {
        $categoryId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($categoryId > 0) {
            $pdo->prepare('DELETE FROM products WHERE category_id = :id')->execute(['id' => $categoryId]);
            $pdo->prepare('UPDATE categories SET parent_id = NULL WHERE parent_id = :id')->execute(['id' => $categoryId]);
            $pdo->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $categoryId]);
            $success = 'Kategori ve ilişkili ürünler silindi.';

            AuditLog::record(
                $currentUser['id'],
                'product_category.delete',
                'category',
                $categoryId,
                sprintf('Kategori silindi: #%d', $categoryId)
            );
        }
    } elseif ($action === 'create_product') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $sku = isset($_POST['sku']) ? trim($_POST['sku']) : '';
        $status = isset($_POST['status']) ? 'active' : 'inactive';

        if (!$name || $categoryId <= 0) {
            $errors[] = 'Ürün adı ve kategorisi zorunludur.';
        } else {
            $pdo->prepare('INSERT INTO products (name, category_id, price, description, sku, status, created_at) VALUES (:name, :category_id, :price, :description, :sku, :status, NOW())')->execute([
                'name' => $name,
                'category_id' => $categoryId,
                'price' => $price,
                'description' => $description,
                'sku' => $sku,
                'status' => $status,
            ]);
            $success = 'Ürün eklendi.';

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
        $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $sku = isset($_POST['sku']) ? trim($_POST['sku']) : '';
        $status = isset($_POST['status']) ? 'active' : 'inactive';

        if ($productId <= 0 || !$name) {
            $errors[] = 'Geçersiz ürün bilgisi.';
        } else {
            $pdo->prepare('UPDATE products SET name = :name, category_id = :category_id, price = :price, description = :description, sku = :sku, status = :status, updated_at = NOW() WHERE id = :id')->execute([
                'id' => $productId,
                'name' => $name,
                'category_id' => $categoryId,
                'price' => $price,
                'description' => $description,
                'sku' => $sku,
                'status' => $status,
            ]);
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
            $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $productId]);
            $success = 'Ürün silindi.';

            AuditLog::record(
                $currentUser['id'],
                'product.delete',
                'product',
                $productId,
                sprintf('Ürün silindi: #%d', $productId)
            );
        }
    } elseif ($action === 'import_csv') {
        $result = WooCommerceImporter::import($pdo, isset($_FILES['csv_file']) ? $_FILES['csv_file'] : []);
        $errors = array_merge($errors, $result['errors']);

        if (!$result['errors']) {
            if ($result['imported'] > 0 || $result['updated'] > 0) {
                $success = sprintf(
                    'WooCommerce içe aktarımı tamamlandı. %d yeni ürün eklendi, %d ürün güncellendi.',
                    $result['imported'],
                    $result['updated']
                );

                AuditLog::record(
                    $currentUser['id'],
                    'product.import_csv',
                    'product',
                    null,
                    sprintf('CSV içe aktarım: %d yeni, %d güncellendi', $result['imported'], $result['updated'])
                );
            } elseif (!empty($result['warning'])) {
                $warning = $result['warning'];
            }
        }
    }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
$products = $pdo->query('SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id ORDER BY pr.created_at DESC')->fetchAll();
$categoryMap = [];
foreach ($categories as $category) {
    $categoryMap[(int)$category['id']] = $category;
}

$categoryChildren = [];
foreach ($categories as $category) {
    $parentKey = isset($category['parent_id']) && $category['parent_id'] ? (int)$category['parent_id'] : 0;
    if (!isset($categoryChildren[$parentKey])) {
        $categoryChildren[$parentKey] = [];
    }
    $categoryChildren[$parentKey][] = $category;
}

foreach ($categoryChildren as &$childList) {
    usort($childList, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
}
unset($childList);

$flattenedCategories = [];
$walker = function ($parentId, $depth) use (&$walker, &$flattenedCategories, $categoryChildren) {
    if (!isset($categoryChildren[$parentId])) {
        return;
    }

    foreach ($categoryChildren[$parentId] as $category) {
        $flattenedCategories[] = [
            'id' => (int)$category['id'],
            'name' => $category['name'],
            'parent_id' => isset($category['parent_id']) ? (int)$category['parent_id'] : null,
            'description' => isset($category['description']) ? $category['description'] : null,
            'depth' => $depth,
        ];

        $walker((int)$category['id'], $depth + 1);
    }
};

$walker(0, 0);

$categoryPath = function ($categoryId) use (&$categoryMap) {
    $parts = [];
    $currentId = $categoryId;
    $guard = 0;
    while ($currentId && isset($categoryMap[$currentId]) && $guard < 20) {
        $parts[] = $categoryMap[$currentId]['name'];
        $currentId = isset($categoryMap[$currentId]['parent_id']) ? (int)$categoryMap[$currentId]['parent_id'] : 0;
        $guard++;
    }

    return implode(' / ', array_reverse($parts));
};
$pageTitle = 'Ürün ve Kategori Yönetimi';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4" id="category-management">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Kategori</h5>
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

                <?php if ($warning): ?>
                    <div class="alert alert-warning mb-4"><?= Helpers::sanitize($warning) ?></div>
                <?php endif; ?>

                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="create_category">
                    <div class="mb-3">
                        <label class="form-label">Kategori Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Üst Kategori</label>
                        <select name="parent_id" class="form-select">
                            <option value="">(Ana kategori)</option>
                            <?php foreach ($flattenedCategories as $item): ?>
                                <option value="<?= (int)$item['id'] ?>"><?= str_repeat('— ', $item['depth']) . Helpers::sanitize($item['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Kategori Oluştur</button>
                </form>

                <h6 class="text-muted">Mevcut Kategoriler</h6>
                <ul class="list-group">
                    <?php if (!$flattenedCategories): ?>
                        <li class="list-group-item text-muted">Henüz kategori oluşturulmadı.</li>
                    <?php else: ?>
                        <?php foreach ($flattenedCategories as $category): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center" style="padding-left: <?php echo 12 + ($category['depth'] * 16); ?>px;">
                                <span>
                                    <?= str_repeat('— ', $category['depth']) ?><?= Helpers::sanitize($category['name']) ?>
                                    <?php if (!empty($category['description'])): ?>
                                        <small class="text-muted d-block"><?= Helpers::sanitize($category['description']) ?></small>
                                    <?php endif; ?>
                                </span>
                                <form method="post" onsubmit="return confirm('Kategoriyi silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="card border-0 shadow-sm" id="product-create">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Ürün</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="create_product">
                    <div class="mb-3">
                        <label class="form-label">Ürün Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Kategori seçin</option>
                            <?php foreach ($flattenedCategories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>"><?= str_repeat('— ', $category['depth']) . Helpers::sanitize($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fiyat ($)</label>
                        <input type="number" step="0.01" name="price" class="form-control" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="status" id="productStatus" checked>
                        <label class="form-check-label" for="productStatus">Ürün aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ürünü Kaydet</button>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm mt-4" id="woocommerce-import">
            <div class="card-header bg-white">
                <h5 class="mb-0">WooCommerce CSV İçe Aktar</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" class="vstack gap-3">
                    <input type="hidden" name="action" value="import_csv">
                    <div>
                        <label class="form-label">CSV Dosyası</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                    <p class="text-muted small mb-0">WooCommerce ürün dışa aktarım dosyasını yükleyerek kategorileriyle birlikte otomatik olarak içeri aktarabilirsiniz.</p>
                    <button type="submit" class="btn btn-outline-primary">Dosyayı İçe Aktar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Ürünler</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Ürün</th>
                            <th>Kategori</th>
                            <th>Fiyat</th>
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
                                    <small class="text-muted"><?= Helpers::sanitize(isset($product['description']) ? $product['description'] : '') ?></small>
                                </td>
                                <td><?= Helpers::sanitize($categoryPath((int)$product['category_id'])) ?></td>
                                <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$product['price'])) ?></td>
                                <td>
                                    <?php if ($product['status'] === 'active'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProduct<?= (int)$product['id'] ?>">Düzenle</button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Ürünü silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="action" value="delete_product">
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
                                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Ürün Adı</label>
                                                        <input type="text" name="name" class="form-control" value="<?= Helpers::sanitize($product['name']) ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Kategori</label>
                                                        <select name="category_id" class="form-select">
                                                            <?php foreach ($flattenedCategories as $category): ?>
                                                                <option value="<?= (int)$category['id'] ?>" <?= $product['category_id'] == $category['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $category['depth']) . Helpers::sanitize($category['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Fiyat ($)</label>
                                                        <input type="number" step="0.01" name="price" class="form-control" value="<?= Helpers::sanitize($product['price']) ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">SKU</label>
                                                        <input type="text" name="sku" class="form-control" value="<?= Helpers::sanitize(isset($product['sku']) ? $product['sku'] : '') ?>">
                                                    </div>
                                                    <div class="col-md-4 form-check form-switch pt-4">
                                                        <input class="form-check-input" type="checkbox" id="productStatus<?= (int)$product['id'] ?>" name="status" <?= $product['status'] === 'active' ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="productStatus<?= (int)$product['id'] ?>">Aktif</label>
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
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
