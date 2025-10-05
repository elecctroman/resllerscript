<?php
require __DIR__ . '/../bootstrap.php';

(new App\Api\Controllers\TokenController())->updateWebhook();
