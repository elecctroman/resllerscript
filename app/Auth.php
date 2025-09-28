<?php

namespace App;

use App\Database;
use PDO;
use RuntimeException;

class Auth
{
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
    public static function createUser($name, $email, $password, $role = 'reseller', $balance = 0)
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
