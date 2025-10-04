<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Database;
use App\Helpers;
use App\Services\ProductStockService;

Auth::requireRoles(array('super_admin', 'admin', 'content'));

$pdo = Database::connection();
$currentUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;

$productId = isset($_GET['product_id']) ? max(0, (int) $_GET['product_id']) : 0;
$status = isset($_GET['status']) ? strtolower((string) $_GET['status']) : 'available';
$allowedStatuses = array('available', 'reserved', 'delivered', 'all');
$statusLabels = array(
    'available' => 'Hazır',
    'reserved' => 'Ayrılmış',
    'delivered' => 'Teslim',
    'all' => 'Tümü',
);
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'available';
}

$pageTitle = $productId > 0 ? 'Ürün Stok Yönetimi' : 'Stok Özeti';
$errors = array();
$successFlash = Helpers::getFlash('success');
$warningFlash = Helpers::getFlash('warning');

if ($productId > 0) {
    $productStmt = $pdo->prepare('SELECT pr.*, cat.name AS category_name FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id WHERE pr.id = :id LIMIT 1');
    $productStmt->execute(array('id' => $productId));
    $product = $productStmt->fetch();

    if (!$product) {
        Helpers::setFlash('warning', 'Ürün kaydı bulunamadı.');
        Helpers::redirect('/admin/product-stock.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
        $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

        if (!Helpers::verifyCsrf($token)) {
            $errors[] = 'Oturum doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
        } else {
            if ($action === 'add_stock') {
                $rawItems = isset($_POST['stock_items']) ? (string) $_POST['stock_items'] : '';
                $lines = preg_split('/\r\n|\r|\n/', $rawItems);
                $lines = array_filter(array_map('trim', $lines), static function ($line) {
                    return $line !== '';
                });

                if (!$lines) {
                    $errors[] = 'Eklenecek stok satırları girilmelidir.';
                } else {
                    try {
                        $result = ProductStockService::addStockItems($productId, $lines);
                        if ($currentUser) {
                            AuditLog::record(
                                $currentUser['id'],
                                'product.stock.add',
                                'product',
                                $productId,
                                sprintf('%d stok satırı eklendi (atlan: %d).', $result['added'], $result['skipped'])
                            );
                        }

                        Helpers::redirectWithFlash(
                            '/admin/product-stock.php?product_id=' . $productId . '&status=' . $status,
                            array('success' => sprintf('%d stok satırı eklendi. %d satır atlandı.', $result['added'], $result['skipped']))
                        );
                    } catch (RuntimeException $exception) {
                        $errors[] = $exception->getMessage();
                    }
                }
            } elseif ($action === 'delete_stock') {
                $itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
                if ($itemId <= 0) {
                    $errors[] = 'Silinecek stok kaydı seçilemedi.';
                } else {
                    if (ProductStockService::deleteStockItem($productId, $itemId)) {
                        if ($currentUser) {
                            AuditLog::record(
                                $currentUser['id'],
                                'product.stock.delete',
                                'product',
                                $productId,
                                sprintf('Stok kaydı #%d silindi.', $itemId)
                            );
                        }

                        Helpers::redirectWithFlash(
                            '/admin/product-stock.php?product_id=' . $productId . '&status=' . $status,
                            array('success' => 'Stok kaydı silindi.')
                        );
                    } else {
                        $errors[] = 'Stok kaydı silinemedi. Yalnızca kullanılmamış stoklar kaldırılabilir.';
                    }
                }
            }
        }
    }

    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $perPage = 50;
    $offset = ($page - 1) * $perPage;

    $summary = ProductStockService::stockSummary($productId);
    $pagination = ProductStockService::paginateStock($productId, $status, $perPage, $offset);
    $items = $pagination['items'];
    $totalItems = $pagination['total'];
    $totalPages = max(1, (int) ceil($totalItems / $perPage));

    include __DIR__ . '/../templates/header.php';
    ?>
    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Stok Ekle</h5>
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

                    <?php if ($successFlash): ?>
                        <div class="alert alert-success"><?= Helpers::sanitize($successFlash) ?></div>
                    <?php endif; ?>

                    <?php if ($warningFlash): ?>
                        <div class="alert alert-warning"><?= Helpers::sanitize($warningFlash) ?></div>
                    <?php endif; ?>

                    <p class="text-muted small mb-3">
                        Her satır bir stok kaydı olarak kaydedilir. Boş satırlar yok sayılır ve tekrar girilen değerler otomatik olarak atlanır.
                    </p>

                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="action" value="add_stock">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                        <div>
                            <label class="form-label">Stok Satırları</label>
                            <textarea name="stock_items" rows="8" class="form-control" placeholder="Her satıra bir stok değeri girin"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Stokları Ekle</button>
                    </form>
                </div>
                <div class="card-footer bg-white">
                    <div class="small text-muted">
                        <div>Ürün: <strong><?= Helpers::sanitize($product['name']) ?></strong></div>
                        <div>Kategori: <?= Helpers::sanitize(isset($product['category_name']) ? $product['category_name'] : '-') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Stok Kayıtları</h5>
                        <div class="small text-muted">Toplam: <?= (int) $totalItems ?></div>
                    </div>
                    <div class="btn-group">
                        <?php foreach ($allowedStatuses as $state): ?>
                            <?php
                            $label = isset($statusLabels[$state]) ? $statusLabels[$state] : ucfirst($state);
                            $countValue = 0;
                            if ($state === 'all') {
                                if (isset($summary['all'])) {
                                    $countValue = (int) $summary['all'];
                                } else {
                                    $countValue = (int) array_sum($summary);
                                }
                            } elseif (isset($summary[$state])) {
                                $countValue = (int) $summary[$state];
                            }
                            ?>
                            <a href="<?= Helpers::sanitize('/admin/product-stock.php?product_id=' . $productId . '&status=' . $state) ?>"
                               class="btn btn-sm <?= $status === $state ? 'btn-primary' : 'btn-outline-secondary' ?>">
                                <?= Helpers::sanitize($label) ?>
                                <span class="badge bg-light text-dark ms-1"><?= $countValue ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!$items): ?>
                        <p class="text-muted mb-0">Seçili durumda görüntülenecek stok kaydı bulunmuyor.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>İçerik</th>
                                    <th>Durum</th>
                                    <th>Oluşturulma</th>
                                    <th>İşlemler</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= (int) $item['id'] ?></td>
                                        <td class="font-monospace small"><?= nl2br(Helpers::sanitize($item['content'])) ?></td>
                                        <td>
                                            <?php
                                            $badgeMap = array(
                                                'available' => 'success',
                                                'reserved' => 'warning',
                                                'delivered' => 'secondary',
                                            );
                                            $statusLabel = isset($item['status']) ? (string) $item['status'] : 'available';
                                            $badgeClass = isset($badgeMap[$statusLabel]) ? $badgeMap[$statusLabel] : 'light';
                                            echo '<span class="badge bg-' . Helpers::sanitize($badgeClass) . '">' . Helpers::sanitize(ucfirst($statusLabel)) . '</span>';
                                            if (!empty($item['order_id'])) {
                                                echo '<div class="small text-muted">Sipariş #' . (int) $item['order_id'] . '</div>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['created_at'])): ?>
                                                <?= Helpers::sanitize(date('d.m.Y H:i', strtotime($item['created_at']))) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($item['status']) && $item['status'] === 'available'): ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Seçili stok kaydı silinsin mi?');">
                                                    <input type="hidden" name="action" value="delete_stock">
                                                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">İşlem yok</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-3">
                                <ul class="pagination pagination-sm mb-0">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= Helpers::sanitize('/admin/product-stock.php?product_id=' . $productId . '&status=' . $status . '&page=' . $i) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../templates/footer.php';
    exit;
}

