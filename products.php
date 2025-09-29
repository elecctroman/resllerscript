<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Telegram;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if (Auth::isAdminRole($user['role'])) {
    Helpers::redirect('/admin/products.php');
}

$selectedCategoryId = isset($_GET['category']) ? max(0, (int)$_GET['category']) : 0;

$pdo = Database::connection();

$errors = [];
$success = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';

if ($success) {
    unset($_SESSION['flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCategoryId = isset($_POST['redirect_category']) ? max(0, (int)$_POST['redirect_category']) : $selectedCategoryId;

    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($csrfToken)) {
        $errors[] = 'Oturum doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $note = isset($_POST['note']) ? trim($_POST['note']) : '';

        if ($productId <= 0) {
            $errors[] = 'Sipariş verilecek ürün seçilemedi.';
        }

        if ($note && (function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') > 500 : strlen($note) > 500)) {
            $errors[] = 'Sipariş notu en fazla 500 karakter olabilir.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                $productStmt = $pdo->prepare('SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.id = :id AND pr.status = :status FOR UPDATE');
                $productStmt->execute([
                    'id' => $productId,
                    'status' => 'active',
                ]);
                $product = $productStmt->fetch();

                if (!$product) {
                    $pdo->rollBack();
                    $errors[] = 'Ürün bulunamadı veya pasif durumda.';
                } else {
                    $userStmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
                    $userStmt->execute(['id' => $user['id']]);
                    $freshUser = $userStmt->fetch();

                    if (!$freshUser) {
                        $pdo->rollBack();
                        $errors[] = 'Kullanıcı kaydı bulunamadı. Lütfen oturumu kapatıp tekrar giriş yapın.';
                    } else {
                        $price = (float)$product['price'];
                        $currentBalance = (float)$freshUser['balance'];

                        if ($price > $currentBalance) {
                            $pdo->rollBack();
                            $errors[] = 'Bakiyeniz bu ürünü sipariş etmek için yetersiz görünüyor. Lütfen bakiye yükleyip tekrar deneyin.';
                        } else {
                            $orderStmt = $pdo->prepare('INSERT INTO product_orders (product_id, user_id, quantity, note, price, status, source, created_at) VALUES (:product_id, :user_id, :quantity, :note, :price, :status, :source, NOW())');
                            $orderStmt->execute([
                                'product_id' => $productId,
                                'user_id' => $user['id'],
                                'quantity' => 1,
                                'note' => $note ?: null,
                                'price' => $price,
                                'status' => 'pending',
                                'source' => 'panel',
                            ]);

                            $orderId = (int)$pdo->lastInsertId();

                            $pdo->prepare('UPDATE users SET balance = balance - :amount WHERE id = :id')->execute([
                                'amount' => $price,
                                'id' => $user['id'],
                            ]);

                            $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute([
                                'user_id' => $user['id'],
                                'amount' => $price,
                                'type' => 'debit',
                                'description' => 'Ürün siparişi: ' . $product['name'],
                            ]);

                            $pdo->commit();

                            Telegram::notify(sprintf(
                                "🛒 Yeni ürün siparişi alındı!\nBayi: %s\nÜrün: %s\nTutar: %s\nSipariş No: #%d",
                                $user['name'],
                                $product['name'],
                                Helpers::formatCurrency($price, 'USD'),
                                $orderId
                            ));

                            $_SESSION['flash_success'] = 'Sipariş talebiniz alındı ve bakiyenizden düşüldü. Ürün teslimatı kısa süre içinde gerçekleştirilecektir.';

                            $redirectQuery = $selectedCategoryId ? '?category=' . $selectedCategoryId : '';
                            Helpers::redirect('/products.php' . $redirectQuery);
                        }
                    }
                }
            } catch (\PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Sipariş talebiniz kaydedilirken bir veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.';
            }
        }
    }
}

$categories = [];
$categoryMap = [];
$categoryChildren = [];
$topCategories = [];
$subCategories = [];
$activeCategory = null;
$activeTrail = [];
$parentCategory = null;
$products = [];

$categoryPath = function ($categoryId) use (&$categoryMap) {
    $parts = [];
    $currentId = $categoryId;
    $guard = 0;

    while ($currentId && isset($categoryMap[$currentId]) && $guard < 20) {
        $parts[] = $categoryMap[$currentId]['name'];
        $currentId = isset($categoryMap[$currentId]['parent_id']) ? (int)$categoryMap[$currentId]['parent_id'] : 0;
        $guard++;
    }

    if (!$parts) {
        return 'Tüm Ürünler';
    }

    return implode(' / ', array_reverse($parts));
};

try {
    $categories = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();

    foreach ($categories as $category) {
        $categoryId = (int)$category['id'];
        $categoryMap[$categoryId] = $category;
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

    $topCategories = isset($categoryChildren[0]) ? $categoryChildren[0] : [];

    if ($selectedCategoryId && !isset($categoryMap[$selectedCategoryId])) {
        $errors[] = 'Seçili kategori bulunamadı. Tüm kategoriler listeleniyor.';
        $selectedCategoryId = 0;
    }

    if ($selectedCategoryId && isset($categoryMap[$selectedCategoryId])) {
        $activeCategory = $categoryMap[$selectedCategoryId];

        $parentId = isset($activeCategory['parent_id']) ? (int)$activeCategory['parent_id'] : 0;
        if ($parentId && isset($categoryMap[$parentId])) {
            $parentCategory = $categoryMap[$parentId];
        }

        $currentId = (int)$activeCategory['id'];
        $guard = 0;
        while ($currentId && isset($categoryMap[$currentId]) && $guard < 20) {
            $activeTrail[] = $currentId;
            $parentId = isset($categoryMap[$currentId]['parent_id']) ? (int)$categoryMap[$currentId]['parent_id'] : 0;
            if (!$parentId) {
                break;
            }
            $currentId = $parentId;
            $guard++;
        }

        $subCategories = isset($categoryChildren[$selectedCategoryId]) ? $categoryChildren[$selectedCategoryId] : [];

        $descendants = [];
        $collectDescendants = function ($parentId) use (&$collectDescendants, &$categoryChildren, &$descendants) {
            if (!isset($categoryChildren[$parentId])) {
                return;
            }
            foreach ($categoryChildren[$parentId] as $child) {
                $childId = (int)$child['id'];
                $descendants[] = $childId;
                $collectDescendants($childId);
            }
        };

        $categoryIds = [$selectedCategoryId];
        $collectDescendants($selectedCategoryId);
        foreach ($descendants as $descendantId) {
            $categoryIds[] = $descendantId;
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $productsQuery = 'SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.status = ? AND pr.category_id IN (' . $placeholders . ') ORDER BY pr.name ASC';
        $productParams = array_merge(['active'], $categoryIds);

        $productsStmt = $pdo->prepare($productsQuery);
        $productsStmt->execute($productParams);
        $products = $productsStmt->fetchAll();
    }
} catch (\PDOException $exception) {
    $errors[] = 'Ürünler yüklenirken bir hata oluştu. Lütfen yöneticiyle iletişime geçin.';
}

$pageTitle = 'Ürün Kataloğu';

include __DIR__ . '/templates/header.php';
?>
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
    <div class="alert alert-success mb-4"><?= Helpers::sanitize($success) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Kategoriler</h5>
        <?php if ($selectedCategoryId): ?>
            <a href="/products.php" class="btn btn-sm btn-outline-secondary">Tüm Kategoriler</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($topCategories): ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
                <?php foreach ($topCategories as $category): ?>
                    <?php $isActive = in_array((int)$category['id'], $activeTrail) || $selectedCategoryId === (int)$category['id']; ?>
                    <div class="col">
                        <a href="/products.php?category=<?= (int)$category['id'] ?>" class="category-card <?= $isActive ? 'active' : '' ?>">
                            <div class="category-card-title"><?= Helpers::sanitize($category['name']) ?></div>
                            <div class="category-card-description">
                                <?= Helpers::sanitize(Helpers::truncate(isset($category['description']) ? $category['description'] : '', 90)) ?>
                            </div>
                            <span class="category-card-link">Görüntüle <i class="bi bi-arrow-right-short"></i></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">Henüz kategori tanımlanmamış.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($activeCategory): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <div>
                <h5 class="mb-1"><?= Helpers::sanitize($activeCategory['name']) ?></h5>
                <small class="text-muted">Konum: <?= Helpers::sanitize($categoryPath((int)$activeCategory['id'])) ?></small>
            </div>
            <?php if ($parentCategory): ?>
                <a href="/products.php?category=<?= (int)$parentCategory['id'] ?>" class="btn btn-sm btn-outline-secondary">Üst Kategoriye Dön</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($subCategories): ?>
                <div class="mb-4">
                    <h6 class="text-uppercase text-muted mb-3">Alt Kategoriler</h6>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
                        <?php foreach ($subCategories as $subCategory): ?>
                            <div class="col">
                                <a href="/products.php?category=<?= (int)$subCategory['id'] ?>" class="subcategory-card <?= $selectedCategoryId === (int)$subCategory['id'] ? 'active' : '' ?>">
                                    <div class="subcategory-card-title"><?= Helpers::sanitize($subCategory['name']) ?></div>
                                    <span class="subcategory-card-link">Görüntüle <i class="bi bi-arrow-right-short"></i></span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($products): ?>
                <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-xl-3">
                    <?php foreach ($products as $product): ?>
                        <div class="col">
                            <div class="card product-card h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="product-card-title mb-1"><?= Helpers::sanitize($product['name']) ?></h6>
                                            <div class="product-card-category text-muted small">Kategori: <?= Helpers::sanitize($product['category_name']) ?></div>
                                        </div>
                                        <div class="product-card-price"><?= Helpers::sanitize(Helpers::formatCurrency((float)$product['price'])) ?></div>
                                    </div>
                                    <div class="product-card-description text-muted small mb-3">
                                        <?php
                                        $rawDescription = isset($product['description']) ? trim($product['description']) : '';
                                        if ($rawDescription === '') {
                                            $rawDescription = Helpers::defaultProductDescription();
                                        }
                                        echo Helpers::sanitize(Helpers::truncate($rawDescription, 180));
                                        ?>
                                    </div>
                                    <div class="product-card-meta text-muted small mb-4">
                                        SKU: <?= Helpers::sanitize(isset($product['sku']) && $product['sku'] !== '' ? $product['sku'] : '-') ?>
                                    </div>
                                    <div class="mt-auto">
                                        <button type="button"
                                                class="btn btn-primary w-100"
                                                data-bs-toggle="modal"
                                                data-bs-target="#orderModal"
                                                data-product-id="<?= (int)$product['id'] ?>"
                                                data-product-name="<?= Helpers::sanitize($product['name']) ?>"
                                                data-product-price="<?= Helpers::sanitize(Helpers::formatCurrency((float)$product['price'])) ?>"
                                                data-product-sku="<?= Helpers::sanitize(isset($product['sku']) ? $product['sku'] : '-') ?>"
                                                data-product-category="<?= Helpers::sanitize($product['category_name']) ?>">
                                            Sipariş Ver
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">Bu kategori için görüntülenecek aktif ürün bulunmuyor.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Ürün Siparişi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                    <input type="hidden" name="product_id" id="orderProductId">
                    <input type="hidden" name="redirect_category" value="<?= (int)$selectedCategoryId ?>">
                    <div class="mb-3">
                        <label class="form-label">Ürün</label>
                        <input type="text" class="form-control" id="orderProductName" readonly>
                    </div>
                    <div class="row g-2 mb-3 small text-muted">
                        <div class="col-6">
                            <div class="fw-semibold">Kategori</div>
                            <div id="orderProductCategory">-</div>
                        </div>
                        <div class="col-6 text-end">
                            <div class="fw-semibold"><?= Helpers::sanitize('Fiyat') ?></div>
                            <div id="orderProductPrice"><?= Helpers::sanitize(Helpers::formatCurrency(0)) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="fw-semibold">SKU</div>
                            <div id="orderProductSku">-</div>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Sipariş Notu</label>
                        <textarea class="form-control" name="note" id="orderNote" rows="3" maxlength="500" placeholder="Ürün teslimatı için özel bir talebiniz varsa yazabilirsiniz."></textarea>
                        <small class="text-muted">Not alanı isteğe bağlıdır ve 500 karakter ile sınırlıdır.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Sipariş Talebi Gönder</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var orderModal = document.getElementById('orderModal');
        if (!orderModal) {
            return;
        }

        orderModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) {
                return;
            }

            var dataset = button.dataset || {};

            orderModal.querySelector('#orderProductId').value = dataset.productId || '';
            orderModal.querySelector('#orderProductName').value = dataset.productName || '';
            orderModal.querySelector('#orderProductPrice').textContent = dataset.productPrice || '<?= Helpers::sanitize(Helpers::formatCurrency(0)) ?>';
            orderModal.querySelector('#orderProductSku').textContent = dataset.productSku || '-';
            orderModal.querySelector('#orderProductCategory').textContent = dataset.productCategory || '-';

            var noteField = orderModal.querySelector('#orderNote');
            if (noteField) {
                noteField.value = '';
            }
        });
    });
</script>
<?php include __DIR__ . '/templates/footer.php';
