<?php
require __DIR__ . '/bootstrap.php';

use App\Helpers;
use App\Lang;
use App\Settings;

Lang::boot();

$pageTitle = 'REST API Dokümantasyonu';
$metaDescription = 'Reseller Script için REST API uç noktaları, kimlik doğrulama, rate limit ve örnek entegrasyonlar.';
$apiBase = rtrim(Helpers::apiBaseUrl(), '/') . '/v2';
$rateLimit = (int) Settings::get('api_rate_limit_per_minute', 120);
if ($rateLimit <= 0) {
    $rateLimit = 120;
}

Helpers::includeTemplate('public-header.php', array(
    'pageTitle' => $pageTitle,
    'metaDescription' => $metaDescription,
));
?>

<div class="page-container api-docs">
    <section class="api-hero">
        <h1>Reseller REST API</h1>
        <p>Bayiler ve entegratörler için ürün listeleme, sipariş oluşturma ve bakiye yönetimi süreçlerini otomatikleştiren JSON tabanlı servis.</p>
        <div class="hero-links">
            <a class="btn" href="api/docs/index.html" target="_blank">Swagger Dokümanı</a>
            <a class="btn" href="api/openapi.yaml" target="_blank">openapi.yaml</a>
            <a class="btn" href="docs/postman/reseller-api.postman_collection.json" target="_blank">Postman Koleksiyonu</a>
        </div>
    </section>

    <section class="api-card">
        <h2>Kimlik Doğrulama</h2>
        <ul>
            <li><strong>X-API-Key</strong> başlığı veya <strong>Authorization: Bearer &lt;token&gt;</strong> kullanılabilir.</li>
            <li>Opsiyonel HMAC kontrolü için <code>X-Request-Timestamp</code> (Unix epoch) ve <code>X-Signature</code> (sha256=&lt;imza&gt;) gönderilir.</li>
            <li>İmza formülü: <code>HMAC_SHA256(secret, timestamp + "\n" + METHOD + "\n" + PATH + "\n" + body)</code></li>
            <li>Varsayılan rate limit: dakikada <strong><?= (int) $rateLimit ?></strong> istek. Anahtar bazlı limit özelleştirilebilir.</li>
        </ul>
    </section>

    <section class="api-card">
        <h2>Temel Uç Noktalar</h2>
        <table class="api-table">
            <thead>
            <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Açıklama</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>GET</td>
                <td><?= Helpers::sanitize($apiBase) ?>/products</td>
                <td>Aktif ürünleri listeler.</td>
            </tr>
            <tr>
                <td>POST</td>
                <td><?= Helpers::sanitize($apiBase) ?>/order/create</td>
                <td>Yeni sipariş oluşturur.</td>
            </tr>
            <tr>
                <td>GET</td>
                <td><?= Helpers::sanitize($apiBase) ?>/order/status</td>
                <td>Sipariş durumunu sorgular.</td>
            </tr>
            <tr>
                <td>GET</td>
                <td><?= Helpers::sanitize($apiBase) ?>/balance</td>
                <td>Kullanıcı bakiyesini getirir.</td>
            </tr>
            <tr>
                <td>GET</td>
                <td><?= Helpers::sanitize($apiBase) ?>/user/info</td>
                <td>Bayi ve token meta verilerini döndürür.</td>
            </tr>
            </tbody>
        </table>
    </section>

    <section class="api-card">
        <h2>İstek Örnekleri</h2>
        <h3>Ürün Listesi (cURL)</h3>
        <pre><code class="language-bash">curl -X GET \
  "<?= Helpers::sanitize($apiBase) ?>/products" \
  -H "X-API-Key: YOUR_API_KEY"</code></pre>

        <h3>Sipariş Oluşturma (PHP)</h3>
        <pre><code class="language-php">$client = new Reseller\Sdk\ResellerApiClient('<?= Helpers::sanitize($apiBase) ?>', 'YOUR_API_KEY');
$order = $client->createOrder([
    'product_id' => 15,
    'quantity' => 1,
    'external_reference' => 'EXT-1001',
]);</code></pre>

        <h3>Sipariş Durumu (Node.js)</h3>
        <pre><code class="language-javascript">import ResellerApiClient from './integrations/sdk/node/index.js';
const client = new ResellerApiClient('<?= Helpers::sanitize($apiBase) ?>', process.env.API_KEY);
const status = await client.getOrderStatus({ order_id: 42 });</code></pre>

        <h3>Bakiye Sorgu (Python)</h3>
        <pre><code class="language-python">from integrations.sdk.python.client import ResellerApiClient
client = ResellerApiClient('<?= Helpers::sanitize($apiBase) ?>', api_key='YOUR_KEY', bearer_token='TOKEN')
balance = client.get_balance()</code></pre>
    </section>

    <section class="api-card">
        <h2>Yanıt Örneği</h2>
        <pre><code class="language-json">{
  "success": true,
  "data": {
    "order_id": 4521,
    "status": "pending",
    "message": "Sipariş başarıyla oluşturuldu.",
    "quantity": 1,
    "total_amount": 89.9,
    "remaining_balance": 310.1
  },
  "meta": {
    "request_id": "c2341f4f7a2f4c1fb0195d54a7823c3f",
    "timestamp": "<?= date('c') ?>"
  }
}</code></pre>
    </section>
</div>

<?php Helpers::includeTemplate('public-footer.php'); ?>
