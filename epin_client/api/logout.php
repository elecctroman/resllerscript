<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_post();

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => 'CSRF doğrulaması başarısız.'], 400);
}

logout();
json_response(['success' => true]);
