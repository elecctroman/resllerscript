<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Dokümantasyonu | Partner Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f7fb;
            font-family: "Inter", "Segoe UI", system-ui, sans-serif;
        }
        .hero {
            background: linear-gradient(135deg,#4338ca,#6366f1);
            color: #fff;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 15px 35px rgba(79,70,229,0.2);
        }
        .endpoint-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 12px 28px rgba(15,23,42,0.08);
            overflow: hidden;
        }
        pre {
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 14px;
            padding: 1.25rem;
            font-size: 0.875rem;
        }
        code {
            font-family: "Fira Code", monospace;
        }
        .badge-method {
            min-width: 64px;
        }
        .table thead {
            background: #f8fafc;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="hero mb-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <h1 class="display-5 fw-semibold mb-3">Partner Paneli REST API</h1>
                <p class="lead mb-4">Bayi panelinizi harici sistemlere bağlayarak ürün senkronizasyonu, bakiye sorgusu ve sipariş işlemlerini otomatikleştirin.</p>
                <div class="d-flex flex-wrap gap-3">
                    <div class="badge text-bg-light text-dark px-3 py-2">Temel URL: <code>https://your-domain.com/api/v1</code></div>
                    <div class="badge text-bg-light text-dark px-3 py-2">Kimlik Doğrulama: <code>X-API-Key</code> veya <code>?apikey=</code></div>
                    <div class="badge text-bg-light text-dark px-3 py-2">Yanıt Formatı: <code>application/json</code></div>
                </div>
            </div>
            <div class="col-lg-5 text-lg-end">
                <div class="card border-0 bg-white bg-opacity-10 text-white">
                    <div class="card-body">
                        <h5 class="fw-semibold mb-3">Hızlı Başlangıç</h5>
                        <ol class="small lh-lg mb-0">
                            <li>Profil &gt; API Erişimi bölümünden bir API anahtarı oluşturun.</li>
                            <li>Güvenlik için IP beyaz liste veya domain kısıtlamalarını tanımlayın.</li>
                            <li>Aşağıdaki örnek istek ile bağlantıyı test edin.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="mb-5">
        <h2 class="h4 fw-semibold mb-3">Kimlik Doğrulama</h2>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card endpoint-card">
                    <div class="card-body">
                        <h5 class="fw-semibold">Başlık Kullanımı</h5>
                        <p class="text-muted mb-3">Her istek aşağıdaki başlıklardan biri ile kimlik doğrulaması yapmalıdır.</p>
                        <pre><code>GET /api/v1/products
Host: your-domain.com
X-API-Key: YOUR_API_KEY
Accept: application/json</code></pre>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card endpoint-card">
                    <div class="card-body">
                        <h5 class="fw-semibold">Sorgu Parametresi</h5>
                        <p class="text-muted mb-3">Alternatif olarak, API anahtarı <code>?apikey=</code> parametresi ile gönderilebilir.</p>
                        <pre><code>curl "https://your-domain.com/api/v1/products?apikey=YOUR_API_KEY" \
  -H "Accept: application/json"</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <h2 class="h4 fw-semibold mb-3">Durum Kodları</h2>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Kod</th>
                        <th>Anlam</th>
                        <th>Açıklama</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <tr>
                        <td><span class="badge text-bg-success">200</span></td>
                        <td>Başarılı</td>
                        <td>İstek işlendi ve yanıt JSON gövdesi döndürüldü.</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-warning text-dark">400</span></td>
                        <td>İş Kuralı</td>
                        <td>Zorunlu alan eksik veya bakiye yetersizliği gibi senaryolar.</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-danger">401</span></td>
                        <td>Yetkisiz</td>
                        <td>API anahtarı eksik ya da geçersiz.</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-info text-dark">404</span></td>
                        <td>Bulunamadı</td>
                        <td>İstenen kaynak mevcut değil.</td>
                    </tr>
                    <tr>
                        <td><span class="badge text-bg-dark">500</span></td>
                        <td>Sunucu Hatası</td>
                        <td>Beklenmeyen bir hata oluştu.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-5">
        <h2 class="h4 fw-semibold mb-4">Uç Noktalar</h2>
        <div class="row g-4">
            <div class="col-12">
                <div class="card endpoint-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge text-bg-primary badge-method">GET</span>
                                <code class="ms-2">/api/v1/user</code>
                            </div>
                            <span class="badge text-bg-success">Kimlik doğrulama gerekli</span>
                        </div>
                        <p class="text-muted">Geçerli API anahtarına sahip bayinin temel bilgilerini ve mevcut bakiyesini döndürür.</p>
                        <pre><code>{
  "success": true,
  "data": {
    "credit": "750",
    "nickname": "bayi",
    "email": "user@example.com"
  }
}</code></pre>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card endpoint-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge text-bg-primary badge-method">GET</span>
                                <code class="ms-2">/api/v1/products</code>
                            </div>
                            <span class="badge text-bg-success">Kimlik doğrulama gerekli</span>
                        </div>
                        <p class="text-muted">Aktif ürünleri fiyat, stok ve kategori bilgileri ile listeler.</p>
                        <pre><code>{
  "success": true,
  "data": [
    {
      "id": 20,
      "title": "Office 2021 Pro Plus - Retail",
      "content": "Key olarak teslim edilir...",
      "amount": "50",
      "stock": 11,
      "available": true
    }
  ]
}</code></pre>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card endpoint-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge text-bg-warning badge-method">POST</span>
                                <code class="ms-2">/api/v1/orders</code>
                            </div>
                            <span class="badge text-bg-success">Kimlik doğrulama gerekli</span>
                        </div>
                        <p class="text-muted">Belirlenen üründen tek adet sipariş oluşturur.</p>
                        <pre><code>curl -X POST "https://your-domain.com/api/v1/orders?apikey=YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"product_id": 57, "note": "müşteri notu"}'</code></pre>
                        <pre><code>{
  "success": true,
  "data": {
    "orders": [
      {
        "order_id": 48380,
        "status": "completed",
        "content": "STOK_ICERIK_KEY_XXX"
      }
    ],
    "remaining_balance": 3120.60
  }
}</code></pre>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card endpoint-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge text-bg-primary badge-method">GET</span>
                                <code class="ms-2">/api/v1/orders</code>
                            </div>
                            <span class="badge text-bg-success">Kimlik doğrulama gerekli</span>
                        </div>
                        <p class="text-muted">API üzerinden oluşturulan sipariş geçmişini listeler.</p>
                        <pre><code>{
  "success": true,
  "data": {
    "orders": [
      {
        "id": 48380,
        "product_id": 29,
        "product_title": "Adobe CC - 2 Hafta",
        "amount": 40,
        "status": "completed",
        "content": "STOK_ICERIK_KEY_XXX",
        "created_at": "2025-10-02T00:06:25Z"
      }
    ]
  }
}</code></pre>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card endpoint-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge text-bg-primary badge-method">GET</span>
                                <code class="ms-2">/api/v1/orders/{id}</code>
                            </div>
                            <span class="badge text-bg-success">Kimlik doğrulama gerekli</span>
                        </div>
                        <p class="text-muted">Belirli bir siparişin detaylarını döndürür.</p>
                        <pre><code>{
  "success": true,
  "data": {
    "id": 48380,
    "product_id": 29,
    "product_title": "Adobe CC - 2 Hafta",
    "amount": 40,
    "status": "completed",
    "content": "STOK_ICERIK_KEY_XXX",
    "created_at": "2025-10-02T00:06:25Z"
  }
}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <h2 class="h4 fw-semibold mb-3">Hata Yanıtları</h2>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card endpoint-card">
                    <div class="card-body">
                        <h5 class="fw-semibold">Yetkisiz Erişim</h5>
                        <pre><code>{
  "success": false,
  "status": "failed",
  "message": "Yetkisiz erişim."
}</code></pre>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card endpoint-card">
                    <div class="card-body">
                        <h5 class="fw-semibold">Ortak Hata Yapısı</h5>
                        <pre><code>{
  "success": false,
  "status": "failed",
  "message": "İşlem hatalı.",
  "timestamp": "2025-10-06T10:00:00Z"
}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <h2 class="h4 fw-semibold mb-3">SDK ve Örnek Kodlar</h2>
        <p class="text-muted">SDK dizininde (integrations/sdk) PHP, Node.js ve Python için örnek istemci sınıfları bulunur. Postman koleksiyonunu indirmek için <code>/integrations/postman/collection.json</code> dosyasını kullanın.</p>
    </section>
</div>
</body>
</html>
