<?php

namespace App\Customers;

use App\Database;
use PDO;

class WalletService
{
    public static function history(int $customerId, int $limit = 100)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM wallet_logs WHERE customer_id = :customer ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':customer', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function add(int $customerId, float $amount, string $description, ?string $reference = null): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE customers SET balance = balance + :amount WHERE id = :id')
                ->execute(array(':amount' => $amount, ':id' => $customerId));

            $pdo->prepare('INSERT INTO wallet_logs (customer_id, amount, type, description, reference_id) VALUES (:customer, :amount, "ekleme", :description, :reference)')
                ->execute(array(
                    ':customer' => $customerId,
                    ':amount' => $amount,
                    ':description' => $description,
                    ':reference' => $reference,
                ));
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function createTopupRequest(int $customerId, float $amount, string $method, ?string $reference = null, ?string $notes = null): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO wallet_topup_requests (customer_id, amount, method, reference, notes) VALUES (:customer, :amount, :method, :reference, :notes)');
        $stmt->execute(array(
            ':customer' => $customerId,
            ':amount' => $amount,
            ':method' => $method,
            ':reference' => $reference,
            ':notes' => $notes,
        ));
    }

    public static function pendingRequests(int $customerId, int $limit = 20): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM wallet_topup_requests WHERE customer_id = :customer AND status = "pending" ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':customer', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }
}
