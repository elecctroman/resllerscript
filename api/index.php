<?php
require __DIR__ . '/bootstrap.php';

json_response(array(
    'message' => 'Reseller API. Use /api/v1/products and /api/v1/orders for REST access.'
));
