<?php

namespace App;

use App\Database;
use App\Settings;
use PDO;

class Auth
{
    public static function attempt(string $identifier, string $password): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE (email = :identifier OR name = :identifier) AND status = :status LIMIT 1');
        $stmt->execute([
            'identifier' => $identifier,
            'status' => 'active'
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['role'] === 'demo') {
                $demoModeEnabled = Settings::get('demo_mode_enabled', '0') === '1';

                if (!$demoModeEnabled) {
                    return null;
                }
            }

            return $user;
        }

        return null;
    }

    public static function createUser(string $name, string $email, string $password, string $role = 'reseller', float $balance = 0): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, balance, status, created_at) VALUES (:name, :email, :password_hash, :role, :balance, :status, NOW())');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'balance' => $balance,
            'status' => 'active'
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function findUser(int $userId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function sendResetLink(string $email, string $token, string $resetUrl): void
    {
        $subject = 'Şifre Sıfırlama Talebi';
        $message = "Merhaba,\n\nŞifrenizi sıfırlamak için lütfen aşağıdaki bağlantıya tıklayın:\n$resetUrl\n\nBu bağlantı 1 saat boyunca geçerlidir.\n\nSaygılarımızla.";
        Mailer::send($email, $subject, $message);
    }

    public static function createPasswordReset(string $email): string
    {
        $pdo = Database::connection();
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTime('+1 hour'))->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare('INSERT INTO password_resets (email, token, expires_at, used) VALUES (:email, :token, :expires_at, 0)');
        $stmt->execute([
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt
        ]);

        return $token;
    }

    public static function validateResetToken(string $token): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = :token AND used = 0 AND expires_at > NOW() LIMIT 1');
        $stmt->execute(['token' => $token]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function resetPassword(string $email, string $password): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE email = :email');
        $stmt->execute([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT)
        ]);
    }

    public static function markResetTokenUsed(int $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function ensureDemoAccount(bool $active): void
    {
        $pdo = Database::connection();
        $email = 'demo@demo.com';
        $name = 'demo';
        $passwordHash = password_hash('demo123!', PASSWORD_BCRYPT);

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($active) {
            if ($user) {
                $update = $pdo->prepare('UPDATE users SET name = :name, email = :email, role = :role, status = :status, password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
                $update->execute([
                    'id' => $user['id'],
                    'name' => $name,
                    'email' => $email,
                    'role' => 'demo',
                    'status' => 'active',
                    'password_hash' => $passwordHash,
                ]);
            } else {
                $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, balance, status, created_at) VALUES (:name, :email, :password_hash, :role, 0, :status, NOW())');
                $insert->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'role' => 'demo',
                    'status' => 'active',
                ]);
            }
        } elseif ($user) {
            $deactivate = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
            $deactivate->execute([
                'id' => $user['id'],
                'status' => 'inactive',
            ]);
        }
    }
}
