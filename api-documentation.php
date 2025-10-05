<?php
require __DIR__ . '/bootstrap.php';

use App\Helpers;
use App\Lang;
use App\Settings;

Lang::boot();

$pageTitle = 'API Dökümantasyonu';
$metaDescription = 'Reseller paneliniz için sipariş, ürün ve webhook işlemlerini kapsayan REST API entegrasyon rehberi.';
$baseUrl = Helpers::apiBaseUrl();
$rateLimit = Settings::get('api_rate_limit_per_minute', 120);
$rateLimit = $rateLimit ? (int)$rateLimit : 120;

Helpers::includeTemplate('public-header.php', array(
    'pageTitle' => $pageTitle,
    'metaDescription' => $metaDescription,
));
?>
<div class="api-doc-hero public-hero text-center mb-5">
    <h1 class="display-4 fw-semibold mb-3">REST API Dökümantasyonu</h1>
    <p class="lead mb-4">Bayi panelinizi dış sistemlere bağlayın, ürün ve sipariş süreçlerini otomatikleştirin, webhook bildirimleriyle güncel kalın.</p>
    <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
        <div class="api-doc-pill">
            <i class="bi bi-globe2 me-2"></i>
            Temel URL: <code><?= Helpers::sanitize($baseUrl) ?></code>
        </div>
        <div class="api-doc-pill">
            <i class="bi bi-shield-lock me-2"></i>
            Kimlik Doğrulama: <code>Authorization: Bearer &lt;API_TOKEN&gt;</code>
        </div>
        <div class="api-doc-pill">
            <i class="bi bi-speedometer2 me-2"></i>
            Rate Limit: dakikada <?= (int)$rateLimit ?> istek
        </div>
    </div>
</div>

<section class="api-doc-section mb-5" id="quickstart">
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="api-doc-card h-100">
                <h2 class="h4 mb-3">Hızlı Başlangıç</h2>
                <p class="text-muted">API entegrasyonuna başlamak için geçerli bir bayi hesabı ve aktif bir API anahtarı yeterlidir.</p>
                <ol class="mb-0">
                    <li>Bayi panelinizden <strong>Profil &gt; API Erişimi</strong> alanına gidin.</li>
                    <li>Yeni bir API anahtarı oluşturun veya mevcut anahtarınızı kopyalayın.</li>
                    <li>IP beyaz liste, OTP ve webhook adresi gibi ek güvenlik ayarlarını yapılandırın.</li>
                    <li>Aşağıdaki cURL örneğini kullanarak bağlantınızı test edin.</li>
                </ol>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="api-doc-card h-100">
                <h2 class="h4 mb-3">cURL Örneği</h2>
<pre class="api-doc-code"><code>curl -X GET "<?= Helpers::sanitize($baseUrl) ?>/products" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"</code></pre>
                <p class="small text-muted mb-0">Alternatif olarak <code>X-API-Key</code> başlığını veya <code>api_key</code> sorgu parametresini kullanabilirsiniz.</p>
            </div>
        </div>
    </div>
</section>

<section class="api-doc-section mb-5" id="auth">
    <h2 class="h3 mb-4">Kimlik Doğrulama &amp; Güvenlik</h2>
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="api-doc-card h-100">
                <h3 class="h5 mb-3">Zorunlu Başlıklar</h3>
                <ul class="mb-0">
                    <li><code>Authorization: Bearer &lt;API_TOKEN&gt;</code></li>
                    <li>veya <code>X-API-Key: &lt;API_TOKEN&gt;</code></li>
                    <li>Opsiyonel: <code>X-Reseller-Email</code> ile anahtar sahibinin e-postası</li>
                </ul>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="api-doc-card h-100">
                <h3 class="h5 mb-3">Ek Koruma Katmanları</h3>
                <ul class="mb-0">
                    <li>IP Beyaz Liste: Panelden güvenilir IP/CIDR aralıklarını tanımlayın.</li>
                    <li>OTP Doğrulama: <code>X-API-OTP</code> başlığıyla 6 haneli TOTP kodu gönderin.</li>
                    <li>Captcha: <code>X-Captcha-Token</code> başlığı <code>api_captcha_secret</code> ile eşleşmelidir.</li>
                </ul>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="api-doc-card h-100">
                <h3 class="h5 mb-3">Rate Limit &amp; Loglama</h3>
                <ul class="mb-0">
                    <li>Varsayılan limit: dakikada <?= (int)$rateLimit ?> istek.</li>
                    <li>Limit aşıldığında HTTP <code>429</code> ve açıklayıcı mesaj döner.</li>
                    <li>Tüm istekler <code>api_request_logs</code> tablosuna kaydedilir.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="api-doc-section mb-5" id="errors">
    <h2 class="h3 mb-3">Hata Yanıtları</h2>
    <p class="text-muted">Tüm uç noktalar JSON formatında yanıt verir. Başarısız isteklerde HTTP durum kodu ve açıklayıcı bir mesaj bulunur.</p>
<pre class="api-doc-code"><code>{
  "success": false,
  "error": "API anahtarı doğrulanamadı."
}</code></pre>
    <p class="small text-muted mb-0">Alan doğrulama hatalarında HTTP <code>422</code>, yetki ihlallerinde <code>403</code>, kimlik doğrulama sorunlarında <code>401</code> döner.</p>
</section>

<section class="api-doc-section mb-5" id="products">
    <h2 class="h3 mb-3">Ürünler</h2>
    <div class="api-doc-card mb-4">
        <h3 class="h5">GET /products</h3>
        <p>Aktif ürünleri ve kategori ağacını döndürür. Yanıtta bayi bakiyesi ve temel bilgileri de yer alır.</p>
