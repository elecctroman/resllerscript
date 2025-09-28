<?php
require __DIR__ . '/bootstrap.php';

use App\Database;
use App\Helpers;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];

if ($user['role'] === 'admin') {
    Helpers::redirect('/admin/products.php');
}

$selectedCategoryId = isset($_GET['category']) ? max(0, (int)$_GET['category']) : 0;

$pdo = Database::connection();

$errors = [];
$success = $_SESSION['flash_success'] ?? '';

if ($success) {
    unset($_SESSION['flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCategoryId = isset($_POST['redirect_category']) ? max(0, (int)$_POST['redirect_category']) : $selectedCategoryId;

    if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Oturum doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $productId = (int)($_POST['product_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        if ($productId <= 0) {
            $errors[] = 'Sipariş verilecek ürün seçilemedi.';
        }

        if ($note && function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') > 500 : strlen($note) > 500) {
            $errors[] = 'Sipariş notu en fazla 500 karakter olabilir.';
        }

        if (!$errors) {
            try {
                $productStmt = $pdo->prepare('SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.id = :id AND pr.status = :status');
                $productStmt->execute([
                    'id' => $productId,
                    'status' => 'active',
                ]);
                $product = $productStmt->fetch();

                if (!$product) {
                    $errors[] = 'Ürün bulunamadı veya pasif durumda.';
                } else {
                    $pdo->prepare('INSERT INTO product_orders (product_id, user_id, note, price, status, created_at) VALUES (:product_id, :user_id, :note, :price, :status, NOW())')->execute([
                        'product_id' => $productId,
                        'user_id' => $user['id'],
                        'note' => $note ?: null,
                        'price' => $product['price'],
                        'status' => 'pending',
                    ]);

                    $_SESSION['flash_success'] = 'Sipariş talebiniz alındı. Ürün teslimatı kısa süre içinde gerçekleştirilecektir.';

                    $redirectQuery = $selectedCategoryId ? '?category=' . $selectedCategoryId : '';
                    Helpers::redirect('/products.php' . $redirectQuery);
                }
            } catch (\PDOException $exception) {
                $errors[] = 'Sipariş talebiniz kaydedilirken bir veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.';
            }
        }
    }
}

$categories = [];
$products = [];

try {
    $categories = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();

    $productsQuery = 'SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.status = :status';
    $params = ['status' => 'active'];

    if ($selectedCategoryId) {
        $productsQuery .= ' AND pr.category_id = :category_id';
        $params['category_id'] = $selectedCategoryId;
    }

    $productsQuery .= ' ORDER BY cat.name, pr.name';
    $productsStmt = $pdo->prepare($productsQuery);
    $productsStmt->execute($params);
    $products = $productsStmt->fetchAll();
} catch (\PDOException $exception) {
    $errors[] = 'Ürünler yüklenirken bir hata oluştu. Lütfen yöneticiyle iletişime geçin.';
}

$pageTitle = 'Ürün Kataloğu';

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Kategoriler</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php if (!$categories): ?>
                    <div class="list-group-item text-muted">Tanımlı kategori bulunmuyor.</div>
                <?php else: ?>
                    <a href="/products.php"
                       class="list-group-item list-group-item-action <?= $selectedCategoryId === 0 ? 'active' : '' ?>">
                        Tüm Ürünler
                    </a>
                    <?php foreach ($categories as $category): ?>
                        <a href="/products.php?category=<?= (int)$category['id'] ?>"
                           class="list-group-item list-group-item-action <?= $selectedCategoryId === (int)$category['id'] ? 'active' : '' ?>">
                            <?= Helpers::sanitize($category['name']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Ürünler</h5>
                <span class="text-muted small">Fiyatlar USD cinsindendir.</span>
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
                    <div class="alert alert-success mb-4"><?= Helpers::sanitize($success) ?></div>
                <?php endif; ?>

                <?php if (!$products): ?>
                    <p class="text-muted mb-0">Seçili kategori için görüntülenecek ürün bulunmuyor.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Ürün</th>
                                <th>Kategori</th>
                                <th>SKU</th>
                                <th>Fiyat</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= (int)$product['id'] ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($product['name']) ?></strong><br>
                                        <?php
                                        $rawDescription = trim($product['description'] ?? '');
                                        if ($rawDescription === '') {
                                            $rawDescription = Helpers::defaultProductDescription();
                                        }
                                        $displayDescription = Helpers::truncate($rawDescription, 160);
                                        ?>
                                        <small class="text-muted"><?= Helpers::sanitize($displayDescription) ?></small>
                                    </td>
                                    <td><?= Helpers::sanitize($product['category_name']) ?></td>
                                    <td><?= Helpers::sanitize($product['sku'] ?? '-') ?></td>
                                    <td>$<?= number_format((float)$product['price'], 2, '.', ',') ?></td>
                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#orderModal"
                                                data-product-id="<?= (int)$product['id'] ?>"
                                                data-product-name="<?= Helpers::sanitize($product['name']) ?>"
                                                data-product-price="$<?= number_format((float)$product['price'], 2, '.', ',') ?>"
                                                data-product-sku="<?= Helpers::sanitize($product['sku'] ?? '-') ?>"
                                                data-product-category="<?= Helpers::sanitize($product['category_name']) ?>">
                                            Sipariş Ver
                                        </button>
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
                            <div class="fw-semibold">Fiyat</div>
                            <div id="orderProductPrice">$0.00</div>
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
            orderModal.querySelector('#orderProductPrice').textContent = dataset.productPrice || '$0.00';
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
