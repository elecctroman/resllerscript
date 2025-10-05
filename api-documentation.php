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
<div class="api-doc-page position-relative">
    <div class="api-doc-glow"></div>
    <div class="container py-5 position-relative">
        <div class="row g-4 g-xl-5">
            <div class="col-xl-3">
                <aside class="api-doc-nav">
                    <div class="api-doc-nav-header">
                        <span class="api-doc-nav-title">İçindekiler</span>
                        <span class="api-doc-nav-sub">Tüm uç noktalar tek bakışta</span>
                    </div>
                    <nav>
                        <ul class="api-doc-nav-list">
                            <li><a href="#quickstart"><i class="bi bi-rocket-takeoff"></i> Hızlı Başlangıç</a></li>
                            <li><a href="#versioning"><i class="bi bi-collection"></i> Versiyonlama &amp; Yetkiler</a></li>
                            <li><a href="#auth"><i class="bi bi-shield-check"></i> Kimlik Doğrulama</a></li>
                            <li><a href="#errors"><i class="bi bi-bug"></i> Yanıt Şeması</a></li>
                            <li><a href="#products"><i class="bi bi-box-seam"></i> Ürün Servisleri</a></li>
                            <li><a href="#orders"><i class="bi bi-receipt"></i> Sipariş Servisleri</a></li>
                            <li><a href="#webhooks"><i class="bi bi-broadcast"></i> Webhooklar</a></li>
                            <li><a href="#providers"><i class="bi bi-diagram-3"></i> Sağlayıcı Köprüleri</a></li>
                            <li><a href="#sdk"><i class="bi bi-braces"></i> Kod Örnekleri</a></li>
                            <li><a href="#best-practices"><i class="bi bi-stars"></i> En İyi Uygulamalar</a></li>
                        </ul>
                    </nav>
                    <div class="api-doc-nav-footer">
                        <p class="small mb-2">Yardıma mı ihtiyacınız var?</p>
                        <a class="btn btn-outline-light btn-sm w-100" href="<?= Helpers::sanitize(Helpers::url('support.php')) ?>">
                            <i class="bi bi-life-preserver me-1"></i> Destek Talebi Aç
                        </a>
                    </div>
                </aside>
            </div>
            <div class="col-xl-9">
                <div class="api-doc-content">
                    <section class="api-doc-hero public-hero text-center text-xl-start mb-5">
                        <div class="row align-items-center g-4">
                            <div class="col-xl-7">
                                <h1 class="display-4 fw-semibold mb-3">
                                    REST API Dökümantasyonu
                                </h1>
                                <p class="lead mb-4 text-muted">
                                    Bayi panelinizi dış sistemlere bağlayın, ürün ve sipariş süreçlerini otomatikleştirin, NetGSM / TürkPin / Pinabi sağlayıcı köprüleriyle stoklarınızı tek yerden yönetin.
                                </p>
                                <div class="d-flex flex-column flex-md-row align-items-center gap-3">
                                    <a class="btn btn-gradient-primary btn-lg px-4" href="<?= Helpers::sanitize(Helpers::url('profile.php#api-access')) ?>">
                                        <i class="bi bi-key me-2"></i> API Anahtarı Oluştur
                                    </a>
                                    <a class="btn btn-outline-light btn-lg px-4" href="<?= Helpers::sanitize(Helpers::url('profile.php#api-settings')) ?>">
                                        <i class="bi bi-gear me-2"></i> Güvenlik Ayarları
                                    </a>
                                </div>
                            </div>
                            <div class="col-xl-5">
                                <div class="api-doc-hero-highlight">
                                    <div class="api-doc-hero-metric">
                                        <span class="label">Temel URL</span>
                                        <span class="value"><code><?= Helpers::sanitize($baseUrl) ?></code></span>
                                    </div>
                                    <div class="api-doc-hero-metric">
                                        <span class="label">Kimlik Doğrulama</span>
                                        <span class="value"><code>Authorization: Bearer &lt;API_TOKEN&gt;</code></span>
                                    </div>
                                    <div class="api-doc-hero-metric">
                                        <span class="label">Rate Limit</span>
                                        <span class="value">Dakikada <?= (int)$rateLimit ?> istek</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="api-doc-section mb-5" id="quickstart">
                        <div class="section-heading">
                            <span class="section-kicker">Başlangıç Rehberi</span>
                            <h2 class="h2 mb-2">Dakikalar İçinde Entegrasyon</h2>
                            <p class="text-muted mb-0">İlk isteğinizi göndermek için ihtiyacınız olan her şey aşağıda listelendi.</p>
                        </div>
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="api-doc-card h-100">
                                    <h3 class="h5 mb-3">4 Adımda Kurulum</h3>
                                    <ol class="mb-0 api-doc-steps">
                                        <li>Bayi panelinizden <strong>Profil &gt; API Erişimi</strong> alanına gidin.</li>
                                        <li>Yeni bir API anahtarı oluşturun veya mevcut anahtarınızı kopyalayın.</li>
                                        <li>IP beyaz liste, OTP ve webhook adresi gibi ek güvenlik ayarlarını yapılandırın.</li>
                                        <li>Aşağıdaki cURL örneğini kullanarak bağlantınızı test edin.</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="api-doc-card h-100">
                                    <div class="api-code-header">
                                        <span class="api-method get">GET</span>
                                        <span class="api-endpoint-path">/products</span>
                                    </div>
