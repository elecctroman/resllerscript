<?php
require __DIR__ . '/../bootstrap.php';

$token = authenticate_token();
require_scope($token, 'read');

json_response(array(
    'success' => true,
    'message' => 'Reseller API v1 aktif. /products.php, /orders.php ve /token-webhook.php uç noktalarını kullanabilirsiniz.'
));
