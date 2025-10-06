# Reseller Platform

Bu depo, ePin, lisans ve dijital hesap satışları için geliştirilen bayi otomasyon yazılımını içerir. Sisteme entegre edilen REST API sayesinde bayiler ve entegratörler ürünleri listeleyebilir, sipariş oluşturabilir ve bakiyelerini gerçek zamanlı olarak takip edebilir.

## API Özeti (v1.1.0)

* Temel URL: `/api/v1`
* Kimlik doğrulama: `Authorization: Bearer <TOKEN>` veya `X-API-KEY` + `X-API-SECRET`
* Desteklenen uç noktalar:
  * `POST /auth/login`
  * `GET /products`
  * `POST /order/create`
  * `GET /order/status`
  * `GET /balance`
  * `GET /user/info`
  * `POST /api-keys/create`
  * `GET /api-keys/list`
  * `POST /api-keys/revoke`

Swagger/OpenAPI şeması, otomatik oluşturulan HTML dokümantasyonu ve Postman koleksiyonu `docs/api` klasöründe bulunur.

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

### PHP SDK

```php
<?php
require __DIR__ . '/integrations/sdk/php/ResellerApiClient.php';

use ResellerApi\Sdk\ResellerApiClient;

$client = new ResellerApiClient('https://example.com/api', 'API_KEY', 'API_SECRET', 'shop.example');
$products = $client->getProducts();
$balance = $client->getBalance();
$order = $client->createOrder(42, 'WooCommerce order', 'WC-1001');
```

### Node.js SDK

```javascript
const ResellerApiClient = require('./integrations/sdk/node/reseller-api-client');

const client = new ResellerApiClient('https://example.com/api', 'API_KEY', 'API_SECRET', 'shop.example');

client.getProducts().then(console.log);
client.createOrder(42, { note: 'Node order', external_reference: 'NODE-1' }).then(console.log);
```

### Python SDK

```python
from integrations.sdk.python.reseller_api_client import ResellerApiClient

client = ResellerApiClient('https://example.com/api', 'API_KEY', 'API_SECRET', 'shop.example')
products = client.get_products()
balance = client.get_balance()
order = client.create_order(42, note='Python order', reference='PY-1')
```