<pre class="api-doc-code"><code>curl -X GET "<?= Helpers::sanitize($baseUrl) ?>/products" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"</code></pre>
                                    <p class="small text-muted mb-0">Alternatif olarak <code>X-API-Key</code> başlığını veya <code>api_key</code> sorgu parametresini kullanabilirsiniz.</p>
                                </div>
                            </div>
                        </div>
                        <div class="api-doc-callout mt-4">
                            <i class="bi bi-lightning-charge"></i>
                            <div>
                                <strong>Anında erişim:</strong> API anahtarları oluşturulduğu anda aktif olur ve IP sınırı koymadıysanız global olarak kullanılabilir.
                            </div>
                        </div>
                    </section>

                    <section class="api-doc-section mb-5" id="versioning">
                        <div class="section-heading">
                            <span class="section-kicker">API Yaşam Döngüsü</span>
                            <h2 class="h2 mb-2">Versiyonlama &amp; Yetki Kapsamları</h2>
                            <p class="text-muted mb-0">V1 sürümü tüm reseller özelliklerini kapsar. Gelecekteki sürümlerde geriye dönük uyumluluk için semantik versiyonlama uygulanır.</p>
                        </div>
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="api-doc-card h-100">
                                    <h3 class="h5 mb-3">Sürüm Politikası</h3>
                                    <ul class="mb-0">
                                        <li>Tüm uç noktalar <code><?= Helpers::sanitize($baseUrl) ?></code> altında yayınlanır.</li>
                                        <li>V1 sürümü major değişiklikler olmadan korunur; kırıcı değişiklikler yeni bir sürüme taşınır.</li>
                                        <li>Önizleme uç noktaları <code>/beta</code> önekiyle duyurulur ve ayrı dokümantasyonda etiketlenir.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="api-doc-card h-100">
                                    <h3 class="h5 mb-3">Scope Bazlı Yetkiler</h3>
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Scope</th>
                                                <th>Açıklama</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>read</code></td>
                                                <td>Ürün ve sipariş sorguları, bakiye görüntüleme.</td>
                                            </tr>
                                            <tr>
                                                <td><code>orders</code></td>
                                                <td>Yeni sipariş oluşturma, stok rezervasyonu.</td>
                                            </tr>
                                            <tr>
                                                <td><code>manage</code></td>
                                                <td>Webhook URL güncelleme, token yönetimi.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <p class="small text-muted mb-0 mt-3">API anahtarlarını <strong>Profil &gt; API Erişimi</strong> sayfasından scope bazlı olarak düzenleyebilirsiniz.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="api-doc-section mb-5" id="auth">
                        <div class="section-heading">
                            <span class="section-kicker">Güvenlik Katmanları</span>
                            <h2 class="h2 mb-2">Kimlik Doğrulama &amp; Yetkilendirme</h2>
                            <p class="text-muted mb-0">Her istekte kimlik doğrulaması zorunludur; aşağıdaki yöntemlerle güvenliği maksimuma taşıyın.</p>
                        </div>
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
                        <div class="section-heading">
                            <span class="section-kicker">Yanıt Formatı</span>
                            <h2 class="h2 mb-2">Yanıt Şeması &amp; Hata Yönetimi</h2>
                            <p class="text-muted mb-0">Tüm uç noktalar JSON yanıt döndürür. Başarısız isteklerde statü kodu ve açıklayıcı mesaj gelir. Başarılı sonuçlarda <code>data</code> alanı ilgili kaydı içerir.</p>
                        </div>
                        <div class="api-doc-card mb-4">
                            <h3 class="h5 mb-3">Başarılı Yanıt Şeması</h3>
