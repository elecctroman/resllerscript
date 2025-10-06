# Reseller Platform

Bu depo, ePin, lisans ve dijital hesap satışları için geliştirilen bayi otomasyon yazılımını içerir. Sisteme entegre edilen REST API sayesinde bayiler ve entegratörler ürünleri listeleyebilir, sipariş oluşturabilir ve bakiyelerini gerçek zamanlı olarak takip edebilir.

## API Özeti

* Temel URL: `/api/v1`
* Kimlik doğrulama: `Authorization: Bearer <TOKEN>` veya `X-API-KEY` + `X-API-SECRET`
* Desteklenen uç noktalar:
  * `POST /auth/login`
  * `GET /products`
  * `POST /order/create`
  * `GET /order/status`
  * `GET /balance`
  * `GET /user/info`

Swagger/OpenAPI şeması ve HTML dokümantasyonuna `docs/api` klasöründen ulaşabilirsiniz.

## SDK ve Postman

Örnek istemci kodları `integrations/sdk` klasöründe yer alır (PHP, Node.js, Python). Postman koleksiyonu `docs/api/postman_collection.json` dosyasında bulunmaktadır.

## WordPress Eklentisi

WooCommerce ile uyumlu WordPress eklentisi `integrations/wp-reseller-api` dizinindedir. Eklentide yalnızca API URL ve anahtar alanlarını doldurmanız yeterlidir.

## Kurulum

1. `composer install`
2. `cp config/config.sample.php config/config.php` ve veritabanı bilgilerini güncelleyin.
3. `php -S localhost:8000` ile yerel ortamda test edin.

## API İstek Örneği

```bash
curl -X GET "https://example.com/api/v1/products" \
  -H "X-API-KEY: YOUR_KEY" \
  -H "X-API-SECRET: YOUR_SECRET" \
  -H "X-CLIENT-DOMAIN: yoursite.com"
```
