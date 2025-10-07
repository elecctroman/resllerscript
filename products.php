<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Services\ProductOrderService;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if (Auth::isAdminRole($user['role'])) {
    Helpers::redirect('/admin/products.php');
}

$featureAvailable = Helpers::featureEnabled('products');
if (!$featureAvailable) {
    Helpers::setFlash('warning', 'Ürün kataloğu şu anda kullanılamıyor.');
    Helpers::redirect('/dashboard.php');
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

$favoriteProductIds = array();
$watchingProductIds = array();
$favoriteProductsData = array();
$autoTopupConfig = null;

try {
    $favStmt = $pdo->prepare('SELECT product_id FROM reseller_favorites WHERE user_id = :user_id');
    $favStmt->execute(array('user_id' => $user['id']));
    $favoriteProductIds = array_map('intval', array_column($favStmt->fetchAll(\PDO::FETCH_ASSOC), 'product_id'));

    $watchStmt = $pdo->prepare('SELECT product_id FROM reseller_stock_watchers WHERE user_id = :user_id');
    $watchStmt->execute(array('user_id' => $user['id']));
    $watchingProductIds = array_map('intval', array_column($watchStmt->fetchAll(\PDO::FETCH_ASSOC), 'product_id'));

    $autoStmt = $pdo->prepare('SELECT threshold, topup_amount, payment_method, status FROM balance_auto_topups WHERE user_id = :user_id LIMIT 1');
    $autoStmt->execute(array('user_id' => $user['id']));
    $autoTopupConfig = $autoStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

    if ($favoriteProductIds) {
        $favoritePlaceholders = implode(',', array_fill(0, count($favoriteProductIds), '?'));
        $favoriteQuery = 'SELECT pr.*, cat.name AS category_name, (
                SELECT COUNT(*) FROM product_stock_items psi
                WHERE psi.product_id = pr.id AND psi.status = \'available\'
            ) AS available_stock
            FROM products pr
            INNER JOIN categories cat ON pr.category_id = cat.id
            WHERE pr.status = ? AND pr.id IN (' . $favoritePlaceholders . ')
            ORDER BY pr.name ASC';

        $favoriteStmt = $pdo->prepare($favoriteQuery);
        $favoriteParams = array_merge(array('active'), $favoriteProductIds);
        $favoriteStmt->execute($favoriteParams);
        $favoriteProductsData = $favoriteStmt->fetchAll();
    }
} catch (\PDOException $exception) {
    $favoriteProductIds = array();
    $watchingProductIds = array();
    $favoriteProductsData = array();
    $autoTopupConfig = null;
}

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
            $result = ProductOrderService::placePanelOrder($user, $productId, $note ?: null);
            if (!$result['success']) {
                $errors[] = isset($result['message']) ? $result['message'] : 'Sipariş oluşturulamadı.';
            } else {
                $_SESSION['flash_success'] = isset($result['message']) ? $result['message'] : 'Siparişiniz oluşturuldu.';

                $queryParams = array();
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
}

$categories = [];
$categoryMap = [];
$categoryChildren = [];
$topCategories = [];
$subCategories = [];
$activeCategory = null;
$activeTrail = [];
$products = [];
$categoryProductCounts = [];
$breadcrumb = [];
$showAllCategories = $selectedCategoryId === 0 && $searchTerm === '';

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

    $countStmt = $pdo->query("SELECT category_id, COUNT(*) AS total FROM products WHERE status = 'active' GROUP BY category_id");
    $countRows = $countStmt->fetchAll();
    foreach ($countRows as $countRow) {
        $catId = isset($countRow['category_id']) ? (int)$countRow['category_id'] : 0;
        if ($catId) {
            $categoryProductCounts[$catId] = isset($countRow['total']) ? (int)$countRow['total'] : 0;
        }
    }

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

        $breadcrumbIds = array_reverse($activeTrail);
        foreach ($breadcrumbIds as $crumbId) {
            if (isset($categoryMap[$crumbId])) {
                $breadcrumb[] = $categoryMap[$crumbId];
            }
        }

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
        $productsQuery = 'SELECT pr.*, cat.name AS category_name, (SELECT COUNT(*) FROM product_stock_items psi WHERE psi.product_id = pr.id AND psi.status = \'available\') AS available_stock FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.status = ? AND pr.category_id IN (' . $placeholders . ')';
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
        if ($searchTerm !== '') {
            $productsQuery = 'SELECT pr.*, cat.name AS category_name, (SELECT COUNT(*) FROM product_stock_items psi WHERE psi.product_id = pr.id AND psi.status = \'available\') AS available_stock FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.status = ?';
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
    }
} catch (\PDOException $exception) {
    $errors[] = 'Ürünler yüklenirken bir hata oluştu. Lütfen yöneticiyle iletişime geçin.';
}