<pre class="api-doc-code"><code>{
  "success": true,
  "data": {...},
  "meta": {
    "request_id": "d1b8c6ec-7f9d-4f6d-9ad0-6c02ed3fb0c4",
    "timestamp": "2025-10-05T12:32:11Z"
  }
}</code></pre>
                            <p class="small text-muted mb-0">Tüm yanıtlar <code>meta</code> alanında benzersiz <em>request_id</em> ve ISO8601 zaman damgası içerir. Hata yanıtlarında <code>meta</code> alanı isteğe bağlıdır.</p>
                        </div>
                        <div class="api-doc-card">
                            <div class="table-responsive">
                                <table class="table table-dark table-striped align-middle mb-0 api-doc-table">
                                    <thead>
                                        <tr>
                                            <th>HTTP Kod</th>
                                            <th>Başlık</th>
                                            <th>Açıklama</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><code>200</code></td>
                                            <td>OK</td>
                                            <td>İstek başarıyla tamamlandı.</td>
                                        </tr>
                                        <tr>
                                            <td><code>201</code></td>
                                            <td>Created</td>
                                            <td>Yeni sipariş oluşturuldu.</td>
                                        </tr>
                                        <tr>
                                            <td><code>401</code></td>
                                            <td>Unauthorized</td>
                                            <td>API anahtarı eksik veya doğrulanamadı.</td>
                                        </tr>
                                        <tr>
                                            <td><code>403</code></td>
                                            <td>Forbidden</td>
                                            <td>Token gerekli scope yetkisine sahip değil.</td>
                                        </tr>
                                        <tr>
                                            <td><code>422</code></td>
                                            <td>Unprocessable Entity</td>
                                            <td>Validasyon hatası veya stok/bakiye yetersizliği.</td>
                                        </tr>
                                        <tr>
                                            <td><code>429</code></td>
                                            <td>Too Many Requests</td>
                                            <td>Rate limit aşıldı. <code>Retry-After</code> başlığı gönderilir.</td>
                                        </tr>
                                        <tr>
                                            <td><code>500</code></td>
                                            <td>Internal Server Error</td>
                                            <td>Beklenmeyen sunucu hatası.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section class="api-doc-section mb-5" id="products">
                        <div class="section-heading">
                            <span class="section-kicker">Katalog Yönetimi</span>
                            <h2 class="h2 mb-2">Ürün Servisleri</h2>
                            <p class="text-muted mb-0">Aktif ürünlerinizi kategori bazlı olarak çekebilir, sağlayıcı kodlarını ve stok tiplerini izleyebilirsiniz.</p>
                        </div>
                        <div class="api-doc-card mb-4">
                            <div class="api-code-header">
                                <span class="api-method get">GET</span>
                                <span class="api-endpoint-path">/products</span>
                            </div>
                            <p class="mb-3">Aktif ürünleri ve kategori ağacını döndürür. Yanıtta bayi bakiyesi, kategori ilişkileri ve sağlayıcı kodları yer alır.</p>
