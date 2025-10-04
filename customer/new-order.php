<?php
require __DIR__ . '/../bootstrap.php';

use App\Customers\CustomerAuth;
use App\Customers\OrderService;
use App\Database;
use App\Helpers;

$customer = CustomerAuth::ensureCustomer();
$pdo = Database::connection();

$products = $pdo->query("SELECT id, name, price FROM products WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$errors = array();
$success = null;
$productId = 0;
$quantity = 1;
$paymentMethod = 'Cuzdan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } else {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $paymentMethod = $_POST['payment_method'] ?? 'Cuzdan';

        $productStmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = :id AND status = 'active' LIMIT 1");
        $productStmt->execute(array(':id' => $productId));
        $product = $productStmt->fetch();
        if (!$product) {
            $errors[] = 'Seçilen ürün bulunamadı.';
        }

        if (!$errors) {
            $total = (float)$product['price'] * $quantity;
            try {
                $orderId = OrderService::placeOrder((int)$customer['id'], (int)$product['id'], $quantity, $total, $paymentMethod, array(
                    'source' => 'customer_panel',
                ));
                $success = 'Siparişiniz oluşturuldu. Sipariş numaranız #' . $orderId;
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
    }
}

$pageTitle = 'Yeni Sipariş';
require __DIR__ . '/../templates/customer-header.php';
?>
<div class="card customer-card">
    <div class="card-header">
        <h5 class="card-title mb-0">Yeni Sipariş Oluştur</h5>
    </div>
    <div class="card-body">
        <?php if ($success): ?>
            <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= Helpers::sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
            <div class="col-md-6">
                <label class="form-label" for="product_id">Ürün</label>
                <select class="form-select" id="product_id" name="product_id" required>
                    <option value="">Ürün seçin</option>
                    <?php foreach ($products as $item): ?>
                        <option value="<?= (int)$item['id'] ?>"<?= ((int)($productId ?? 0) === (int)$item['id']) ? ' selected' : '' ?>><?= Helpers::sanitize($item['name']) ?> - <?= number_format((float)$item['price'], 2, ',', '.') ?> <?= Helpers::sanitize($customer['currency'] ?? 'TRY') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="quantity">Adet</label>
                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="<?= (int)($quantity ?? 1) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="payment_method">Ödeme Yöntemi</label>
                <select class="form-select" id="payment_method" name="payment_method">
                    <option value="Cuzdan"<?= (($_POST['payment_method'] ?? '') === 'Cuzdan') ? ' selected' : '' ?>>Cüzdan</option>
                    <option value="Shopier"<?= (($_POST['payment_method'] ?? '') === 'Shopier') ? ' selected' : '' ?>>Shopier</option>
                    <option value="PayTR"<?= (($_POST['payment_method'] ?? '') === 'PayTR') ? ' selected' : '' ?>>PayTR</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Sipariş Oluştur</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../templates/customer-footer.php';
