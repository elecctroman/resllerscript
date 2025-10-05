<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function session_get(string $key, mixed $default = null): mixed
{
    return $_SESSION[$key] ?? $default;
}

function session_set(string $key, mixed $value): void
{
    $_SESSION[$key] = $value;
}

function session_forget(string $key): void
{
    unset($_SESSION[$key]);
}
