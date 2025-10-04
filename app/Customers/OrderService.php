<?php

namespace App\Customers;

use App\Database;
use PDO;
use PDOException;

class OrderService
{
    public static function placeOrder(int $customerId, int $productId, int $quantity, float $totalPrice, string $paymentMethod, array $metadata = array()): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO customer_orders (customer_id, product_id, quantity, total_price, payment_method, metadata) VALUES (:customer, :product, :quantity, :total_price, :payment_method, :metadata)');
            $stmt->execute(array(
                ':customer' => $customerId,
                ':product' => $productId,
                ':quantity' => $quantity,
                ':total_price' => $totalPrice,
                ':payment_method' => $paymentMethod,
                ':metadata' => $metadata ? json_encode($metadata) : null,
            ));

            $orderId = (int)$pdo->lastInsertId();

            if ($paymentMethod === 'Cuzdan') {
                $walletStmt = $pdo->prepare('UPDATE customers SET balance = balance - :amount WHERE id = :id AND balance >= :amount');
                $walletStmt->execute(array(':amount' => $totalPrice, ':id' => $customerId));
                if ($walletStmt->rowCount() === 0) {
                    throw new \RuntimeException('Bakiye yetersiz.');
                }

                $logStmt = $pdo->prepare('INSERT INTO wallet_logs (customer_id, amount, type, description, reference_id) VALUES (:customer, :amount, "cikarma", :description, :reference)');
                $logStmt->execute(array(
                    ':customer' => $customerId,
                    ':amount' => $totalPrice,
                    ':description' => 'Sipariş #'.$orderId,
                    ':reference' => 'order:'.$orderId,
                ));
            }

            $pdo->commit();
            return $orderId;
        } catch (PDOException $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function listForCustomer(int $customerId, int $limit = 50)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT co.*, p.name AS product_name FROM customer_orders co INNER JOIN products p ON p.id = co.product_id WHERE co.customer_id = :customer ORDER BY co.created_at DESC LIMIT :limit');
        $stmt->bindValue(':customer', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function recent(int $limit = 10)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT co.*, c.email AS customer_email, p.name AS product_name FROM customer_orders co INNER JOIN customers c ON c.id = co.customer_id INNER JOIN products p ON p.id = co.product_id ORDER BY co.created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