if ($showAllCategories) {
    $products = [];
}

$favoritesSectionProducts = array();
if ($showAllCategories) {
    $favoritesSectionProducts = $favoriteProductsData;
} else {
    foreach ($products as $index => $product) {
        $productId = isset($product['id']) ? (int)$product['id'] : 0;
        if ($productId && in_array($productId, $favoriteProductIds, true)) {
            $favoritesSectionProducts[] = $product;
            unset($products[$index]);
        }
    }
    $products = array_values($products);
}

$hasFavoritesSection = !empty($favoritesSectionProducts);

$renderProductCard = function (array $product, $highlight = false) use ($categoryPath, $favoriteProductIds, $watchingProductIds) {
    $productId = isset($product['id']) ? (int)$product['id'] : 0;
    $productName = isset($product['name']) ? $product['name'] : (isset($product['title']) ? $product['title'] : 'Servis');
    $productBaseAmount = isset($product['price']) ? (float)$product['price'] : (isset($product['amount']) ? (float)$product['amount'] : 0.0);
    $productBaseCurrency = 'TRY';
    $productPriceHtml = Helpers::formatCurrencyHtml($productBaseAmount, $productBaseCurrency);
    $categoryTrail = isset($product['category_id']) ? $categoryPath((int)$product['category_id']) : (isset($product['category_name']) ? $product['category_name'] : 'Kategori');
    $rawDescription = isset($product['description']) ? trim((string)$product['description']) : '';
    if ($rawDescription === '') {
        $rawDescription = Helpers::defaultProductDescription();
    }
    $shortDescription = Helpers::truncate($rawDescription, 140);
    $skuValue = (isset($product['sku']) && $product['sku'] !== '') ? $product['sku'] : null;
    $automaticDelivery = isset($product['automatic_delivery']) ? (int)$product['automatic_delivery'] === 1 : false;
    $isStockBased = !$automaticDelivery;
    $availableStock = isset($product['available_stock']) ? (int)$product['available_stock'] : 0;
    $stockBadgeClass = 'bg-info text-dark';
    $stockLabel = 'Teslimat durumunu yöneticinizden öğrenin';
    $restockHint = '';

    if ($automaticDelivery && !$isStockBased) {
        $stockBadgeClass = 'bg-primary';
        $stockLabel = 'Otomatik teslimat';
    } elseif ($isStockBased) {
        if ($availableStock > 0) {
            $stockBadgeClass = 'bg-success';
            $stockLabel = sprintf('Stokta %d adet', $availableStock);
            if ($availableStock <= 3) {
                $restockHint = 'Stok seviyesi kritik, yöneticinizden yenileme talep edin.';
            }
        } else {
            $stockBadgeClass = 'bg-danger';
            $stockLabel = 'Stok tükendi';
            $restockHint = 'Bu ürün için stok bildirimi ayarlayabilirsiniz.';
        }
    }

    $isFavorited = in_array($productId, $favoriteProductIds, true) || $highlight;
    $isWatching = in_array($productId, $watchingProductIds, true);
    $buttonDisabled = $isStockBased && $availableStock <= 0;

    $cardClasses = 'product-card';
    if ($highlight) {
        $cardClasses .= ' product-card--favorite';
    }

    ob_start();
    ?>
    <div class="<?= $cardClasses ?>" data-product-card="<?= $productId ?>"<?= $highlight ? ' data-favorite-card="1"' : '' ?>>
        <div class="product-card__header">
            <div>
                <h3 class="product-card__title"><?= Helpers::sanitize($productName) ?></h3>
                <div class="product-card__category"><?= Helpers::sanitize($categoryTrail) ?></div>
            </div>
            <div class="product-card__price"><?= $productPriceHtml ?></div>
        </div>
        <?php if ($skuValue): ?>
            <div class="product-card__sku">SKU: <?= Helpers::sanitize($skuValue) ?></div>
        <?php endif; ?>
        <div class="product-card__stock">
            <span class="badge <?= htmlspecialchars($stockBadgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= Helpers::sanitize($stockLabel) ?></span>
        </div>
        <p class="product-card__description"><?= Helpers::sanitize($shortDescription) ?></p>
        <?php if ($restockHint !== ''): ?>
            <div class="product-card__hint text-muted small mb-2">
                <i class="bi bi-lightbulb me-1"></i><?= Helpers::sanitize($restockHint) ?>
            </div>
        <?php endif; ?>
        <div class="product-card__actions">
            <button type="button"
                    class="product-card__button<?= $buttonDisabled ? ' is-disabled' : '' ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#orderModal"
                    data-product-id="<?= $productId ?>"
                    data-product-name="<?= Helpers::sanitize($productName) ?>"
                    data-product-price-html="<?= htmlspecialchars($productPriceHtml, ENT_QUOTES, 'UTF-8') ?>"
                    data-product-base-amount="<?= Helpers::sanitize(number_format($productBaseAmount, 6, '.', '')) ?>"
                    data-product-base-currency="<?= Helpers::sanitize($productBaseCurrency) ?>"
                    data-product-sku="<?= Helpers::sanitize($skuValue ?: '-') ?>"
                    data-product-category="<?= Helpers::sanitize($categoryTrail) ?>"
                    <?= $buttonDisabled ? 'disabled aria-disabled="true" title="Stok tükendi"' : '' ?>>
                Sipariş ver
            </button>
            <button type="button"
                    class="btn btn-outline-secondary btn-sm ms-2 js-favorite-toggle<?= $isFavorited ? ' active' : '' ?>"
                    data-product-id="<?= $productId ?>"
                    title="<?= $isFavorited ? 'Favorilerden çıkar' : 'Favorilere ekle' ?>">
                <i class="bi <?= $isFavorited ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i>
            </button>
            <button type="button"
                    class="btn btn-outline-warning btn-sm ms-2 js-watch-toggle<?= $isWatching ? ' active' : '' ?>"
                    data-product-id="<?= $productId ?>"
                    title="Stok bildirimi">
                <i class="bi <?= $isWatching ? 'bi-bell-fill' : 'bi-bell' ?>"></i>
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
};

