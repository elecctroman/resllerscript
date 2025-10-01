<?php
require __DIR__ . '/../bootstrap.php';

authenticate_token();

json_response(array(
    'success' => true,
    'message' => 'Reseller API v1 aktif. /products.php, /orders.php ve /token-webhook.php uç noktalarını kullanabilirsiniz.'
));
