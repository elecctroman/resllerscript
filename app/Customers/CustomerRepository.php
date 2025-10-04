<?php

namespace App\Customers;

use App\Database;
use PDO;
use PDOException;

class CustomerRepository
{
    public static function findByEmail(string $email)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE email = :email LIMIT 1');
        $stmt->execute(array(':email' => $email));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findById(int $id)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(array(':id' => $id));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByToken(string $token)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE api_token = :token LIMIT 1');
        $stmt->execute(array(':token' => $token));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(array $data)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO customers (name, surname, email, phone, password, locale, currency, api_token, api_token_created_at, api_status, api_scopes, api_ip_whitelist, api_otp_secret) VALUES (:name, :surname, :email, :phone, :password, :locale, :currency, :api_token, :token_created, :status, :scopes, :whitelist, :otp_secret)');
        $token = bin2hex(random_bytes(16));
        $stmt->execute(array(
            ':name' => $data['name'],
            ':surname' => $data['surname'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':locale' => $data['locale'],
            ':currency' => $data['currency'],
            ':api_token' => $token,
            ':token_created' => date('Y-m-d H:i:s'),
            ':status' => 'active',
            ':scopes' => 'full',
            ':whitelist' => null,
            ':otp_secret' => null,
        ));

        return self::findById((int)$pdo->lastInsertId());
    }

    public static function updateProfile(int $id, array $data): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE customers SET name = :name, surname = :surname, phone = :phone, locale = :locale, currency = :currency WHERE id = :id');
        $stmt->execute(array(
            ':name' => $data['name'],
            ':surname' => $data['surname'],
            ':phone' => $data['phone'],
            ':locale' => $data['locale'],
            ':currency' => $data['currency'],
            ':id' => $id,
        ));
    }

    public static function updatePassword(int $id, string $password): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE customers SET password = :password WHERE id = :id');
        $stmt->execute(array(':password' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id));
    }

    public static function list(int $limit = 50, int $offset = 0)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM customers ORDER BY created_at DESC LIMIT :offset, :limit');
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function count(): int
    {
        $pdo = Database::connection();
        return (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    }

    public static function adjustBalance(int $id, float $amount, string $type, string $description = '', ?string $reference = null): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE customers SET balance = balance + :amount WHERE id = :id');
            $stmt->execute(array(':amount' => $amount, ':id' => $id));

            $logStmt = $pdo->prepare('INSERT INTO wallet_logs (customer_id, amount, type, description, reference_id) VALUES (:customer, :amount, :type, :description, :reference)');
            $logStmt->execute(array(
                ':customer' => $id,
                ':amount' => abs($amount),
                ':type' => $type,
                ':description' => $description,
                ':reference' => $reference,
            ));

            $pdo->commit();
        } catch (PDOException $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function regenerateToken(int $id): string
    {
        $token = bin2hex(random_bytes(16));
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE customers SET api_token = :token, api_token_created_at = NOW(), api_status = "active" WHERE id = :id');
        $stmt->execute(array(':token' => $token, ':id' => $id));
        return $token;
    }

    public static function updateApiSettings(int $id, array $data): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE customers SET api_status = :status, api_scopes = :scopes, api_ip_whitelist = :whitelist WHERE id = :id');
        $stmt->execute(array(
            ':status' => $data['status'],
            ':scopes' => $data['scopes'],
            ':whitelist' => $data['ip_whitelist'],
            ':id' => $id,
        ));
    }

    public static function updateOtpSecret(int $id, ?string $secret): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE customers SET api_otp_secret = :secret WHERE id = :id');
        $stmt->execute(array(':secret' => $secret, ':id' => $id));
    }
}
