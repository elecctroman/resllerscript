<?php

namespace App;

use App\Database;
use App\Helpers;
use PDO;
use RuntimeException;

class Auth
{
    private const ROLE_LABELS = [
        'super_admin' => 'Üst Yönetici',
        'admin' => 'Yönetici',
        'manager' => 'Yönetici Yardımcısı',
        'support' => 'Destek Uzmanı',
        'auditor' => 'Denetçi',
        'reseller' => 'Bayi',
    ];

    private const ROLE_PERMISSIONS = [
        'access_admin_panel' => ['super_admin', 'admin', 'manager', 'support', 'auditor'],
        'manage_users' => ['super_admin', 'admin'],
        'manage_finance' => ['super_admin', 'admin', 'manager'],
        'manage_products' => ['super_admin', 'admin', 'manager'],
        'manage_orders' => ['super_admin', 'admin', 'manager'],
        'manage_support' => ['super_admin', 'admin', 'support'],
        'manage_settings' => ['super_admin'],
        'import_products' => ['super_admin', 'admin'],
        'view_audit_logs' => ['super_admin', 'auditor'],
    ];

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
            return $user;
        }

        return null;
    }

    public static function createUser(string $name, string $email, string $password, string $role = 'reseller', float $balance = 0): int
    {
        if (!array_key_exists($role, self::ROLE_LABELS)) {
            throw new RuntimeException('Geçersiz kullanıcı rolü: ' . $role);
        }

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

    public static function userHasPermission(?array $user, string $permission): bool
    {
        if (!$user || empty($user['role'])) {
            return false;
        }

        if ($user['role'] === 'super_admin') {
            return true;
        }

        $allowedRoles = self::ROLE_PERMISSIONS[$permission] ?? [];

        return in_array($user['role'], $allowedRoles, true);
    }

    public static function requirePermission(string $permission, string $redirect = '/'): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = $_SESSION['user'] ?? null;

        if (!self::userHasPermission($user, $permission)) {
            Helpers::redirect($redirect);
        }
    }

    /**
     * @return array<string,string>
     */
    public static function roleLabels(): array
    {
        return self::ROLE_LABELS;
    }

    /**
     * @return array<string,string>
     */
    public static function assignableRoles(?array $user = null): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = $user ?? ($_SESSION['user'] ?? null);
        $roles = self::ROLE_LABELS;

        if (!$user || ($user['role'] ?? null) !== 'super_admin') {
            unset($roles['super_admin']);
        }

        return $roles;
    }

    /**
     * @return string[]
     */
    public static function rolesForPermission(string $permission): array
    {
        $roles = self::ROLE_PERMISSIONS[$permission] ?? [];

        if (!in_array('super_admin', $roles, true)) {
            $roles[] = 'super_admin';
        }

        return array_values(array_unique($roles));
    }

    public static function roleLabel(string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ucfirst($role);
    }

    public static function isAdminRole(?string $role): bool
    {
        if (!$role) {
            return false;
        }

        if ($role === 'super_admin') {
            return true;
        }

        return in_array($role, self::ROLE_PERMISSIONS['access_admin_panel'], true);
    }
}
