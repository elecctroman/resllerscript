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

    if ($action === 'create_category') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (!$name) {
            $errors[] = 'Kategori adı zorunludur.';
        } else {
            $pdo->prepare('INSERT INTO categories (name, description, created_at) VALUES (:name, :description, NOW())')->execute([
                'name' => $name,
                'description' => $description,
            ]);
            $success = 'Kategori oluşturuldu.';
        }
    } elseif ($action === 'delete_category') {
        $categoryId = (int)($_POST['id'] ?? 0);
        if ($categoryId > 0) {
            $pdo->prepare('DELETE FROM products WHERE category_id = :id')->execute(['id' => $categoryId]);
            $pdo->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $categoryId]);
            $success = 'Kategori ve ilişkili ürünler silindi.';
        }
    } elseif ($action === 'create_product') {
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
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
        }
    } elseif ($action === 'update_product') {
        $productId = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
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
        }
    } elseif ($action === 'delete_product') {
        $productId = (int)($_POST['id'] ?? 0);
        if ($productId > 0) {
            $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $productId]);
            $success = 'Ürün silindi.';
        }
    } elseif ($action === 'import_csv') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'CSV dosyası yüklenemedi. Lütfen tekrar deneyin.';
        } else {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');

            if (!$handle) {
                $errors[] = 'CSV dosyası okunamadı.';
            } else {
                $firstLine = fgets($handle);
                $delimiter = (substr_count((string)$firstLine, ';') > substr_count((string)$firstLine, ',')) ? ';' : ',';
                rewind($handle);

                $headers = fgetcsv($handle, 0, $delimiter);
                if (!$headers) {
                    $errors[] = 'CSV başlık bilgisi okunamadı.';
                } else {
                    $map = [];
                    foreach ($headers as $index => $header) {
                        $map[strtolower(trim($header))] = $index;
                    }

                    $requiredColumns = ['name'];
                    foreach ($requiredColumns as $column) {
                        if (!array_key_exists($column, $map)) {
                            $errors[] = 'CSV dosyasında ürün adını içeren "Name" sütunu bulunamadı.';
                            break;
                        }
                    }

                    if (!$errors) {
                        $imported = 0;
                        $updated = 0;

                        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                            if (!is_array($row) || !isset($row[$map['name']]) || trim($row[$map['name']]) === '') {
                                continue;
                            }

                            $name = trim($row[$map['name']]);
                            $sku = isset($map['sku']) ? trim($row[$map['sku']]) : '';
                            $priceRaw = '';
                            if (isset($map['regular price'])) {
                                $priceRaw = $row[$map['regular price']] ?? '';
                            } elseif (isset($map['price'])) {
                                $priceRaw = $row[$map['price']] ?? '';
                            }

                            $priceSanitized = preg_replace('/[^0-9.,]/', '', (string)$priceRaw);
                            $priceSanitized = str_replace(',', '.', $priceSanitized);
                            $price = (float)$priceSanitized;

                            $categoryName = 'Genel';
                            if (isset($map['categories']) && !empty($row[$map['categories']])) {
                                $rawCategory = $row[$map['categories']];
                                $parts = preg_split('/[>,|]/', $rawCategory);
                                $categoryName = trim($parts[0]);
                                if ($categoryName === '') {
                                    $categoryName = 'Genel';
                                }
                            }

                            $description = '';
                            if (isset($map['description']) && !empty($row[$map['description']])) {
                                $description = $row[$map['description']];
                            } elseif (isset($map['short description']) && !empty($row[$map['short description']])) {
                                $description = $row[$map['short description']];
                            }

                            $status = 'active';
                            if (isset($map['status'])) {
                                $statusValue = strtolower(trim((string)$row[$map['status']]));
                                $status = $statusValue === 'publish' ? 'active' : 'inactive';
                            }

                            $categoryStmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
                            $categoryStmt->execute(['name' => $categoryName]);
                            $category = $categoryStmt->fetchColumn();

                            if (!$category) {
                                $pdo->prepare('INSERT INTO categories (name, created_at) VALUES (:name, NOW())')->execute([
                                    'name' => $categoryName,
                                ]);
                                $category = $pdo->lastInsertId();
                            }

                            $existingProduct = null;
                            if ($sku) {
                                $productStmt = $pdo->prepare('SELECT id FROM products WHERE sku = :sku LIMIT 1');
                                $productStmt->execute(['sku' => $sku]);
                                $existingProduct = $productStmt->fetchColumn();
                            }

                            if (!$existingProduct) {
                                $productStmt = $pdo->prepare('SELECT id FROM products WHERE name = :name AND category_id = :category LIMIT 1');
                                $productStmt->execute([
                                    'name' => $name,
                                    'category' => $category,
                                ]);
                                $existingProduct = $productStmt->fetchColumn();
                            }

                            if ($existingProduct) {
                                $pdo->prepare('UPDATE products SET name = :name, category_id = :category_id, price = :price, description = :description, sku = :sku, status = :status, updated_at = NOW() WHERE id = :id')
                                    ->execute([
                                        'id' => $existingProduct,
                                        'name' => $name,
                                        'category_id' => $category,
                                        'price' => $price,
                                        'description' => $description ?: null,
                                        'sku' => $sku ?: null,
                                        'status' => $status,
                                    ]);
                                $updated++;
                            } else {
                                $pdo->prepare('INSERT INTO products (name, category_id, price, description, sku, status, created_at) VALUES (:name, :category_id, :price, :description, :sku, :status, NOW())')
                                    ->execute([
                                        'name' => $name,
                                        'category_id' => $category,
                                        'price' => $price,
                                        'description' => $description ?: null,
                                        'sku' => $sku ?: null,
                                        'status' => $status,
                                    ]);
                                $imported++;
                            }
                        }

                        $success = sprintf('WooCommerce içe aktarımı tamamlandı. %d yeni ürün eklendi, %d ürün güncellendi.', $imported, $updated);
                    }
                }

                fclose($handle);
            }
        }
    }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
