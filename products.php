<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Telegram;
use App\Services\LotusPartnerApi;
use Lotus\Exceptions\ApiError as LotusApiError;

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
            $lotusIntegrationEnabled = LotusPartnerApi::isEnabled();
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

                            $orderStatus = 'pending';
                            $externalReference = null;
                            $externalMetadata = null;

                            if ($lotusIntegrationEnabled) {
                                $lotusResult = LotusPartnerApi::submitProductOrder($product, 1, $note !== '' ? $note : null);
                                $orderStatus = isset($lotusResult['status']) && $lotusResult['status'] !== '' ? $lotusResult['status'] : 'processing';
                                $externalReference = isset($lotusResult['external_reference']) ? $lotusResult['external_reference'] : null;
                                $externalMetadata = LotusPartnerApi::encodeMetadata($lotusResult['metadata']);

                                $updateStmt = $pdo->prepare('UPDATE product_orders SET status = :status, external_reference = :external_reference, external_metadata = :external_metadata, updated_at = NOW() WHERE id = :id');
                                $updateStmt->execute([
                                    'status' => $orderStatus,
                                    'external_reference' => $externalReference,
                                    'external_metadata' => $externalMetadata,
                                    'id' => $orderId,
                                ]);
                            }

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
            } catch (LotusApiError $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Lotus Lisans API hatası: ' . $exception->getMessage();
            } catch (\PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Sipariş talebiniz kaydedilirken bir veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.';
            } catch (\RuntimeException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $exception->getMessage();
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Sipariş talebiniz işlenirken beklenmedik bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
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

    return Helpers::url('products.php' . $queryString);
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
        if ($searchTerm !== '') {
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
    }
} catch (\PDOException $exception) {
    $errors[] = 'Ürünler yüklenirken bir hata oluştu. Lütfen yöneticiyle iletişime geçin.';
}

if ($showAllCategories) {
    $products = [];
}

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
                <a href="<?= Helpers::url('products.php') ?>" class="catalog-breadcrumb__link<?= $selectedCategoryId ? '' : ' is-current' ?>">Kategoriler</a>
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
        <form method="get" action="<?= Helpers::url('products.php') ?>" class="catalog-search-form">
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
        <section class="catalog-section">
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
                <a class="catalog-back" href="<?= Helpers::url('products.php') ?>"><i class="bi bi-arrow-left"></i> Tüm kategoriler</a>
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
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <?php
                        $productName = isset($product['name']) ? $product['name'] : 'Servis';
                        $productPrice = Helpers::formatCurrency(isset($product['price']) ? (float)$product['price'] : 0);
                        $categoryTrail = isset($product['category_id']) ? $categoryPath((int)$product['category_id']) : (isset($product['category_name']) ? $product['category_name'] : 'Kategori');
                        $rawDescription = isset($product['description']) ? trim($product['description']) : '';
                        if ($rawDescription === '') {
                            $rawDescription = Helpers::defaultProductDescription();
                        }
                        $shortDescription = Helpers::truncate($rawDescription, 140);
                        $skuValue = (isset($product['sku']) && $product['sku'] !== '') ? $product['sku'] : null;
                        ?>
                        <div class="product-card">
                            <div class="product-card__header">
                                <div>
                                    <h3 class="product-card__title"><?= Helpers::sanitize($productName) ?></h3>
                                    <div class="product-card__category"><?= Helpers::sanitize($categoryTrail) ?></div>
                                </div>
                                <div class="product-card__price"><?= Helpers::sanitize($productPrice) ?></div>
                            </div>
                            <?php if ($skuValue): ?>
                                <div class="product-card__sku">SKU: <?= Helpers::sanitize($skuValue) ?></div>
                            <?php endif; ?>
                            <p class="product-card__description"><?= Helpers::sanitize($shortDescription) ?></p>
                            <div class="product-card__actions">
                                <button type="button"
                                        class="product-card__button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#orderModal"
                                        data-product-id="<?= (int)$product['id'] ?>"
                                        data-product-name="<?= Helpers::sanitize($productName) ?>"
                                        data-product-price="<?= Helpers::sanitize($productPrice) ?>"
                                        data-product-sku="<?= Helpers::sanitize($skuValue ?: '-') ?>"
                                        data-product-category="<?= Helpers::sanitize($categoryTrail) ?>">
                                    Sipariş ver
                                </button>
                            </div>
                        </div>
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
