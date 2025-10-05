<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

function current_user(): ?array
{
    $userId = session_get('user_id');
    if (!$userId) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, name, email, balance, created_at FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function is_authenticated(): bool
{
    return session_get('user_id') !== null;
}

function attempt_login(string $email, string $password): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    session_set('user_id', (int)$user['id']);
    return true;
}

function register_user(string $name, string $email, string $password): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Bu e-posta adresi zaten kayıtlı.'];
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, balance, created_at) VALUES (:name, :email, :password_hash, 0, NOW())');
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => $passwordHash,
    ]);

    session_regenerate_id(true);
    session_set('user_id', (int)$pdo->lastInsertId());

    return ['success' => true];
}

function logout(): void
{
    session_forget('user_id');
    session_regenerate_id(true);
}
