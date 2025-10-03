<?php
require __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Migrations\Schema;

Auth::requireRoles(array('super_admin', 'admin'));

Schema::ensure();

$pdo = Database::connection();

$providerStmt = $pdo->prepare('SELECT * FROM providers WHERE name = :name LIMIT 1');
$providerStmt->execute(array(':name' => 'Lotus'));
$provider = $providerStmt->fetch();

if (!$provider) {
    $provider = array(
        'api_url' => 'https://partner.lotuslisans.com.tr',
        'api_key' => '',
        'status' => 0,
    );
}

$apiUrl = (string) $provider['api_url'];
$apiKey = (string) $provider['api_key'];
$timeoutMs = isset($provider['timeout_ms']) ? (int) $provider['timeout_ms'] : 20000;

$maskedKey = $apiKey === '' ? '' : str_repeat('•', max(0, strlen($apiKey) - 4)) . substr($apiKey, -4);

$GLOBALS['pageScripts'] = isset($GLOBALS['pageScripts']) && is_array($GLOBALS['pageScripts']) ? $GLOBALS['pageScripts'] : array();
$GLOBALS['pageScripts'][] = '/public/assets/js/providers.js';

$pageTitle = 'Sağlayıcı Ayarları';

include __DIR__ . '/../../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Lotus API Ayarları</h5>
                    <small class="text-muted">Sağlayıcı bilgilerini güncelleyin ve bağlantıyı test edin.</small>
                </div>
                <span class="badge bg-light text-dark" id="provider-test-status">Beklemede</span>
            </div>
            <div class="card-body">
                <form id="provider-settings-form" autocomplete="off">
                    <input type="hidden" name="action" value="save_settings">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Helpers::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label for="provider-api-url" class="form-label">API URL</label>
                        <input type="url" class="form-control" id="provider-api-url" name="api_url" value="<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://partner.lotuslisans.com.tr" required>
                        <div class="form-text">URL'yi <strong>https://alanadiniz.com</strong> formatında girin. Sonunda <code>/api</code> eklemeyin.</div>
                    </div>
                    <div class="mb-3 position-relative">
                        <label for="provider-api-key" class="form-label">API Anahtarı</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="provider-api-key" name="api_key" value="<?= htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8') ?>" autocomplete="new-password" placeholder="Yeni anahtar girin" aria-describedby="provider-key-toggle">
                            <button class="btn btn-outline-secondary" type="button" id="provider-key-toggle" data-visible="0" aria-label="API anahtarını göster">Göster</button>
                        </div>
                        <?php if ($apiKey !== ''): ?>
                            <div class="form-text">Kayıtlı anahtar: <span class="fw-semibold"><?= htmlspecialchars($maskedKey, ENT_QUOTES, 'UTF-8') ?></span></div>
                        <?php else: ?>
                            <div class="form-text text-muted">Henüz bir anahtar kaydedilmedi.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="provider-timeout" class="form-label">Zaman aşımı (ms)</label>
                        <input type="number" class="form-control" id="provider-timeout" name="timeout_ms" value="<?= (int) $timeoutMs ?>" min="5000" step="500">
                        <div class="form-text">Varsayılan: 20000 ms. Yüksek ping veya yoğunluk durumunda artırabilirsiniz.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                        <button type="button" class="btn btn-outline-success" id="provider-test-button" data-action="test-connection">Bağlantıyı Test Et</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Bilgilendirme</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Lotus Partner API ile doğrudan entegrasyon kurarak ürünleri panelinize aktarabilir ve siparişleri otomatik eşleyebilirsiniz.</p>
                <ul class="small text-muted ps-3">
                    <li>API anahtarınızın Lotus panelinde IP kısıtlaması varsa bu sunucunun IP adresini yetkilendirin.</li>
                    <li>Bağlantıyı test ettikten sonra ürünleri <strong>Lotus Ürünleri</strong> sayfasından panelinize aktarabilirsiniz.</li>
                    <li>Günlük loglar <code>storage/lotus.log</code> dosyasına yazılır.</li>
                </ul>
                <div class="alert alert-warning small mb-0">
                    <strong>Güvenlik:</strong> API anahtarınızı yalnızca güvenilir yöneticilerle paylaşın. Bu sayfa anahtarı varsayılan olarak gizler.
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php';
