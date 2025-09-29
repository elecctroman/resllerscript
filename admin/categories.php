<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Database;
use App\Helpers;

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
        if ($action === 'create_category') {
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';

            if ($name === '') {
                $errors[] = 'Kategori adı zorunludur.';
            }

            if ($parentId) {
                $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
                $stmt->execute(array('id' => $parentId));
                if (!$stmt->fetchColumn()) {
                    $errors[] = 'Belirtilen üst kategori bulunamadı.';
                }
            }

            if (!$errors) {
                $stmt = $pdo->prepare('INSERT INTO categories (name, parent_id, description, created_at) VALUES (:name, :parent_id, :description, NOW())');
                $stmt->execute(array(
                    'name' => $name,
                    'parent_id' => $parentId,
                    'description' => $description !== '' ? $description : null,
                ));

                $success = 'Kategori oluşturuldu.';

                AuditLog::record(
                    $currentUser['id'],
                    'product_category.create',
                    'category',
                    (int)$pdo->lastInsertId(),
                    sprintf('Kategori oluşturuldu: %s', $name)
                );
            }
        } elseif ($action === 'update_category') {
            $categoryId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';

            if ($categoryId <= 0) {
                $errors[] = 'Geçersiz kategori seçimi.';
            }

            if ($name === '') {
                $errors[] = 'Kategori adı zorunludur.';
            }

            if ($parentId && $parentId === $categoryId) {
                $errors[] = 'Bir kategori kendi altına taşınamaz.';
            }

            if (!$errors && $parentId) {
                $stmt = $pdo->prepare('SELECT parent_id FROM categories WHERE id = :id LIMIT 1');
                $stmt->execute(array('id' => $parentId));
                $parentRow = $stmt->fetch();
                if (!$parentRow) {
                    $errors[] = 'Belirtilen üst kategori bulunamadı.';
                } else {
                    $ancestorId = isset($parentRow['parent_id']) ? (int)$parentRow['parent_id'] : null;
                    $guard = 0;
                    while ($ancestorId && $ancestorId > 0 && $guard < 20) {
                        if ($ancestorId === $categoryId) {
                            $errors[] = 'Bir kategori kendi alt kategorisine taşınamaz.';
                            break;
                        }
                        $stmt->execute(array('id' => $ancestorId));
                        $parentRow = $stmt->fetch();
                        if (!$parentRow) {
                            break;
                        }
                        $ancestorId = isset($parentRow['parent_id']) ? (int)$parentRow['parent_id'] : null;
                        $guard++;
                    }
                }
            }

            if (!$errors) {
                $stmt = $pdo->prepare('UPDATE categories SET name = :name, parent_id = :parent_id, description = :description, updated_at = NOW() WHERE id = :id');
                $stmt->execute(array(
                    'id' => $categoryId,
                    'name' => $name,
                    'parent_id' => $parentId,
                    'description' => $description !== '' ? $description : null,
                ));

                $success = 'Kategori güncellendi.';

                AuditLog::record(
                    $currentUser['id'],
                    'product_category.update',
                    'category',
                    $categoryId,
                    sprintf('Kategori güncellendi: %s', $name)
                );
            }
        } elseif ($action === 'delete_category') {
            $categoryId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($categoryId <= 0) {
                $errors[] = 'Geçersiz kategori seçimi.';
            } else {
                $productCountStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id');
                $productCountStmt->execute(array('id' => $categoryId));
                $productCount = (int)$productCountStmt->fetchColumn();

                if ($productCount > 0) {
                    $errors[] = 'Bu kategoride ürün bulunduğu için silinemez. Önce ürünleri başka bir kategoriye taşıyın.';
                }

                if (!$errors) {
                    $childCountStmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = :id');
                    $childCountStmt->execute(array('id' => $categoryId));
                    $childCount = (int)$childCountStmt->fetchColumn();

                    if ($childCount > 0) {
                        $errors[] = 'Alt kategorileri olan kategoriler silinemez. Önce alt kategorileri taşıyın veya silin.';
                    }
                }

                if (!$errors) {
                    $delete = $pdo->prepare('DELETE FROM categories WHERE id = :id');
                    $delete->execute(array('id' => $categoryId));

                    $success = 'Kategori silindi.';

                    AuditLog::record(
                        $currentUser['id'],
                        'product_category.delete',
                        'category',
                        $categoryId,
                        sprintf('Kategori silindi: #%d', $categoryId)
                    );
                }
            }
        }
    }
}