<pre class="api-doc-code"><code>GET <?= Helpers::sanitize($baseUrl) ?>/products
</code></pre>
                            <div class="api-doc-card-subtitle">Örnek yanıt</div>
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
        "description": null,
        "price": 89.90,
        "category_id": 1,
        "category_name": "Oyun",
        "provider_code": "turkpin"
      }
    ]
  }
}</code></pre>
                            <div class="table-responsive mt-3">
                                <table class="table table-sm align-middle api-doc-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Parametre</th>
                                            <th>Tür</th>
                                            <th>Açıklama</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><code>since</code></td>
                                            <td>datetime</td>
                                            <td>Belirtilen tarihten sonra güncellenen ürünleri getirir.</td>
                                        </tr>
                                        <tr>
                                            <td><code>provider_code</code></td>
                                            <td>string</td>
                                            <td>Yalnızca belirli sağlayıcıya ait ürünleri döndürür.</td>
                                        </tr>
                                        <tr>
                                            <td><code>include_inactive</code></td>
                                            <td>bool</td>
                                            <td>Varsayılan <code>false</code>. <code>true</code> gönderilirse pasif ürünler de listelenir.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="api-doc-card-gradient">
                            <div class="api-doc-card-gradient-inner">
                                <i class="bi bi-lightbulb"></i>
                                <div>
                                    <strong>İpucu:</strong> Ürün listenizi önbelleğe alın ve <code>since</code> parametresi ile sadece değişen kayıtları sorgulayın.
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="api-doc-section mb-5" id="orders">
                        <div class="section-heading">
                            <span class="section-kicker">Sipariş Akışı</span>
                            <h2 class="h2 mb-2">Sipariş Servisleri</h2>
                            <p class="text-muted mb-0">Sipariş geçmişinizi sorgulayın, yeni siparişler oluşturun ve webhook adresinizi güncelleyin.</p>
                        </div>
                        <div class="api-doc-card mb-4">
                            <div class="api-code-header">
                                <span class="api-method get">GET</span>
                                <span class="api-endpoint-path">/orders</span>
                            </div>
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
                                        <tr>
                                            <td><code>page</code></td>
                                            <td>int</td>
                                            <td>Sayfalama için başlangıç değeri 1.</td>
                                        </tr>
                                        <tr>
                                            <td><code>per_page</code></td>
                                            <td>int</td>
                                            <td>Varsayılan 50, maksimum 200.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="api-doc-card mb-4">
                            <div class="api-code-header">
                                <span class="api-method post">POST</span>
                                <span class="api-endpoint-path">/orders</span>
                            </div>
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
  ],
  "webhook_override": "https://entegrasyon.ornek.com/webhooks/notify"
}</code></pre>
                            <div class="api-doc-card-subtitle">Başarılı yanıt</div>
<pre class="api-doc-code"><code>{
  "success": true,
  "data": {
    "orders": [4512],
    "remaining_balance": 3120.60
  }
}</code></pre>
                            <div class="api-doc-card-subtitle">Validasyon notları</div>
                            <ul class="small text-muted mb-3">
                                <li>Her <code>sku</code> aktif katalogda bulunmalıdır.</li>
                                <li>Stok gerektiren ürünlerde miktar, <code>product_stock_items</code> tablosundaki mevcut adet ile karşılaştırılır.</li>
                                <li><code>webhook_override</code> alanı isteğe bağlıdır, yalnızca ilgili sipariş için geçerli olur.</li>
                            </ul>
                            <p class="small text-muted mb-0">Bakiye yetersizliğinde veya stok bulunamadığında HTTP <code>422</code> döner ve işlem geri alınır.</p>
                        </div>
                        <div class="api-doc-card">
                            <div class="api-code-header">
                                <span class="api-method post">POST</span>
                                <span class="api-endpoint-path">/token-webhook</span>
                            </div>
                            <p>API anahtarınıza ait webhook adresini günceller. <code>manage</code> yetkisi gereklidir.</p>
<pre class="api-doc-code"><code>POST <?= Helpers::sanitize($baseUrl) ?>/token-webhook
Content-Type: application/json

