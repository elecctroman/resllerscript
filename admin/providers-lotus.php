<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Services\LotusPartnerApi;
use App\Settings;
use Lotus\Exceptions\ApiError;

Auth::requireRoles(array('super_admin', 'admin'));

$pageTitle = 'Lotus Lisans Sağlayıcısı';

$errors = array();
$snapshot = null;
$lotusEnabled = LotusPartnerApi::isEnabled();

if ($lotusEnabled) {
    try {
        $snapshot = LotusPartnerApi::fetchSnapshot(100, 50);
    } catch (ApiError $apiError) {
        $errors[] = 'Lotus API hatası: ' . $apiError->getMessage();
    } catch (\Throwable $throwable) {
        $errors[] = 'Lotus API bağlantısı kurulamadı: ' . $throwable->getMessage();
    }
} else {
    $errors[] = 'Lotus API entegrasyonu devre dışı. Genel Ayarlar sayfasından bilgileri doldurup entegrasyonu etkinleştirin.';
}

include __DIR__ . '/../templates/header.php';

$baseUrlSetting = Settings::get('lotus_base_url');
$displayBaseUrl = $baseUrlSetting && $baseUrlSetting !== '' ? $baseUrlSetting : 'https://partner.lotuslisans.com.tr';
$useQuery = Settings::get('lotus_use_query_api_key') === '1';
$timeoutSetting = Settings::get('lotus_timeout');
$timeoutDisplay = $timeoutSetting !== null && $timeoutSetting !== '' ? $timeoutSetting : '20';

$userResponse = $snapshot && isset($snapshot['user']['response']) ? $snapshot['user']['response'] : array();
$userRequestId = $snapshot && isset($snapshot['user']['request_id']) ? $snapshot['user']['request_id'] : null;
$userData = isset($userResponse['data']) && is_array($userResponse['data']) ? $userResponse['data'] : array();

$productsResponse = $snapshot && isset($snapshot['products']['response']) ? $snapshot['products']['response'] : array();
$productsRequestId = $snapshot && isset($snapshot['products']['request_id']) ? $snapshot['products']['request_id'] : null;
$productsData = isset($productsResponse['data']) && is_array($productsResponse['data']) ? $productsResponse['data'] : array();

