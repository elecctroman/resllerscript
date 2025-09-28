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

                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= (int)$product['id'] ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize($product['name']) ?></strong><br>

                                    </td>
                                    <td><?= Helpers::sanitize($product['category_name']) ?></td>
                                    <td><?= Helpers::sanitize($product['sku'] ?? '-') ?></td>
                                    <td>$<?= number_format((float)$product['price'], 2, '.', ',') ?></td>

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

<?php include __DIR__ . '/templates/footer.php';