$categoryCountResolver = null;
$categoryCountResolver = function ($categoryId) use (&$categoryChildren, &$categoryProductCounts, &$categoryCountResolver) {
    $total = isset($categoryProductCounts[$categoryId]) ? (int)$categoryProductCounts[$categoryId] : 0;

    if (isset($categoryChildren[$categoryId])) {
        foreach ($categoryChildren[$categoryId] as $child) {
            $childId = isset($child['id']) ? (int)$child['id'] : 0;
            if ($childId) {
                $total += $categoryCountResolver($childId);
            }
        }
    }

    return $total;
};

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

<div class="catalog-container">
    <div class="catalog-top">
        <div>
            <h1 class="catalog-title">Ürün Kataloğu</h1>
            <nav class="catalog-breadcrumb" aria-label="Kategori Gezinimi">
                <a href="/products.php" class="catalog-breadcrumb__link<?= $selectedCategoryId ? '' : ' is-current' ?>">Kategoriler</a>
                <?php foreach ($breadcrumb as $index => $crumb): ?>
                    <span class="catalog-breadcrumb__divider">/</span>
                    <?php $isLast = $index === count($breadcrumb) - 1; ?>
                    <?php $crumbUrl = $buildProductsUrl(array('category' => (int)$crumb['id'])); ?>
                    <?php if ($isLast): ?>
                        <span class="catalog-breadcrumb__current"><?= Helpers::sanitize($crumb['name']) ?></span>
                    <?php else: ?>
                        <a href="<?= Helpers::sanitize($crumbUrl) ?>" class="catalog-breadcrumb__link"><?= Helpers::sanitize($crumb['name']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
        <form method="get" action="/products.php" class="catalog-search-form">
            <?php if ($selectedCategoryId): ?>
                <input type="hidden" name="category" value="<?= (int)$selectedCategoryId ?>">
            <?php endif; ?>
            <div class="catalog-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Ürün ara" value="<?= Helpers::sanitize($searchTerm) ?>" autocomplete="off">
                <?php if ($searchTerm !== ''): ?>
                    <?php $resetParams = $selectedCategoryId ? array('category' => $selectedCategoryId) : array(); ?>
                    <a href="<?= Helpers::sanitize($buildProductsUrl($resetParams)) ?>" class="catalog-search__reset" title="Filtreyi temizle">
                        <i class="bi bi-x"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <section class="catalog-section catalog-section--favorites<?= $hasFavoritesSection ? ' is-visible' : ' is-empty' ?>">
        <div class="catalog-section__header">
            <h2 class="catalog-section__title"><i class="bi bi-heart-fill text-danger me-2"></i>Favorilerim</h2>
        </div>
        <div class="favorites-rail" id="favoritesRail">
            <?php foreach ($favoritesSectionProducts as $favoriteProduct): ?>
                <?= $renderProductCard($favoriteProduct, true) ?>
            <?php endforeach; ?>
        </div>
        <div class="favorites-empty-message<?= $hasFavoritesSection ? ' d-none' : '' ?>" id="favoritesEmptyMessage">
            Henüz favori ürün eklemediniz. Beğendiğiniz ürünlerde kalp simgesine tıklayarak hızlı erişim sağlayabilirsiniz.
        </div>
    </section>

    <?php if ($showAllCategories): ?>
        <section class="catalog-section">
            <div class="catalog-section__header">
                <h2 class="catalog-section__title">Kategoriler</h2>
            </div>
            <?php if ($topCategories): ?>
                <div class="catalog-grid">
                    <?php foreach ($topCategories as $category): ?>
                        <?php $categoryId = (int)$category['id']; ?>
                        <?php $categoryUrl = $buildProductsUrl(array('category' => $categoryId)); ?>
                        <?php $count = $categoryCountResolver($categoryId); ?>
                        <div class="catalog-card">
                            <div class="catalog-card__body">
                                <h3 class="catalog-card__title"><?= Helpers::sanitize($category['name']) ?></h3>
                                <div class="catalog-card__meta">
                                    <?php if ($count > 0): ?>
                                        <?= (int)$count ?> ürün
                                    <?php else: ?>
                                        Yeni ürünler yakında
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a class="catalog-card__button" href="<?= Helpers::sanitize($categoryUrl) ?>">Görüntüle</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="catalog-empty">Henüz kategori eklenmemiş.</div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="catalog-section catalog-section--products">
            <div class="catalog-section__header">
                <div>
                    <?php if ($activeCategory): ?>
                        <h2 class="catalog-section__title"><?= Helpers::sanitize($activeCategory['name']) ?></h2>
                    <?php elseif ($searchTerm !== ''): ?>
                        <h2 class="catalog-section__title">Arama Sonuçları</h2>
                    <?php else: ?>
                        <h2 class="catalog-section__title">Ürünler</h2>
                    <?php endif; ?>
                </div>
                <a class="catalog-back" href="/products.php"><i class="bi bi-arrow-left"></i> Tüm kategoriler</a>
            </div>

            <?php if ($subCategories): ?>
                <div class="catalog-subheader">Alt Kategoriler</div>
                <div class="catalog-grid mb-4">
                    <?php foreach ($subCategories as $subCategory): ?>
                        <?php $subId = (int)$subCategory['id']; ?>
                        <?php $subUrl = $buildProductsUrl(array('category' => $subId)); ?>
                        <?php $count = $categoryCountResolver($subId); ?>
                        <div class="catalog-card is-compact">
                            <div class="catalog-card__body">
                                <h3 class="catalog-card__title"><?= Helpers::sanitize($subCategory['name']) ?></h3>
                                <div class="catalog-card__meta">
                                    <?php if ($count > 0): ?>
                                        <?= (int)$count ?> ürün
                                    <?php else: ?>
                                        Ürün bekleniyor
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a class="catalog-card__button" href="<?= Helpers::sanitize($subUrl) ?>">Görüntüle</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="catalog-subheader">Ürünler</div>

            <?php if ($products): ?>
                <div class="catalog-products">
                    <?php foreach ($products as $product): ?>
                        <?= $renderProductCard($product) ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="catalog-empty">
                    <?php if ($searchTerm !== ''): ?>
                        “<?= Helpers::sanitize($searchTerm) ?>” aramanızla eşleşen ürün bulunamadı.
                    <?php elseif ($activeCategory): ?>
                        Bu kategori için görüntülenecek aktif ürün bulunmuyor.
                    <?php else: ?>
                        Şu anda listelenecek ürün bulunmuyor.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
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
                            <div id="orderProductPrice"><?= Helpers::formatCurrencyHtml(0) ?></div>
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
        var csrfToken = '<?= Helpers::csrfToken() ?>';

        function postResellerAction(action, payload, onSuccess) {
            var formData = new URLSearchParams();
            formData.append('action', action);
            formData.append('csrf_token', csrfToken);
            Object.keys(payload || {}).forEach(function (key) {
                formData.append(key, payload[key]);
            });

            fetch('/reseller-actions.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('İstek başarısız: ' + response.status);
                }
                return response.json();
            }).then(function (data) {
                if (!data.success) {
                    throw new Error(data.error || 'İşlem tamamlanamadı.');
                }
                if (typeof onSuccess === 'function') {
                    onSuccess(data);
                }
            }).catch(function (error) {
                alert(error.message);
            });
        }

        var favoritesRail = document.getElementById('favoritesRail');
        var favoritesSection = document.querySelector('.catalog-section--favorites');
        var favoritesEmptyMessage = document.getElementById('favoritesEmptyMessage');

        function setFavoriteButtonState(button, isFavorited) {
            if (!button) { return; }
            button.classList.toggle('active', !!isFavorited);
            var icon = button.querySelector('i');
            if (icon) {
                icon.className = isFavorited ? 'bi bi-heart-fill text-danger' : 'bi bi-heart';
            }
            button.setAttribute('title', isFavorited ? 'Favorilerden çıkar' : 'Favorilere ekle');
        }

        function setWatchButtonState(button, isWatching) {
            if (!button) { return; }
            button.classList.toggle('active', !!isWatching);
            var icon = button.querySelector('i');
            if (icon) {
                icon.className = isWatching ? 'bi bi-bell-fill' : 'bi bi-bell';
            }
            button.setAttribute('title', isWatching ? 'Bildirimi kapat' : 'Stok bildirimi');
        }

        function updateFavoriteState(productId, isFavorited, originCard) {
            if (!productId) { return; }

            if (originCard) {
                originCard.classList.toggle('product-card--favorite', !!isFavorited);
                if (isFavorited) {
                    originCard.setAttribute('data-favorite-card', '1');
                } else {
                    originCard.removeAttribute('data-favorite-card');
                }
            }

            if (!favoritesRail) {
                return;
            }

            if (isFavorited) {
                var existing = favoritesRail.querySelector('[data-product-card="' + productId + '"]');
                if (!existing && originCard) {
                    var clone = originCard.cloneNode(true);
                    clone.classList.add('product-card--favorite');
                    clone.setAttribute('data-favorite-card', '1');
                    favoritesRail.appendChild(clone);
                    bindFavoriteHandlers(clone);
                    bindWatchHandlers(clone);
                }
                if (favoritesSection) {
                    favoritesSection.classList.remove('is-empty');
                    favoritesSection.classList.add('is-visible');
                }
                if (favoritesEmptyMessage) {
                    favoritesEmptyMessage.classList.add('d-none');
                }
            } else {
                var favoriteCard = favoritesRail.querySelector('[data-product-card="' + productId + '"]');
                if (favoriteCard) {
                    favoriteCard.remove();
                }
                if (favoritesRail.children.length === 0) {
                    if (favoritesSection) {
                        favoritesSection.classList.add('is-empty');
                        favoritesSection.classList.remove('is-visible');
                    }
                    if (favoritesEmptyMessage) {
                        favoritesEmptyMessage.classList.remove('d-none');
                    }
                }
            }
        }

        function bindFavoriteHandlers(scope) {
            (scope || document).querySelectorAll('.js-favorite-toggle').forEach(function (button) {
                if (button.dataset.boundFavorite === '1') {
                    return;
                }
                button.dataset.boundFavorite = '1';
                button.addEventListener('click', function () {
                    var productId = button.getAttribute('data-product-id');
                    var card = button.closest('[data-product-card]');
                    postResellerAction('toggle_favorite', { product_id: productId }, function (data) {
                        var favorited = !!data.favorited;
                        setFavoriteButtonState(button, favorited);
                        updateFavoriteState(productId, favorited, card);
                    });
                });
            });
        }

        function bindWatchHandlers(scope) {
            (scope || document).querySelectorAll('.js-watch-toggle').forEach(function (button) {
                if (button.dataset.boundWatch === '1') {
                    return;
                }
                button.dataset.boundWatch = '1';
                button.addEventListener('click', function () {
                    var productId = button.getAttribute('data-product-id');
                    postResellerAction('toggle_watch', { product_id: productId }, function (data) {
                        setWatchButtonState(button, !!data.watching);
                    });
                });
            });
        }

        bindFavoriteHandlers(document);
        bindWatchHandlers(document);

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
            var priceNode = orderModal.querySelector('#orderProductPrice');
            if (priceNode) {
                var priceHtml = dataset.productPriceHtml || '<?= htmlspecialchars(Helpers::formatCurrencyHtml(0), ENT_QUOTES, 'UTF-8') ?>';
                priceNode.innerHTML = priceHtml;
                if (window.App && typeof window.App.refreshMoney === 'function') {
                    window.App.refreshMoney();
                }
            }
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