$ordersResponse = $snapshot && isset($snapshot['orders']['response']) ? $snapshot['orders']['response'] : array();
$ordersRequestId = $snapshot && isset($snapshot['orders']['request_id']) ? $snapshot['orders']['request_id'] : null;
$ordersData = isset($ordersResponse['data']) && is_array($ordersResponse['data']) ? $ordersResponse['data'] : array();
?>
<div class="row g-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <h1 class="h4 mb-0">Lotus Lisans Sağlayıcısı</h1>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= Helpers::url('admin/settings-general.php') ?>#lotus-settings" class="btn btn-sm btn-outline-primary">Genel Ayarları Düzenle</a>
                <a href="https://partner.lotuslisans.com.tr" target="_blank" rel="noreferrer" class="btn btn-sm btn-outline-secondary">Lotus Partner Paneli</a>
            </div>
        </div>
        <p class="text-muted">Lotus Lisans API kimlik bilgilerinizi doğrulayın, uzaktaki ürünleri ve sipariş geçmişini tek ekrandan inceleyin.</p>
        <?php if ($errors): ?>
            <div class="alert alert-warning">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= Helpers::sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Bağlantı Özeti</h2>
                <?php if ($userRequestId): ?>
                    <span class="badge text-bg-light">X-Request-Id: <?= Helpers::sanitize($userRequestId) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="small text-muted">Temel API URL'si</div>
                        <div class="fw-semibold"><?= Helpers::sanitize($displayBaseUrl) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Kimlik Doğrulama</div>
                        <div class="fw-semibold">X-API-Key header<?= $useQuery ? ' + query param' : '' ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Zaman Aşımı (sn)</div>
                        <div class="fw-semibold"><?= Helpers::sanitize($timeoutDisplay) ?></div>
                    </div>
                </div>
                <hr>
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="small text-muted">Güncel Kredi</div>
                        <div class="fw-semibold">
                            <?php if (isset($userData['credit'])): ?>
                                <?= Helpers::sanitize(is_numeric($userData['credit']) ? number_format((float)$userData['credit'], 2, ',', '.') : (string)$userData['credit']) ?> TL
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Kullanıcı Adı</div>
                        <div class="fw-semibold"><?= isset($userData['nickname']) ? Helpers::sanitize((string)$userData['nickname']) : '-' ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">E-posta</div>
                        <div class="fw-semibold"><?= isset($userData['email']) ? Helpers::sanitize((string)$userData['email']) : '-' ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Son Güncelleme</div>
                        <div class="fw-semibold"><?= Helpers::sanitize(date('d.m.Y H:i')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Lotus Ürünleri</h2>
                <?php if ($productsRequestId): ?>
                    <span class="badge text-bg-light">X-Request-Id: <?= Helpers::sanitize($productsRequestId) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($productsData): ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Başlık</th>
                                    <th scope="col">Tutar</th>
                                    <th scope="col">Stok</th>
                                    <th scope="col">Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productsData as $product): ?>
                                    <tr>
                                        <td class="text-nowrap">#<?= Helpers::sanitize(isset($product['id']) ? (string)$product['id'] : '-') ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= Helpers::sanitize(isset($product['title']) ? (string)$product['title'] : '-') ?></div>
                                            <?php if (!empty($product['content'])): ?>
                                                <div class="text-muted small"><?= Helpers::sanitize(mb_strimwidth((string)$product['content'], 0, 120, '…')) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= Helpers::sanitize(isset($product['amount']) ? (string)$product['amount'] : '-') ?> TL</td>
                                        <td><?= Helpers::sanitize(isset($product['stock']) ? (string)$product['stock'] : '-') ?></td>
                                        <td>
                                            <?php if (isset($product['available']) && $product['available']): ?>
                                                <span class="badge text-bg-success">Siparişe Açık</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light mb-0">Lotus API'den ürün listesi alınamadı veya ürün bulunmuyor.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Son Siparişler</h2>
                <?php if ($ordersRequestId): ?>
                    <span class="badge text-bg-light">X-Request-Id: <?= Helpers::sanitize($ordersRequestId) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($ordersData): ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">Sipariş ID</th>
                                    <th scope="col">Ürün</th>
                                    <th scope="col">Tutar</th>
                                    <th scope="col">Durum</th>
                                    <th scope="col">Oluşturma</th>
                                    <th scope="col">İçerik</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ordersData as $order): ?>
                                    <tr>
                                        <td class="text-nowrap">#<?= Helpers::sanitize(isset($order['id']) ? (string)$order['id'] : '-') ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= Helpers::sanitize(isset($order['product_title']) ? (string)$order['product_title'] : '-') ?></div>
                                            <?php if (isset($order['product_id'])): ?>
                                                <div class="text-muted small">Ürün ID: <?= Helpers::sanitize((string)$order['product_id']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= Helpers::sanitize(isset($order['amount']) ? (string)$order['amount'] : '-') ?> TL</td>
                                        <td>
                                            <?php $status = isset($order['status']) ? strtolower((string)$order['status']) : 'unknown'; ?>
                                            <?php if ($status === 'completed'): ?>
                                                <span class="badge text-bg-success">Tamamlandı</span>
                                            <?php elseif ($status === 'pending'): ?>
                                                <span class="badge text-bg-warning text-dark">Beklemede</span>
                                            <?php elseif ($status === 'cancelled'): ?>
                                                <span class="badge text-bg-secondary">İptal</span>
                                            <?php elseif ($status === 'failed'): ?>
                                                <span class="badge text-bg-danger">Başarısız</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-light text-dark">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $createdAt = isset($order['created_at']) ? (string)$order['created_at'] : '';
                                                $formatted = '-';
                                                if ($createdAt !== '') {
                                                    try {
                                                        $date = new DateTimeImmutable($createdAt);
                                                        $formatted = $date->format('d.m.Y H:i');
                                                    } catch (\Exception $e) {
                                                        $formatted = $createdAt;
                                                    }
                                                }
                                            ?>
                                            <?= Helpers::sanitize($formatted) ?>
                                        </td>
                                        <td style="min-width: 220px;">
                                            <?php if (!empty($order['content'])): ?>
                                                <pre class="mb-0 small bg-light p-2 rounded"><?= Helpers::sanitize((string)$order['content']) ?></pre>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light mb-0">Lotus API'den sipariş geçmişi alınamadı veya sipariş bulunmuyor.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