{
  "webhook_url": "https://entegrasyon.ornek.com/api/webhooks/reseller"
}</code></pre>
                            <p class="small text-muted mb-0">Bu uç noktaya erişmek için tokenınızda <code>manage</code> scope'u bulunmalıdır.</p>
                        </div>
                    </section>

                    <section class="api-doc-section mb-5" id="providers">
                        <div class="section-heading">
                            <span class="section-kicker">Tedarikçi Köprüleri</span>
                            <h2 class="h2 mb-2">NetGSM, TürkPin ve Pinabi ile Anında Entegrasyon</h2>
                            <p class="text-muted mb-0">Yönetim panelinizdeki <strong>Sağlayıcılar</strong> menüsü, sadece API adresi ve anahtar girerek üç büyük toptan sağlayıcıya bağlanmanızı sağlar. Sistem, sürücü ayarlarını otomatik doldurur ve ürün kataloğunu içe aktarmanız için senkronizasyon araçları sunar.</p>
                        </div>
                        <div class="api-doc-card mb-4">
                            <h3 class="h5 mb-3">Varsayılan uç noktalar</h3>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Sağlayıcı</th>
                                            <th>API URL Örneği</th>
                                            <th>Ürün Uç Noktası</th>
                                            <th>Sipariş Uç Noktası</th>
                                            <th>Durum Kontrolü</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>NetGSM</strong></td>
                                            <td><code>https://api.netgsm.com.tr/</code></td>
                                            <td><code>/api/v2/catalog/products</code></td>
                                            <td><code>/api/v2/orders</code></td>
                                            <td><code>/api/v2/account</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>TürkPin</strong></td>
                                            <td><code>https://panel.turkpin.net/</code></td>
                                            <td><code>/api/products</code></td>
                                            <td><code>/api/order</code></td>
                                            <td><code>/api/profile</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Pinabi</strong></td>
                                            <td><code>https://api.pinabi.com/</code></td>
                                            <td><code>/api/v1/products</code></td>
                                            <td><code>/api/v1/orders</code></td>
                                            <td><code>/api/v1/user</code></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="small text-muted mb-0">Gerek duyarsanız gelişmiş ayarlar bölümünden uç noktaları özelleştirebilir veya ek başlıklar tanımlayabilirsiniz.</p>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="api-doc-card h-100">
                                    <h4 class="h6 mb-2"><i class="bi bi-magic me-2"></i> Tek Adım Kurulum</h4>
                                    <p class="mb-0">Panel, girilen API adresinden sağlayıcı adını ve kodunu otomatik üretir. Üç sürücü de yalnızca URL ve anahtar ile saniyeler içinde etkinleşir.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="api-doc-card h-100">
                                    <h4 class="h6 mb-2"><i class="bi bi-heart-pulse me-2"></i> Bağlantıyı Test Et</h4>
                                    <p class="mb-0">“Bağlantıyı Test Et” butonu, API anahtarınızı doğrular, yanıt mesajını kaydeder ve sağlayıcı listesinde rozet olarak gösterir. Başarısız denemelerde hata ayrıntıları loglanır.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="api-doc-card h-100">
                                    <h4 class="h6 mb-2"><i class="bi bi-arrow-repeat me-2"></i> Kataloğu Senkronize Et</h4>
                                    <p class="mb-0">Ürünler tek tıkla çekilir, stok ve fiyat bilgileri saklanır. Seçtiğiniz kayıtları kendi kategorilerinize aktarırken ek marj uygulayabilirsiniz.</p>
                                </div>
                            </div>
                        </div>
                        <div class="api-doc-card-gradient mt-4">
                            <div class="api-doc-card-gradient-inner">
                                <i class="bi bi-list-check"></i>
                                <div>
                                    <strong>İş Akışı:</strong>
                                    <ol class="mb-0 small ps-3">
                                        <li><strong>Yönetici &gt; Sağlayıcılar</strong> menüsünden yeni sağlayıcı ekleyin ve bağlantıyı test edin.</li>
                                        <li>Senkronizasyonu çalıştırarak sağlayıcı kataloğunu güncelleyin.</li>
                                        <li>Seçili ürünleri yerel kataloğunuza aktarırken kategori ve fiyat politikalarını belirleyin.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="api-doc-card mt-4">
                            <h3 class="h5 mb-3">Örnek Sağlayıcı Yanıt Haritaları</h3>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Sürücü</th>
                                            <th>Fiyat Alanı</th>
                                            <th>Stok Alanı</th>
                                            <th>Durum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>NetGSM</td>
                                            <td><code>price_with_tax</code></td>
                                            <td><code>available_quantity</code></td>
                                            <td><code>status === 'ACTIVE'</code></td>
                                        </tr>
                                        <tr>
                                            <td>TürkPin</td>
                                            <td><code>reseller_price</code></td>
                                            <td><code>stock</code></td>
                                            <td><code>is_active === 1</code></td>
                                        </tr>
                                        <tr>
                                            <td>Pinabi</td>
                                            <td><code>price</code></td>
                                            <td><code>inventory</code></td>
                                            <td><code>status === 'enabled'</code></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="small text-muted mb-0 mt-3">Senkronizasyon sırasında gelen alanlar otomatik olarak normalize edilir ve ürün kartındaki <code>provider_code</code> alanına işlenir.</p>
                        </div>
                    </section>

                    <section class="api-doc-section mb-5" id="sdk">
                        <div class="section-heading">
                            <span class="section-kicker">Kod Örnekleri</span>
                            <h2 class="h2 mb-2">cURL, PHP, Node.js ve Python ile Entegrasyon</h2>
                            <p class="text-muted mb-0">Aşağıdaki örnekler her uç noktaya nasıl bağlanacağınızı gösterir. Tüm örnekler Authorization başlığında Bearer token kullanır.</p>
                        </div>
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="api-doc-card h-100">
                                    <h3 class="h5 mb-3">PHP (Guzzle)</h3>
