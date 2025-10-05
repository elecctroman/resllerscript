<?php
declare(strict_types=1);

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Invalid request method.'], 405);
    }
}

function require_auth(): void
{
    if (!is_authenticated()) {
        json_response(['error' => 'Unauthorized'], 401);
    }
}

function redirect_if_not_authenticated(): void
{
    if (!is_authenticated()) {
        header('Location: /epin_client/public/login.php');
        exit;
    }
}

function redirect_if_authenticated(): void
{
    if (is_authenticated()) {
        header('Location: /epin_client/public/dashboard.php');
        exit;
    }
}