$list = $pdo->query('SELECT pr.id, pr.name, cat.name AS category_name, (SELECT COUNT(*) FROM product_stock_items psi WHERE psi.product_id = pr.id AND psi.status = "available") AS available_stock, (SELECT COUNT(*) FROM product_stock_items psi WHERE psi.product_id = pr.id AND psi.status = "delivered") AS delivered_stock FROM products pr INNER JOIN categories cat ON pr.category_id = cat.id ORDER BY pr.created_at DESC')->fetchAll();

include __DIR__ . '/../templates/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Ürün Stok Özeti</h5>
        <a class="btn btn-sm btn-outline-primary" href="/admin/products.php">Ürün Listesine Dön</a>
    </div>
    <div class="card-body">
        <?php if ($successFlash): ?>
            <div class="alert alert-success mb-3"><?= Helpers::sanitize($successFlash) ?></div>
        <?php endif; ?>

        <?php if ($warningFlash): ?>
            <div class="alert alert-warning mb-3"><?= Helpers::sanitize($warningFlash) ?></div>
        <?php endif; ?>

        <?php if ($list): ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Ürün</th>
                        <th>Kategori</th>
                        <th>Hazır Stok</th>
                        <th>Teslim Edilen</th>
                        <th class="text-end">İşlemler</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($list as $row): ?>
                        <tr>
                            <td><?= (int) $row['id'] ?></td>
                            <td><?= Helpers::sanitize($row['name']) ?></td>
                            <td><?= Helpers::sanitize($row['category_name']) ?></td>
                            <td>
                                <?php if ((int) $row['available_stock'] > 0): ?>
                                    <span class="badge bg-success"><?= (int) $row['available_stock'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Stok Yok</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $row['delivered_stock'] ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="/admin/product-stock.php?product_id=<?= (int) $row['id'] ?>">Stok Yönet</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">Henüz stok yönetimi yapılacak ürün bulunmuyor.</p>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
