<?php declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Helpers;

$apiBase = Helpers::apiBaseUrl(true);
$docsTitle = 'API Dokümantasyonu | Partner Paneli';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Helpers::sanitize($docsTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #0f172a;
            color: #f8fafc;
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        .doc-hero {
            background: radial-gradient(circle at top left, rgba(59,130,246,.25), transparent),
                        radial-gradient(circle at bottom right, rgba(236,72,153,.25), transparent);
            border-radius: 1.5rem;
            padding: 3rem;
            margin-bottom: 3rem;
        }
        .doc-card {
            background: rgba(15, 23, 42, 0.75);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(6px);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.3);
        }
        .code-block {
            background: rgba(15, 23, 42, 0.9);
            border-radius: .75rem;
            padding: 1.25rem;
            font-family: "Fira Code", "Courier New", monospace;
            font-size: .9rem;
            color: #bae6fd;
            overflow-x: auto;
        }
        .endpoint-badge {
            display: inline-block;
            border-radius: 999px;
            padding: .35rem 1rem;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .endpoint-get {
            background: rgba(34, 197, 94, .15);
            color: #4ade80;
        }
        .endpoint-post {
            background: rgba(59, 130, 246, .15);
            color: #93c5fd;
        }
        .text-muted {
            color: rgba(226, 232, 240, .75) !important;
        }
        a {
            color: #60a5fa;
        }
        a:hover {
            color: #bfdbfe;
        }
        footer {
            color: rgba(148, 163, 184, .75);
        }
    </style>
</head>
<body class="py-5">
<div class="container-lg">
    <div class="doc-hero text-center">
        <h1 class="display-5 fw-bold mb-3">REST API Dokümantasyonu</h1>
        <p class="lead text-muted mb-4">Panelinizi harici uygulamalarınıza bağlayın, ürün ve sipariş süreçlerinizi otomatikleştirin.</p>
        <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
            <div class="doc-card py-3 px-4 mb-0">
                <span class="text-uppercase fw-semibold text-muted">Temel URL</span>
                <div class="fs-6 fw-semibold text-white text-break"><code><?= Helpers::sanitize($apiBase) ?></code></div>
            </div>
            <div class="doc-card py-3 px-4 mb-0">
                <span class="text-uppercase fw-semibold text-muted">Kimlik Doğrulama</span>
                <div class="fs-6 fw-semibold text-white">X-API-Key / apikey</div>
            </div>
        </div>
    </div>

    <div class="doc-card">
        <h2 class="h4 mb-3">Kimlik Doğrulama</h2>
        <p class="text-muted mb-3">Tüm uç noktalar istekte geçerli bir <code>apikey</code> bulunmasını bekler. Anahtarınızı istek başlığı ya da sorgu parametresi olarak ekleyebilirsiniz.</p>
        <ul class="text-muted">
            <li><strong>Header:</strong> <code>X-API-Key: YOUR_API_KEY</code></li>
            <li><strong>Query:</strong> <code>?apikey=YOUR_API_KEY</code></li>
            <li><strong>Authorization:</strong> <code>Authorization: Bearer YOUR_API_KEY</code></li>
        </ul>
        <div class="code-block mt-3">
<pre><code>HTTP/1.1 401 Unauthorized
{
  "success": false,
  "status": "failed",
  "message": "Yetkisiz erişim.",
  "error_code": "UNAUTHORIZED"
}</code></pre>
        </div>
    </div>

    <div class="doc-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">GET /api/user</h2>
            <span class="endpoint-badge endpoint-get">GET</span>
        </div>
        <p class="text-muted">Kimlik doğrulanan bayinin temel bilgilerini ve güncel bakiyesini döndürür.</p>
        <div class="code-block mb-3">
<pre><code>curl -X GET "<?= Helpers::sanitize($apiBase) ?>/user" \
  -H "X-API-Key: YOUR_API_KEY"</code></pre>
        </div>
        <div class="code-block">
<pre><code>{
  "success": true,
  "data": {
    "id": 42,
    "name": "Demo Bayi",
    "email": "demo@example.com",
    "credit": 1250.50,
    "locale": "tr",
    "currency": "TRY"
  },
  "message": "Kullanıcı bilgileri getirildi."
}</code></pre>
        </div>
    </div>

    <div class="doc-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">GET /api/products</h2>
            <span class="endpoint-badge endpoint-get">GET</span>
        </div>
        <p class="text-muted">Aktif ürünleri, stok durumlarını ve otomatik teslimat bilgilerini listeler.</p>
        <div class="code-block mb-3">
<pre><code>curl -X GET "<?= Helpers::sanitize($apiBase) ?>/products" \
  -H "X-API-Key: YOUR_API_KEY"</code></pre>
        </div>
        <div class="code-block">
<pre><code>{
  "success": true,
  "data": [
    {
      "id": 15,
      "title": "Steam Cüzdan 100 TL",
      "description": "Kullan-at kod teslimatı",
      "amount": 89.90,
      "stock": 12,
      "available": true,
      "automatic_delivery": true,
      "category": "Oyun"
    }
  ],
  "message": "Ürün listesi başarıyla getirildi."
}</code></pre>
        </div>
    </div>

    <div class="doc-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">POST /api/orders</h2>
            <span class="endpoint-badge endpoint-post">POST</span>
        </div>
        <p class="text-muted">Belirtilen üründen bir adet sipariş oluşturur. İstek gövdesi JSON formatında gönderilmelidir.</p>
        <div class="code-block mb-3">
<pre><code>curl -X POST "<?= Helpers::sanitize($apiBase) ?>/orders" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{
    "product_id": 15,
    "note": "Müşteri referansı"
  }'</code></pre>
        </div>
        <div class="code-block">
<pre><code>{
  "success": true,
  "data": {
    "order_id": 4812,
    "status": "processing"
  },
  "message": "Siparişiniz otomatik teslimat için kuyruğa alındı. Sipariş durumunu kısa süre içinde siparişlerim ekranından takip edebilirsiniz."
}</code></pre>
        </div>
    </div>

    <div class="doc-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">GET /api/orders</h2>
            <span class="endpoint-badge endpoint-get">GET</span>
        </div>
        <p class="text-muted">Bayiye ait tüm siparişlerin listesini döndürür.</p>
        <div class="code-block">
<pre><code>{
  "success": true,
  "data": [
    {
      "id": 4812,
      "product_id": 15,
      "product_title": "Steam Cüzdan 100 TL",
      "status": "processing",
      "note": "Müşteri referansı",
      "price": 89.90,
      "total_amount": 89.90,
      "created_at": "2025-10-07 11:22:13",
      "updated_at": null
    }
  ],
  "message": "Sipariş geçmişi listelendi."
}</code></pre>
        </div>
    </div>

    <div class="doc-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">GET /api/orders/{id}</h2>
            <span class="endpoint-badge endpoint-get">GET</span>
        </div>
        <p class="text-muted">Tek bir siparişin detayını getirir ve teslim edilen içerik varsa yanıt gövdesine ekler.</p>
        <div class="code-block">
<pre><code>{
  "success": true,
  "data": {
    "id": 4812,
    "product_id": 15,
    "product_title": "Steam Cüzdan 100 TL",
    "status": "completed",
    "note": "Müşteri referansı",
    "price": 89.90,
    "total_amount": 89.90,
    "created_at": "2025-10-07 11:22:13",
    "updated_at": "2025-10-07 11:25:01",
    "content": "XXXX-XXXX-XXXX-XXXX"
  },
  "message": "Sipariş detayı getirildi."
}</code></pre>
        </div>
    </div>

    <div class="doc-card">
        <h2 class="h5 mb-3">Hata Yanıtları</h2>
        <p class="text-muted">API her durumda JSON döndürür ve başarısız taleplerde ayrıntılı bir hata kodu içerir.</p>
        <ul class="text-muted">
            <li><strong>400</strong> – <code>İşlem hatalı.</code> (Geçersiz veri veya doğrulama hatası)</li>
            <li><strong>401</strong> – <code>Yetkisiz erişim.</code> (Eksik veya hatalı API anahtarı)</li>
            <li><strong>404</strong> – <code>Bulunamadı.</code> (Kaynak mevcut değil)</li>
            <li><strong>500</strong> – <code>Sunucu hatası.</code> (Beklenmeyen durum)</li>
        </ul>
    </div>

    <footer class="text-center mt-5">
        <p class="mb-0">&copy; <?= date('Y') ?> <?= Helpers::sanitize(Helpers::siteName()) ?> · <a href="<?= Helpers::sanitize(Helpers::url('', true)) ?>">Panele geri dön</a></p>
    </footer>
</div>
</body>
</html>
