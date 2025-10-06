# Reseller Script REST API

Bu sürüm Reseller Script paneline REST mimarisinde çalışan, Swagger dokümantasyonlu ve SDK desteği olan yeni bir API katmanı ekler. API, ePin/lisans mağazanızı WordPress (WooCommerce) dâhil olmak üzere PHP, Node.js, Python gibi dillerle hızlıca entegre etmenizi sağlar.

## 🚀 Özellikler

- JSON tabanlı REST uç noktaları (`/api/v2`)
- API anahtarı, Bearer Token veya HMAC imzalı güvenli istek desteği
- IP beyaz liste, rate limit ve imzalı zaman damgası doğrulamaları
- Swagger UI (`api/docs/index.html`), OpenAPI şeması (`api/openapi.yaml`) ve Postman koleksiyonu
- PHP / Node.js / Python için hafif istemci kütüphaneleri (`integrations/sdk/`)
- WooCommerce eklentisi ile ürün ve sipariş senkronizasyonu (`integrations/wordpress/reseller-api`)

## 📦 Kurulum

1. **Veritabanı**
   - `schema.sql` dosyasındaki `api_tokens` alanlarının yeni sütunlara sahip olduğundan emin olun.
   - Sistem otomatik olarak `src/Migrations/Schema.php` üzerinden şema güncellemesi yapacaktır.

2. **Sunucu yapılandırması**
   - PHP 8.1+, MySQL 5.7+ ve composer bağımlılıklarını kurun.
   - Gerekirse `.env` veya `config/config.php` üzerinde veritabanı bilgilerinizi güncelleyin.

3. **API erişimi**
   - Yönetim panelinden bayi için API anahtarı oluşturun.
   - Yeni anahtarlar otomatik olarak HMAC gizli anahtarı ile gelir. İmza doğrulaması yapmak istemiyorsanız boş bırakabilirsiniz.

## 🔐 İstek Gönderme

```bash
curl -X POST "https://panel.example.com/api/v2/order/create" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
        "product_id": 15,
        "quantity": 1,
        "external_reference": "EXT-1001"
      }'
```

### HMAC İsteği

```text
signature = HMAC_SHA256(secret, timestamp + "\n" + METHOD + "\n" + PATH + "\n" + body)
Headers:
  X-API-Key: YOUR_API_KEY
  X-Request-Timestamp: 1700000000
  X-Signature: sha256=<hesaplanan-imza>
```

## 🧰 SDK Örnekleri

- **PHP:** `integrations/sdk/php/ResellerApiClient.php`
- **Node.js:** `integrations/sdk/node/index.js`
- **Python:** `integrations/sdk/python/client.py`

Her istemci `getProducts`, `createOrder`, `getOrderStatus`, `getBalance`, `getUserInfo` fonksiyonlarını içerir.

## 🧩 WooCommerce Eklentisi

`integrations/wordpress/reseller-api` klasörünü ZIP yaparak yönetim panelinden “Eklentiler → Yeni Ekle” üzerinden yükleyin.

1. Ayarlar → WooCommerce → Reseller API menüsünden **API URL** ve **API Key** değerlerini girin.
2. “WooCommerce Ürünlerini Güncelle” butonuyla paneldeki aktif ürünler WooCommerce’de sanal ürün olarak oluşturulur.
3. WooCommerce siparişleri ödeme sonrası otomatik olarak Reseller API üzerinden oluşturulur ve durum bilgisi sipariş notu olarak eklenir.

## 📚 Dokümantasyon

- Swagger UI: `api/docs/index.html`
- OpenAPI: `api/openapi.yaml`
- Postman: `docs/postman/reseller-api.postman_collection.json`
- Panel içi rehber: `/api-documentation.php`

## 🧪 Test & Rate Limit

- Varsayılan hız limiti dakikada 120 istektir. `api_tokens.rate_limit_per_minute` ile kullanıcı bazlı değer tanımlayabilirsiniz.
- Tüm yanıtlar `meta.request_id` ve `meta.timestamp` alanları ile izlenebilir.

## 🤝 Katkı

Geliştirmeler için yeni uç noktalar eklerken `App\Api` katmanındaki controller ve servis mimarisini takip edin. Swagger şemasını güncellemeyi ve Postman koleksiyonuna yeni istekleri eklemeyi unutmayın.
