<?php
require __DIR__ . '/bootstrap.php';

json_response(array(
    'success' => true,
    'message' => 'Reseller API v1. Ürün ve sipariş uç noktaları için /api/v1/products ve /api/v1/orders adreslerini, SMM entegrasyonu için ise /api/smm.php adresini kullanın.'
));