<pre class="api-doc-code"><code>$client = new \GuzzleHttp\Client([
    'base_uri' => '<?= Helpers::sanitize($baseUrl) ?>/',
    'headers' => [
        'Authorization' => 'Bearer ' . $apiToken,
        'Accept' => 'application/json',
    ],
]);

$response = $client->get('products');
$products = json_decode($response->getBody()->getContents(), true);
</code></pre>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="api-doc-card h-100">
                                    <h3 class="h5 mb-3">Node.js (Axios)</h3>
<pre class="api-doc-code"><code>import axios from 'axios';

const api = axios.create({
  baseURL: '<?= Helpers::sanitize($baseUrl) ?>/',
  headers: {
    Authorization: `Bearer ${process.env.API_TOKEN}`,
  },
});

const order = await api.post('orders', {
  order_id: 'EXT-2024001',
  items: [{ sku: 'STM-100', quantity: 1 }],
});
</code></pre>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="api-doc-card h-100">
                                    <h3 class="h5 mb-3">Python (Requests)</h3>
<pre class="api-doc-code"><code>import requests

BASE_URL = '<?= Helpers::sanitize($baseUrl) ?>'
TOKEN = 'YOUR_API_TOKEN'

resp = requests.get(
    f"{BASE_URL}/orders",
    headers={'Authorization': f'Bearer {TOKEN}'},
    params={'status': 'processing'}
)
resp.raise_for_status()
print(resp.json())
</code></pre>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="api-doc-card h-100">
                                    <h3 class="h5 mb-3">Webhook Karşılama (PHP)</h3>
<pre class="api-doc-code"><code>$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

if (!hash_equals('Bearer ' . $expectedToken, $_SERVER['HTTP_AUTHORIZATION'] ?? '')) {
    http_response_code(401);
    exit('invalid token');
}

http_response_code(204);
</code></pre>
                                    <p class="small text-muted mb-0">Webhooklar aynı API anahtarınızı Bearer token olarak gönderir. İsteğe bağlı olarak IP kısıtlaması da uygulayabilirsiniz.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="api-doc-section mb-5" id="webhooks">
                        <div class="section-heading">
                            <span class="section-kicker">Gerçek Zamanlı Güncellemeler</span>
                            <h2 class="h2 mb-2">Webhook Bildirimleri</h2>
                            <p class="text-muted mb-0">Sipariş durum değişikliklerini saniyeler içinde kendi sistemlerinize aktarın.</p>
                        </div>
                        <div class="api-doc-card">
                            <p>Webhook adresi tanımladığınızda sipariş durum değişiklikleri anlık olarak iletilir.</p>
                            <div class="api-doc-card-subtitle">Gönderim örneği</div>
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
  },
  "meta": {
    "provider": "turkpin",
    "webhook_attempt": 1,
    "sent_at": "2025-10-05T12:31:22Z"
  }
}</code></pre>
                            <p class="small text-muted mb-0">İstekler <code>Authorization: Bearer &lt;API_TOKEN&gt;</code> başlığı ile gönderilir. 2xx dışındaki yanıtlar yeniden deneme kuyruğuna eklenir.</p>
                        </div>
                    </section>

                    <section class="api-doc-section mb-5" id="best-practices">
                        <div class="section-heading">
                            <span class="section-kicker">Operasyonel Öneriler</span>
                            <h2 class="h2 mb-2">En İyi Uygulamalar</h2>
                            <p class="text-muted mb-0">Uzun vadede stabil ve güvenli entegrasyonlar için önerilen uygulamalar.</p>
                        </div>
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
                </div>
            </div>
        </div>
    </div>
</div>

<?php Helpers::includeTemplate('public-footer.php');
?>
