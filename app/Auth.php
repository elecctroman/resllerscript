<?php

namespace App;

use App\Database;
use App\Helpers;
use App\Notifications\ResellerNotifier;
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
        return array('super_admin', 'admin', 'finance', 'support', 'content');
    }

    /**
     * @param string $role
     * @return bool
     */
    public static function isAdminRole($role)
    {
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

        if (!$user || !self::userHasRole($user, $roles)) {
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
            return self::roles();
        }

        if ($role === 'admin') {
            return array('admin', 'finance', 'support', 'content', 'reseller');
        }

        if ($role === 'finance') {
            return array('finance', 'support', 'reseller');
        }

        if ($role === 'support' || $role === 'content') {
            return array('support', 'content', 'reseller');
        }

        return array('reseller');
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
    public static function createUser($name, $email, $password, $role = 'reseller', $balance = 0, array $options = array())
    {
        if (!in_array($role, self::roles(), true)) {
            $role = 'reseller';
        }

        $pdo = Database::connection();
        $status = isset($options['status']) && in_array($options['status'], array('active', 'inactive'), true)
            ? $options['status']
            : 'active';

        $telegramBotToken = isset($options['telegram_bot_token']) ? trim((string)$options['telegram_bot_token']) : null;
        $telegramChatId = isset($options['telegram_chat_id']) ? trim((string)$options['telegram_chat_id']) : null;

        $columns = array('name', 'email', 'password_hash', 'role', 'balance', 'status', 'created_at');
        $placeholders = array(':name', ':email', ':password_hash', ':role', ':balance', ':status', 'NOW()');
        $params = array(
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'balance' => $balance,
            'status' => $status,
        );

        if ($telegramBotToken !== null && $telegramBotToken !== '') {
            $columns[] = 'telegram_bot_token';
            $placeholders[] = ':telegram_bot_token';
            $params['telegram_bot_token'] = $telegramBotToken;
        }

        if ($telegramChatId !== null && $telegramChatId !== '') {
            $columns[] = 'telegram_chat_id';
            $placeholders[] = ':telegram_chat_id';
            $params['telegram_chat_id'] = $telegramChatId;
        }

        $sql = sprintf(
            'INSERT INTO users (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

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
     * @return array|null
     */
    public static function findUserByEmail($email)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(array('email' => $email));

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
        $user = self::findUserByEmail($email);
        if (!$user) {
            return;
        }

        $message = implode("\n", array(
            '🔐 <b>Şifre sıfırlama talebi</b>',
            '',
            'Panel şifrenizi sıfırlamak için aşağıdaki bağlantıyı kullanabilirsiniz:',
            '<a href="' . htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Şifremi Sıfırla</a>',
            '',
            'Bu bağlantı 1 saat boyunca geçerlidir. Eğer bu talebi siz oluşturmadıysanız lütfen hesabınızı kontrol edin.',
        ));

        ResellerNotifier::sendDirect($user, $message);
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