$products = $pdo->query('SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id ORDER BY pr.created_at DESC')->fetchAll();
$pageTitle = 'Ürün ve Kategori Yönetimi';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
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

                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="create_category">
                    <div class="mb-3">
                        <label class="form-label">Kategori Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Kategori Oluştur</button>
                </form>

                <h6 class="text-muted">Mevcut Kategoriler</h6>
                <ul class="list-group">
                    <?php foreach ($categories as $category): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= Helpers::sanitize($category['name']) ?></span>
                            <form method="post" onsubmit="return confirm('Kategoriyi silmek istediğinize emin misiniz?');">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
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
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>"><?= Helpers::sanitize($category['name']) ?></option>
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
        <div class="card border-0 shadow-sm mt-4">
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
                                    <small class="text-muted"><?= Helpers::sanitize($product['description'] ?? '') ?></small>
                                </td>
                                <td><?= Helpers::sanitize($product['category_name']) ?></td>
                                <td>$<?= number_format((float)$product['price'], 2, '.', ',') ?></td>
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
                                                            <?php foreach ($categories as $category): ?>
                                                                <option value="<?= (int)$category['id'] ?>" <?= $product['category_id'] == $category['id'] ? 'selected' : '' ?>><?= Helpers::sanitize($category['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Fiyat ($)</label>
                                                        <input type="number" step="0.01" name="price" class="form-control" value="<?= Helpers::sanitize($product['price']) ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">SKU</label>
                                                        <input type="text" name="sku" class="form-control" value="<?= Helpers::sanitize($product['sku'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-4 form-check form-switch pt-4">
                                                        <input class="form-check-input" type="checkbox" id="productStatus<?= (int)$product['id'] ?>" name="status" <?= $product['status'] === 'active' ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="productStatus<?= (int)$product['id'] ?>">Aktif</label>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Açıklama</label>
                                                        <textarea name="description" class="form-control" rows="3"><?= Helpers::sanitize($product['description'] ?? '') ?></textarea>
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
