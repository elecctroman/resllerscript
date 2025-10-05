<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

function csrf_token(): string
{
    $token = session_get('csrf_token');
    if (!$token) {
        $token = bin2hex(random_bytes(32));
        session_set('csrf_token', $token);
    }
    return $token;
}

function verify_csrf(?string $token): bool
{
    if (!$token) {
        return false;
    }
    $stored = session_get('csrf_token');
    if (!$stored) {
        return false;
    }
    return hash_equals($stored, $token);
}
