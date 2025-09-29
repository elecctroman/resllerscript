<?php
require __DIR__ . '/bootstrap.php';

json_response(array(
    'success' => true,
    'message' => 'Reseller API v1. Kullanılabilir uç noktalar için /api/v1/products ve /api/v1/orders adreslerini ziyaret edin.',
));
