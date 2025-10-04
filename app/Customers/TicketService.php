<?php

namespace App\Customers;

use App\Database;
use PDO;

class TicketService
{
    public static function create(int $customerId, string $subject, string $message): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO customer_tickets (customer_id, subject, message) VALUES (:customer, :subject, :message)');
        $stmt->execute(array(
            ':customer' => $customerId,
            ':subject' => $subject,
            ':message' => $message,
        ));
        return (int)$pdo->lastInsertId();
    }

    public static function addReply(int $ticketId, string $authorType, ?int $authorId, string $message): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO customer_ticket_replies (ticket_id, author_type, author_id, message) VALUES (:ticket, :author_type, :author_id, :message)');
        $stmt->execute(array(
            ':ticket' => $ticketId,
            ':author_type' => $authorType,
            ':author_id' => $authorId,
            ':message' => $message,
        ));
    }

    public static function listForCustomer(int $customerId)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM customer_tickets WHERE customer_id = :customer ORDER BY created_at DESC');
        $stmt->execute(array(':customer' => $customerId));
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $replyStmt = $pdo->prepare('SELECT * FROM customer_ticket_replies WHERE ticket_id = :ticket ORDER BY created_at ASC');
        foreach ($tickets as &$ticket) {
            $replyStmt->execute(array(':ticket' => $ticket['id']));
            $ticket['replies'] = $replyStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $tickets;
    }
}
