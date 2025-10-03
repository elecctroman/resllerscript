<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Integrations\ProviderClient;
use App\Settings;

Auth::requireRoles(array('super_admin', 'admin'));

$errors = array();
$products = array();
$orders = array();
$connectionSummary = null;

$config = Settings::getMany(array('provider_api_url', 'provider_api_key'));
$apiUrl = isset($config['provider_api_url']) ? trim((string)$config['provider_api_url']) : '';
$apiKey = isset($config['provider_api_key']) ? trim((string)$config['provider_api_key']) : '';

if ($apiUrl === '' || $apiKey === '') {
    $errors[] = 'Önce Genel Ayarlar üzerinden sağlayıcı API adresini ve anahtarını tanımlayın.';
} else {
    try {
        $client = new ProviderClient($apiUrl, $apiKey);

        try {
            $connectionSummary = $client->testConnection();
        } catch (\RuntimeException $exception) {
            $errors[] = 'API doğrulaması sırasında hata oluştu: ' . $exception->getMessage();
        }

        try {
            $productResponse = $client->fetchProducts();
            if (isset($productResponse['success']) && $productResponse['success'] && isset($productResponse['data']) && is_array($productResponse['data'])) {
                $products = $productResponse['data'];
            } else {
                $errors[] = 'Sağlayıcıdan ürün listesi alınamadı.';
            }
        } catch (\RuntimeException $exception) {
            $errors[] = 'Ürün listesi alınırken bir hata oluştu: ' . $exception->getMessage();
        }

        try {
            $orderResponse = $client->fetchOrders();
            if (isset($orderResponse['success']) && $orderResponse['success'] && isset($orderResponse['data']) && is_array($orderResponse['data'])) {
                $orders = $orderResponse['data'];
            } else {
                $errors[] = 'Sağlayıcı sipariş geçmişi alınamadı.';
            }
        } catch (\RuntimeException $exception) {
            $errors[] = 'Sipariş geçmişi alınırken bir hata oluştu: ' . $exception->getMessage();
        }
    } catch (\RuntimeException $exception) {
        $errors[] = 'Sağlayıcı API istemcisi oluşturulamadı: ' . $exception->getMessage();
    }
}

$pageTitle = 'Sağlayıcılar';

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Lotus Lisans Bağlantısı</h5>
                <a href="/admin/settings-general.php" class="btn btn-sm btn-outline-primary">Genel Ayarlara Git</a>
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

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 border rounded h-100">
                            <h6 class="text-muted text-uppercase small">API Bilgileri</h6>
                            <dl class="mb-0">
                                <dt class="small">API URL</dt>
                                <dd class="mb-2"><code><?= Helpers::sanitize($apiUrl ?: '-') ?></code></dd>
                                <dt class="small">API Anahtarı</dt>
                                <dd class="mb-0"><code><?= Helpers::sanitize($apiKey !== '' ? substr($apiKey, 0, 6) . '•••' : '-') ?></code></dd>
                            </dl>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded h-100">
                            <h6 class="text-muted text-uppercase small">Bağlantı Durumu</h6>
                            <?php if ($connectionSummary && isset($connectionSummary['success']) && $connectionSummary['success']): ?>
                                <p class="mb-1 text-success fw-semibold">Bağlantı başarılı.</p>
                                <?php if (isset($connectionSummary['data']['credit'])): ?>
                                    <p class="mb-0 text-muted">Sağlayıcı bakiyesi: <strong><?= Helpers::sanitize($connectionSummary['data']['credit']) ?></strong></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="mb-0 text-muted">Bağlantı durumu doğrulanamadı. Lütfen API bilgilerini kontrol edin.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sağlayıcı Ürünleri</h5>
                <span class="text-muted small"><?= Helpers::sanitize(count($products)) ?> ürün bulundu</span>
            </div>
            <div class="card-body">
                <?php if (!$products): ?>
                    <p class="text-muted mb-0">Henüz ürün verisi alınamadı.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Ürün</th>
                                <th>Fiyat</th>
                                <th>Stok</th>
                                <th>Durum</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= Helpers::sanitize(isset($product['id']) ? $product['id'] : '-') ?></td>
                                    <td>
                                        <strong><?= Helpers::sanitize(isset($product['title']) ? $product['title'] : 'Tanımsız') ?></strong>
                                        <?php if (!empty($product['content'])): ?>
                                            <div class="small text-muted mt-1"><?= Helpers::sanitize($product['content']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Helpers::sanitize(isset($product['amount']) ? $product['amount'] : '-') ?></td>
                                    <td><?= Helpers::sanitize(isset($product['stock']) ? $product['stock'] : '-') ?></td>
                                    <td>
                                        <?php if (!empty($product['available'])): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pasif</span>
                                        <?php endif; ?>
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

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sağlayıcı Siparişleri</h5>
                <span class="text-muted small"><?= Helpers::sanitize(count($orders)) ?> kayıt</span>
            </div>
            <div class="card-body">
                <?php if (!$orders): ?>
                    <p class="text-muted mb-0">Sipariş geçmişi bulunamadı.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Ürün</th>
                                <th>Durum</th>
                                <th>İçerik</th>
                                <th>Tarih</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?= Helpers::sanitize(isset($order['order_id']) ? $order['order_id'] : (isset($order['id']) ? $order['id'] : '-')) ?></td>
                                    <td><?= Helpers::sanitize(isset($order['product_id']) ? $order['product_id'] : '-') ?></td>
                                    <td><span class="badge bg-light text-dark"><?= Helpers::sanitize(isset($order['status']) ? strtoupper($order['status']) : '-') ?></span></td>
                                    <td>
                                        <?php if (!empty($order['content'])): ?>
                                            <code><?= Helpers::sanitize($order['content']) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= Helpers::sanitize(isset($order['created_at']) ? $order['created_at'] : '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">API Dokümantasyonu</h5>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#provider-docs" aria-expanded="false" aria-controls="provider-docs">Göster / Gizle</button>
            </div>
            <div class="collapse show" id="provider-docs">
                <div class="card-body">
                    <?php include __DIR__ . '/../views/admin/providers/docs.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
