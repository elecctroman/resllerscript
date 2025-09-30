<?php

namespace App;

use App\Database;
use App\Helpers;
use App\Settings;
use PDO;
use RuntimeException;

class Auth
{
    private static $roleLabels = array(
        'super_admin' => 'Süper Yönetici',
        'admin' => 'Yönetici',
        'finance' => 'Finans',
        'support' => 'Destek',
        'content' => 'İçerik',
        'reseller' => 'Bayi',
        'demo' => 'Yönetici (Demo)',
    );

    /**
     * @return array
     */
    public static function roles()
    {
        return array_keys(self::$roleLabels);
    }

    /**
     * @return array
     */
    public static function adminRoles()
    {
        return array('super_admin', 'admin', 'finance', 'support', 'content', 'demo');
    }

    /**
     * @param string $role
     * @return bool
     */
    public static function isAdminRole($role)
    {
        if ($role === 'demo') {
            return Settings::get('demo_mode_enabled') === '1';
        }

        return in_array($role, self::adminRoles(), true);
    }

    /**
     * @param array|string $userOrRole
     * @param array|string $roles
     * @return bool
     */
    public static function userHasRole($userOrRole, $roles)
    {
        $role = is_array($userOrRole) ? (isset($userOrRole['role']) ? $userOrRole['role'] : null) : $userOrRole;

        if ($role === null) {
            return false;
        }

        if (!is_array($roles)) {
            $roles = array($roles);
        }

        if ($role === 'demo') {
            if (Settings::get('demo_mode_enabled') !== '1') {
                return false;
            }

            foreach ($roles as $candidate) {
                if ($candidate === 'demo' || self::isAdminRole($candidate)) {
                    return true;
                }
            }

            return false;
        }

        return in_array($role, $roles, true);
    }

    /**
     * @param array|string $roles
     * @param string $redirect
     * @return void
     */
    public static function requireRoles($roles, $redirect = '/')
    {
        if (!is_array($roles)) {
            $roles = array($roles);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = isset($_SESSION['user']) ? $_SESSION['user'] : null;

        if (!$user) {
            Helpers::redirect($redirect);
        }

        if ($user['role'] === 'demo' && Settings::get('demo_mode_enabled') === '1') {
            return;
        }

        if (!self::userHasRole($user, $roles)) {
            Helpers::redirect($redirect);
        }
    }

    /**
     * @param array|string $actor
     * @return array
     */
    public static function assignableRoles($actor)
    {
        $role = is_array($actor) ? (isset($actor['role']) ? $actor['role'] : null) : $actor;

        if ($role === 'super_admin') {
            $assignable = self::roles();
        } elseif ($role === 'admin') {
            $assignable = array('admin', 'finance', 'support', 'content', 'reseller');
        } elseif ($role === 'finance') {
            $assignable = array('finance', 'support', 'reseller');
        } elseif ($role === 'support' || $role === 'content') {
            $assignable = array('support', 'content', 'reseller');
        } else {
            $assignable = array('reseller');
        }

        return array_values(array_diff($assignable, array('demo')));
    }

    /**
     * @param string $role
     * @return string
     */
    public static function roleLabel($role)
    {
        return isset(self::$roleLabels[$role]) ? self::$roleLabels[$role] : ucfirst($role);
    }

    /**
     * @param string $identifier
     * @param string $password
     * @return array|null
     */
    public static function attempt($identifier, $password)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE (email = :identifier OR name = :identifier) AND status = :status LIMIT 1');
        $stmt->execute([
            'identifier' => $identifier,
            'status' => 'active'
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['role'] === 'demo' && Settings::get('demo_mode_enabled') !== '1') {
                return null;
            }

            return $user;
        }

        return null;
    }

    /**
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $role
     * @param float $balance
     * @return int
     */
    public static function createUser($name, $email, $password, $role = 'reseller', $balance = 0)
    {
        if (!in_array($role, self::roles(), true)) {
            $role = 'reseller';
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

    /**
     * @param int $userId
     * @return array|null
     */
    public static function findUser($userId)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @param string $email
     * @param string $token
     * @param string $resetUrl
     * @return void
     */
    public static function sendResetLink($email, $token, $resetUrl)
    {
        $subject = 'Şifre Sıfırlama Talebi';
        $message = "Merhaba,\n\nŞifrenizi sıfırlamak için lütfen aşağıdaki bağlantıya tıklayın:\n$resetUrl\n\nBu bağlantı 1 saat boyunca geçerlidir.\n\nSaygılarımızla.";
        Mailer::send($email, $subject, $message);
    }

    /**
     * @param string $email
     * @return string
     */
    public static function createPasswordReset($email)
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

    /**
     * @param string $token
     * @return array|null
     */
    public static function validateResetToken($token)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = :token AND used = 0 AND expires_at > NOW() LIMIT 1');
        $stmt->execute(['token' => $token]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @param string $email
     * @param string $password
     * @return void
     */
    public static function resetPassword($email, $password)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE email = :email');
        $stmt->execute([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT)
        ]);
    }

    /**
     * @param int $id
     * @return void
     */
    public static function markResetTokenUsed($id)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