<pre class="api-doc-code"><code>GET <?= Helpers::sanitize($baseUrl) ?>/products
</code></pre>
        <p class="mb-2">Örnek yanıt:</p>
<pre class="api-doc-code"><code>{
  "success": true,
  "data": {
    "reseller": {
      "id": 12,
      "name": "Demo Bayi",
      "balance": 3500.5
    },
    "categories": [
      {"id": 1, "name": "Oyun", "parent_id": null}
    ],
    "products": [
      {
        "id": 24,
        "name": "Steam Cüzdan 100 TL",
        "sku": "STM-100",
        "price": 89.90,
        "category_id": 1,
        "category_name": "Oyun"
      }
    ]
  }
}</code></pre>
    </div>
</section>

<section class="api-doc-section mb-5" id="orders">
    <h2 class="h3 mb-3">Siparişler</h2>
    <div class="api-doc-card mb-4">
        <h3 class="h5">GET /orders</h3>
        <p>API üzerinden oluşturulmuş veya panele yansıyan siparişlerin listesini döndürür. Parametreler isteğe bağlıdır.</p>
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle api-doc-table">
                <thead>
                    <tr>
                        <th>Parametre</th>
                        <th>Tür</th>
                        <th>Açıklama</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>external_reference</code></td>
                        <td>string</td>
                        <td>Panelde sipariş oluşturulurken verdiğiniz referans.</td>
                    </tr>
                    <tr>
                        <td><code>status</code></td>
                        <td>string</td>
                        <td><code>pending</code>, <code>processing</code>, <code>completed</code> veya <code>cancelled</code>.</td>
                    </tr>
                    <tr>
                        <td><code>since</code></td>
                        <td>datetime</td>
                        <td>ISO8601 formatında güncellenme tarihine göre filtreleme.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="api-doc-card mb-4">
        <h3 class="h5">POST /orders</h3>
        <p>Stok ve bakiye kontrolleri yapılarak yeni bir sipariş oluşturur.</p>
<pre class="api-doc-code"><code>POST <?= Helpers::sanitize($baseUrl) ?>/orders
Content-Type: application/json

{
  "order_id": "EXT-2024001",
  "currency": "TRY",
  "customer": {
    "name": "Ahmet Yılmaz",
    "email": "musteri@example.com"
  },
  "items": [
    {"sku": "STM-100", "quantity": 1, "note": "Hediye"}
  ]
}</code></pre>
        <p class="mb-2">Başarılı yanıt örneği:</p>
<pre class="api-doc-code"><code>{
  "success": true,
  "data": {
    "orders": [4512],
    "remaining_balance": 3120.60
  }
}</code></pre>
        <p class="small text-muted mb-0">Bakiye yetersizliğinde veya stok bulunamadığında HTTP <code>422</code> döner ve işlem geri alınır.</p>
    </div>
    <div class="api-doc-card">
        <h3 class="h5">POST /token-webhook</h3>
        <p>API anahtarınıza ait webhook adresini günceller. <code>manage</code> yetkisi gereklidir.</p>
<pre class="api-doc-code"><code>POST <?= Helpers::sanitize($baseUrl) ?>/token-webhook
Content-Type: application/json

{
  "webhook_url": "https://entegrasyon.ornek.com/api/webhooks/reseller"
}</code></pre>
    </div>
</section>

<section class="api-doc-section mb-5" id="webhooks">
    <h2 class="h3 mb-3">Webhook Bildirimleri</h2>
    <div class="api-doc-card">
        <p>Webhook adresi tanımladığınızda sipariş durum değişiklikleri anlık olarak iletilir.</p>
        <p class="mb-2">Gönderim örneği:</p>
<pre class="api-doc-code"><code>{
  "event": "order_status_changed",
  "order_id": 4512,
  "remote_order_id": 4512,
  "status": "completed",
  "previous_status": "processing",
  "external_reference": "EXT-2024001",
  "sku": "STM-100",
  "quantity": 1,
  "total": 89.90,
  "admin_note": null,
  "external_order": {
    "id": "EXT-2024001",
    "currency": "TRY",
    "customer": {
      "name": "Ahmet Yılmaz"
    }
  }
}</code></pre>
        <p class="small text-muted mb-0">İstekler <code>Authorization: Bearer &lt;API_TOKEN&gt;</code> başlığı ile gönderilir. 2xx dışındaki yanıtlar yeniden deneme kuyruğuna eklenir.</p>
    </div>
</section>

<section class="api-doc-section mb-5" id="best-practices">
    <h2 class="h3 mb-3">En İyi Uygulamalar</h2>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="api-doc-card h-100">
                <ul class="mb-0">
                    <li>API anahtarınızı gizli tutun ve sadece sunucu tarafında kullanın.</li>
                    <li>Her entegrasyon için ayrı anahtar oluşturup yetkileri sınırlandırın.</li>
                    <li>IP beyaz liste ve OTP güvenliğini aktif hale getirin.</li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="api-doc-card h-100">
                <ul class="mb-0">
                    <li>Siparişlerinizi <code>external_reference</code> alanı ile eşleyin.</li>
                    <li>Webhook yanıtlarında 200-299 arası kod döndürün.</li>
                    <li>İstek loglarını ve başarısız denemeleri paneldeki <em>API Güvenliği</em> ekranından izleyin.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php Helpers::includeTemplate('public-footer.php');
