<?php
require __DIR__ . '/../bootstrap.php';

(new App\Api\Controllers\ProductsController())->index();
