<?php
require __DIR__ . '/bootstrap.php';

json_response(array(
    'message' => 'Reseller API hazır. Güncel REST uç noktaları için /api/v2 yolunu kullanın.',
    'links' => array(
        'openapi' => '/api/openapi.yaml',
        'documentation' => '/api/docs/index.html',
    ),
));
