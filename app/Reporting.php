<?php

namespace App;

use App\Database;
use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

class Reporting
{
    /**
     * Build a monthly overview for the provided number of months (including current month).
     *
     * @param int $months
     * @return array
     */
    public static function monthlyOverview($months = 6)
    {
        $months = (int)$months;
        if ($months <= 0) {
            $months = 6;
        }

        $now = new DateTimeImmutable('first day of this month 00:00:00');
        $start = $now->sub(new DateInterval('P' . ($months - 1) . 'M'));
        $end = $now->add(new DateInterval('P1M'));

        $timeline = array();
        $period = new DatePeriod($start, new DateInterval('P1M'), $end);
        foreach ($period as $point) {
            $key = $point->format('Y-m');
            $timeline[$key] = array(
                'key' => $key,
                'label' => $point->format('M Y'),
                'package_orders' => 0,
                'product_orders' => 0,
                'revenue' => 0.0,
                'balance_credits' => 0.0,
                'balance_debits' => 0.0,
            );
        }

        $pdo = Database::connection();
        $bounds = array(
            'start' => $start->format('Y-m-01 00:00:00'),
            'end' => $end->format('Y-m-01 00:00:00'),
        );

        $packageStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total_orders, SUM(CASE WHEN status IN ('paid','completed') THEN total_amount ELSE 0 END) AS revenue FROM package_orders WHERE created_at >= :start AND created_at < :end GROUP BY month_key");
        $packageStmt->execute($bounds);
        foreach ($packageStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $monthKey = isset($row['month_key']) ? $row['month_key'] : '';
            if (isset($timeline[$monthKey])) {
                $timeline[$monthKey]['package_orders'] = (int)$row['total_orders'];
                $timeline[$monthKey]['revenue'] += (float)$row['revenue'];
            }
        }

        $productStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total_orders, SUM(CASE WHEN status IN ('processing','completed') THEN price * quantity ELSE 0 END) AS revenue FROM product_orders WHERE created_at >= :start AND created_at < :end GROUP BY month_key");
        $productStmt->execute($bounds);
        foreach ($productStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $monthKey = isset($row['month_key']) ? $row['month_key'] : '';
            if (isset($timeline[$monthKey])) {
                $timeline[$monthKey]['product_orders'] = (int)$row['total_orders'];
                $timeline[$monthKey]['revenue'] += (float)$row['revenue'];
            }
        }

        $balanceStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) AS total_credit, SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) AS total_debit FROM balance_transactions WHERE created_at >= :start AND created_at < :end GROUP BY month_key");
        $balanceStmt->execute($bounds);
        foreach ($balanceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $monthKey = isset($row['month_key']) ? $row['month_key'] : '';
            if (isset($timeline[$monthKey])) {
                $timeline[$monthKey]['balance_credits'] = (float)$row['total_credit'];
                $timeline[$monthKey]['balance_debits'] = (float)$row['total_debit'];
            }
        }

        $summary = array(
            'package_orders' => 0,
            'product_orders' => 0,
            'revenue' => 0.0,
            'balance_credits' => 0.0,
            'balance_debits' => 0.0,
        );

        foreach ($timeline as $monthData) {
            $summary['package_orders'] += (int)$monthData['package_orders'];
            $summary['product_orders'] += (int)$monthData['product_orders'];
            $summary['revenue'] += (float)$monthData['revenue'];
            $summary['balance_credits'] += (float)$monthData['balance_credits'];
            $summary['balance_debits'] += (float)$monthData['balance_debits'];
        }

        return array(
            'months' => array_values($timeline),
            'summary' => $summary,
        );
    }

    /**
     * Summaries for the provided range (inclusive start, inclusive end).
     *
     * @param DateTimeInterface $start
     * @param DateTimeInterface $end
     * @return array
     */
    public static function summaryInRange(DateTimeInterface $start, DateTimeInterface $end)
    {
        $pdo = Database::connection();
        $bounds = self::rangeBounds($start, $end);

        $packageStmt = $pdo->prepare("SELECT COUNT(*) AS total_orders, SUM(CASE WHEN status IN ('paid','completed') THEN total_amount ELSE 0 END) AS revenue FROM package_orders WHERE created_at >= :start AND created_at < :end");
        $packageStmt->execute($bounds);
        $package = $packageStmt->fetch(PDO::FETCH_ASSOC) ?: array('total_orders' => 0, 'revenue' => 0);

        $productStmt = $pdo->prepare("SELECT COUNT(*) AS total_orders, SUM(CASE WHEN status IN ('processing','completed') THEN price * quantity ELSE 0 END) AS revenue FROM product_orders WHERE created_at >= :start AND created_at < :end");
        $productStmt->execute($bounds);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC) ?: array('total_orders' => 0, 'revenue' => 0);

        $balanceStmt = $pdo->prepare("SELECT SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) AS total_credit, SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) AS total_debit FROM balance_transactions WHERE created_at >= :start AND created_at < :end");
        $balanceStmt->execute($bounds);
        $balance = $balanceStmt->fetch(PDO::FETCH_ASSOC) ?: array('total_credit' => 0, 'total_debit' => 0);

        return array(
            'packages' => array(
                'total' => (int)$package['total_orders'],
                'revenue' => (float)$package['revenue'],
            ),
            'products' => array(
                'total' => (int)$product['total_orders'],
                'revenue' => (float)$product['revenue'],
            ),
            'balance' => array(
                'credits' => (float)$balance['total_credit'],
                'debits' => (float)$balance['total_debit'],
            ),
        );
    }

    /**
     * Retrieve detailed package and product orders for the provided range.
     *
     * @param DateTimeInterface $start
     * @param DateTimeInterface $end
     * @return array
     */
    public static function ordersInRange(DateTimeInterface $start, DateTimeInterface $end)
    {
        $pdo = Database::connection();
        $bounds = self::rangeBounds($start, $end);

        $packageStmt = $pdo->prepare('SELECT po.id, po.name, po.email, po.status, po.total_amount, po.created_at, p.name AS package_name FROM package_orders po INNER JOIN packages p ON p.id = po.package_id WHERE po.created_at >= :start AND po.created_at < :end ORDER BY po.created_at DESC');
        $packageStmt->execute($bounds);
        $packages = $packageStmt->fetchAll(PDO::FETCH_ASSOC);

        $productStmt = $pdo->prepare('SELECT o.id, o.user_id, o.quantity, o.note, o.admin_note, o.price, o.status, o.created_at, pr.name AS product_name, u.name AS user_name, u.email AS user_email FROM product_orders o INNER JOIN products pr ON pr.id = o.product_id INNER JOIN users u ON u.id = o.user_id WHERE o.created_at >= :start AND o.created_at < :end ORDER BY o.created_at DESC');
        $productStmt->execute($bounds);
        $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

        return array(
            'packages' => $packages,
            'products' => $products,
        );
    }

    /**
     * Retrieve balance transactions for the provided range.
     *
     * @param DateTimeInterface $start
     * @param DateTimeInterface $end
     * @return array
     */
    public static function balanceTransactionsInRange(DateTimeInterface $start, DateTimeInterface $end)
    {
        $pdo = Database::connection();
        $bounds = self::rangeBounds($start, $end);

        $stmt = $pdo->prepare('SELECT bt.id, bt.user_id, bt.amount, bt.type, bt.description, bt.created_at, u.name AS user_name, u.email AS user_email FROM balance_transactions bt INNER JOIN users u ON u.id = bt.user_id WHERE bt.created_at >= :start AND bt.created_at < :end ORDER BY bt.created_at DESC');
        $stmt->execute($bounds);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Convert the provided range into SQL bounds (inclusive end).
     *
     * @param DateTimeInterface $start
     * @param DateTimeInterface $end
     * @return array
     */
    private static function rangeBounds(DateTimeInterface $start, DateTimeInterface $end)
    {
        $rangeStart = (new DateTime($start->format('Y-m-d 00:00:00')))->format('Y-m-d H:i:s');
        $rangeEnd = (new DateTime($end->format('Y-m-d 00:00:00')))->add(new DateInterval('P1D'))->format('Y-m-d H:i:s');

        return array(
            'start' => $rangeStart,
            'end' => $rangeEnd,
        );
    }
}
