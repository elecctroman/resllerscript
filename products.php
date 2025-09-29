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
$searchTerm = '';

if (isset($_GET['q'])) {
    $searchTerm = trim($_GET['q']);
    if ($searchTerm !== '') {
        $searchTerm = function_exists('mb_substr')
            ? mb_substr($searchTerm, 0, 80, 'UTF-8')
            : substr($searchTerm, 0, 80);
    }
}

$pdo = Database::connection();

$errors = [];
$success = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';

if ($success) {
    unset($_SESSION['flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCategoryId = isset($_POST['redirect_category']) ? max(0, (int)$_POST['redirect_category']) : $selectedCategoryId;
    if (isset($_POST['search_query'])) {
        $searchInput = trim($_POST['search_query']);
        if ($searchInput !== '') {
            $searchTerm = function_exists('mb_substr')
                ? mb_substr($searchInput, 0, 80, 'UTF-8')
                : substr($searchInput, 0, 80);
        } else {
            $searchTerm = '';
        }
    }

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

                            $queryParams = [];
                            if ($selectedCategoryId) {
                                $queryParams['category'] = $selectedCategoryId;
                            }
                            if ($searchTerm !== '') {
                                $queryParams['q'] = $searchTerm;
                            }

                            $redirectQuery = $queryParams ? ('?' . http_build_query($queryParams)) : '';
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
$products = [];

$buildProductsUrl = function (array $params = []) use (&$searchTerm) {
    $query = $params;
    if ($searchTerm !== '') {
        $query['q'] = $searchTerm;
    }

    $queryString = $query ? '?' . http_build_query($query) : '';

    return '/products.php' . $queryString;
};

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
        $productsQuery = 'SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.status = ? AND pr.category_id IN (' . $placeholders . ')';
        $productParams = array_merge(['active'], $categoryIds);

        if ($searchTerm !== '') {
            $productsQuery .= ' AND pr.name LIKE ?';
            $productParams[] = '%' . $searchTerm . '%';
        }

        $productsQuery .= ' ORDER BY pr.name ASC';

        $productsStmt = $pdo->prepare($productsQuery);
        $productsStmt->execute($productParams);
        $products = $productsStmt->fetchAll();
    } else {
        $productsQuery = 'SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.status = ?';
        $productParams = ['active'];

        if ($searchTerm !== '') {
            $productsQuery .= ' AND pr.name LIKE ?';
            $productParams[] = '%' . $searchTerm . '%';
        }

        $productsQuery .= ' ORDER BY cat.name ASC, pr.name ASC';

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

<div class="catalog-wrapper">
    <div class="catalog-filter-bar">
        <div class="catalog-filter-group">
            <?php $allUrl = $buildProductsUrl(array()); ?>
            <a href="<?= Helpers::sanitize($allUrl) ?>" class="catalog-pill <?= $selectedCategoryId ? '' : 'active' ?>">
                <span class="catalog-pill-text">Hepsi</span>
            </a>
            <?php foreach ($topCategories as $category): ?>
                <?php $categoryId = (int)$category['id']; ?>
                <?php $categoryUrl = $buildProductsUrl(array('category' => $categoryId)); ?>
                <?php $isActive = $selectedCategoryId === $categoryId || in_array($categoryId, $activeTrail, true); ?>
                <a href="<?= Helpers::sanitize($categoryUrl) ?>" class="catalog-pill <?= $isActive ? 'active' : '' ?>">
                    <span class="catalog-pill-text"><?= Helpers::sanitize($category['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="get" action="/products.php" class="catalog-search">
            <?php if ($selectedCategoryId): ?>
                <input type="hidden" name="category" value="<?= (int)$selectedCategoryId ?>">
            <?php endif; ?>
            <div class="catalog-search-field">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Arama" value="<?= Helpers::sanitize($searchTerm) ?>" autocomplete="off">
                <?php if ($searchTerm !== ''): ?>
                    <?php $resetParams = $selectedCategoryId ? array('category' => $selectedCategoryId) : array(); ?>
                    <a href="<?= Helpers::sanitize($buildProductsUrl($resetParams)) ?>" class="catalog-search-reset" title="Filtreyi temizle">
                        <i class="bi bi-x"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($subCategories): ?>
            <div class="catalog-filter-subgroup">
                <?php foreach ($subCategories as $subCategory): ?>
                    <?php $subId = (int)$subCategory['id']; ?>
                    <?php $subUrl = $buildProductsUrl(array('category' => $subId)); ?>
                    <a href="<?= Helpers::sanitize($subUrl) ?>" class="catalog-pill small <?= $selectedCategoryId === $subId ? 'active' : '' ?>">
                        <span class="catalog-pill-text"><?= Helpers::sanitize($subCategory['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php
    $headline = 'Tüm Ürünler';
    if ($activeCategory) {
        $headline = $activeCategory['name'];
    } elseif ($searchTerm !== '') {
        $headline = 'Arama Sonuçları';
    }
    ?>
    <div class="catalog-headline">
        <h5><?= Helpers::sanitize($headline) ?></h5>
        <?php if ($searchTerm !== ''): ?>
            <span class="catalog-headline-pill">“<?= Helpers::sanitize($searchTerm) ?>”</span>
        <?php endif; ?>
    </div>

    <div class="service-list">
        <?php if ($products): ?>
            <?php foreach ($products as $product): ?>
                <?php
                $productName = isset($product['name']) ? $product['name'] : 'Servis';
                $productPrice = Helpers::formatCurrency(isset($product['price']) ? (float)$product['price'] : 0);
                $categoryTrail = isset($product['category_id']) ? $categoryPath((int)$product['category_id']) : (isset($product['category_name']) ? $product['category_name'] : 'Kategori');
                $rawDescription = isset($product['description']) ? trim($product['description']) : '';
                if ($rawDescription === '') {
                    $rawDescription = Helpers::defaultProductDescription();
                }
                $descriptionLines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawDescription)));
                $hasList = count($descriptionLines) > 1;
                $skuValue = (isset($product['sku']) && $product['sku'] !== '') ? $product['sku'] : null;
                ?>
                <div class="service-card">
                    <div class="service-card__header">
                        <div>
                            <div class="service-card__title"><?= Helpers::sanitize($productName) ?></div>
                            <?php if ($skuValue): ?>
                                <div class="service-card__meta">SKU: <?= Helpers::sanitize($skuValue) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="service-card__price"><?= Helpers::sanitize($productPrice) ?></div>
                    </div>

                    <div class="service-section">
                        <div class="service-section__label">Kategori</div>
                        <div class="service-section__body"><?= Helpers::sanitize($categoryTrail) ?></div>
                    </div>

                    <div class="service-section">
                        <div class="service-section__label">Servis</div>
                        <div class="service-section__body service-section__tags">
                            <span class="service-pill"><?= Helpers::sanitize($productName) ?></span>
                            <span class="service-pill price"><?= Helpers::sanitize($productPrice) ?></span>
                        </div>
                    </div>

                    <div class="service-section">
                        <div class="service-section__label">Açıklama</div>
                        <div class="service-section__body">
                            <?php if ($hasList): ?>
                                <ul class="service-description-list">
                                    <?php foreach ($descriptionLines as $line): ?>
                                        <li><?= Helpers::sanitize($line) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="mb-0"><?= Helpers::sanitize($rawDescription) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="service-section">
                        <div class="service-section__label">Link</div>
                        <div class="service-section__body">
                            <span class="service-hint">Sipariş sırasında bağlantınızı ve talimatlarınızı not alanına ekleyin.</span>
                        </div>
                    </div>

                    <div class="service-section">
                        <div class="service-section__label">Miktar</div>
                        <div class="service-section__body">
                            <span class="service-hint">Minimum 1 - Maksimum 1 (tek seferlik teslimat)</span>
                        </div>
                    </div>

                    <div class="service-card__footer">
                        <button type="button"
                                class="service-order-button"
                                data-bs-toggle="modal"
                                data-bs-target="#orderModal"
                                data-product-id="<?= (int)$product['id'] ?>"
                                data-product-name="<?= Helpers::sanitize($productName) ?>"
                                data-product-price="<?= Helpers::sanitize($productPrice) ?>"
                                data-product-sku="<?= Helpers::sanitize($skuValue ?: '-') ?>"
                                data-product-category="<?= Helpers::sanitize($categoryTrail) ?>">
                            Sipariş oluştur
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="service-empty">
                <?php if ($searchTerm !== ''): ?>
                    “<?= Helpers::sanitize($searchTerm) ?>” aramanızla eşleşen ürün bulunamadı.
                <?php elseif ($activeCategory): ?>
                    Bu kategori için görüntülenecek aktif ürün bulunmuyor.
                <?php else: ?>
                    Şu anda listelenecek aktif ürün bulunmuyor.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

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
                    <input type="hidden" name="search_query" value="<?= Helpers::sanitize($searchTerm) ?>">
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