$rawCategories = $pdo->query('SELECT id, parent_id, name, description, created_at, updated_at FROM categories ORDER BY name ASC')->fetchAll();
$categoryMap = array();
foreach ($rawCategories as $category) {
    $categoryMap[(int)$category['id']] = $category;
}

$categoryChildren = array();
foreach ($rawCategories as $category) {
    $parentId = isset($category['parent_id']) && $category['parent_id'] ? (int)$category['parent_id'] : 0;
    if (!isset($categoryChildren[$parentId])) {
        $categoryChildren[$parentId] = array();
    }
    $categoryChildren[$parentId][] = $category;
}

foreach ($categoryChildren as &$list) {
    usort($list, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
}
unset($list);

$flattenedCategories = array();
$walker = function ($parentId, $depth) use (&$walker, &$flattenedCategories, $categoryChildren) {
    if (!isset($categoryChildren[$parentId])) {
        return;
    }

    foreach ($categoryChildren[$parentId] as $category) {
        $flattenedCategories[] = array(
            'id' => (int)$category['id'],
            'name' => isset($category['name']) ? (string)$category['name'] : '',
            'description' => isset($category['description']) ? (string)$category['description'] : '',
            'depth' => $depth,
            'created_at' => isset($category['created_at']) ? $category['created_at'] : null,
            'updated_at' => isset($category['updated_at']) ? $category['updated_at'] : null,
        );

        $walker((int)$category['id'], $depth + 1);
    }
};
$walker(0, 0);

$productCounts = $pdo->query('SELECT category_id, COUNT(*) AS total FROM products GROUP BY category_id')->fetchAll();
$productCountMap = array();
foreach ($productCounts as $row) {
    $productCountMap[(int)$row['category_id']] = (int)$row['total'];
}

$pageTitle = 'Kategoriler';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Kategori</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Kategorileri hiyerarşik olarak düzenleyebilir, ürünlerinizi daha kolay yönetebilirsiniz.</p>

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

                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="create_category">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <div>
                        <label class="form-label">Kategori Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Üst Kategori</label>
                        <select name="parent_id" class="form-select">
                            <option value="">(Ana kategori)</option>
                            <?php foreach ($flattenedCategories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>"><?= str_repeat('— ', $category['depth']) . Helpers::sanitize($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Opsiyonel"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Kategori Oluştur</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Kategori Listesi</h5>
            </div>
            <div class="card-body">
                <?php if (!$flattenedCategories): ?>
                    <p class="text-muted mb-0">Henüz kategori oluşturulmadı.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Ürün Sayısı</th>
                                <th>Güncelleme</th>
                                <th class="text-end">İşlemler</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($flattenedCategories as $category): ?>
                                <?php $count = isset($productCountMap[$category['id']]) ? $productCountMap[$category['id']] : 0; ?>
                                <tr>
                                    <td style="padding-left: <?= 12 + ($category['depth'] * 18) ?>px;">
                                        <strong><?= Helpers::sanitize($category['name']) ?></strong>
                                        <?php if (!empty($category['description'])): ?>
                                            <div class="text-muted small"><?= Helpers::sanitize($category['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?= (int)$count ?></span></td>
                                    <td>
                                        <?php if (!empty($category['updated_at'])): ?>
                                            <small class="text-muted"><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($category['updated_at']))) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCategory<?= (int)$category['id'] ?>">Düzenle</button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Kategoriyi silmek istediğinize emin misiniz?');">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" <?= $count > 0 ? 'disabled' : '' ?>>Sil</button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editCategory<?= (int)$category['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Kategoriyi Düzenle</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update_category">
                                                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Kategori Adı</label>
                                                        <input type="text" name="name" class="form-control" value="<?= Helpers::sanitize($category['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Üst Kategori</label>
                                                        <select name="parent_id" class="form-select">
                                                            <option value="">(Ana kategori)</option>
                                                            <?php foreach ($flattenedCategories as $parent): ?>
                                                                <?php if ($parent['id'] === $category['id']) { continue; } ?>
                                                                <option value="<?= (int)$parent['id'] ?>" <?= isset($categoryMap[$category['id']]['parent_id']) && (int)$categoryMap[$category['id']]['parent_id'] === $parent['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $parent['depth']) . Helpers::sanitize($parent['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Açıklama</label>
                                                        <textarea name="description" class="form-control" rows="3"><?= Helpers::sanitize(isset($categoryMap[$category['id']]['description']) ? $categoryMap[$category['id']]['description'] : '') ?></textarea>
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
