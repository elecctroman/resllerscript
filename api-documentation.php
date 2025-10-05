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

<pre class="api-doc-code"><code>{
  "success": false,
  "error": "API anahtarı doğrulanamadı."
}</code></pre>

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

<pre class="api-doc-code"><code>{
  "success": true,
  "data": {
    "orders": [4512],
    "remaining_balance": 3120.60
  }
}</code></pre>

<pre class="api-doc-code"><code>POST <?= Helpers::sanitize($baseUrl) ?>/token-webhook
Content-Type: application/json

{
  "webhook_url": "https://entegrasyon.ornek.com/api/webhooks/reseller"
}</code></pre>

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
