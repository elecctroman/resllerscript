<?php

namespace App\Reports;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;

class ReportService
{
    /**
     * Get monthly order count and revenue summary for the last given number of months.
     */
    public static function getMonthlyOrderSummary(PDO $pdo, int $months = 6): array
    {
        $months = max(1, $months);
        $end = new DateTimeImmutable('first day of this month');
        $start = $end->sub(new DateInterval('P' . ($months - 1) . 'M'));

        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COUNT(*) AS total_orders,
                    COALESCE(SUM(total_amount), 0) AS total_revenue
             FROM package_orders
             WHERE created_at >= :startDate
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
        );
        $stmt->execute([
            ':startDate' => $start->format('Y-m-01 00:00:00'),
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['month']] = [
                'orders' => (int)($row['total_orders'] ?? 0),
                'revenue' => (float)($row['total_revenue'] ?? 0),
            ];
        }

        $labels = [];
        $orders = [];
        $revenue = [];

        $period = new DatePeriod($start, new DateInterval('P1M'), $end->add(new DateInterval('P1M')));
        foreach ($period as $date) {
            $label = $date->format('Y-m');
            $labels[] = $label;
            $orders[] = $indexed[$label]['orders'] ?? 0;
            $revenue[] = $indexed[$label]['revenue'] ?? 0.0;
        }

        return [
            'labels' => $labels,
            'orders' => $orders,
            'revenue' => $revenue,
        ];
    }

    /**
     * Get monthly balance transaction summary for the last given number of months.
     */
    public static function getMonthlyBalanceSummary(PDO $pdo, int $months = 6): array
    {
        $months = max(1, $months);
        $end = new DateTimeImmutable('first day of this month');
        $start = $end->sub(new DateInterval('P' . ($months - 1) . 'M'));

        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END), 0) AS total_credit,
                    COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END), 0) AS total_debit
             FROM balance_transactions
             WHERE created_at >= :startDate
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
        );
        $stmt->execute([
            ':startDate' => $start->format('Y-m-01 00:00:00'),
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['month']] = [
                'credit' => (float)($row['total_credit'] ?? 0),
                'debit' => (float)($row['total_debit'] ?? 0),
            ];
        }

        $labels = [];
        $credits = [];
        $debits = [];
        $net = [];

        $period = new DatePeriod($start, new DateInterval('P1M'), $end->add(new DateInterval('P1M')));
        foreach ($period as $date) {
            $label = $date->format('Y-m');
            $labels[] = $label;
            $credit = $indexed[$label]['credit'] ?? 0.0;
            $debit = $indexed[$label]['debit'] ?? 0.0;
            $credits[] = $credit;
            $debits[] = $debit;
            $net[] = $credit - $debit;
        }

        return [
            'labels' => $labels,
            'credits' => $credits,
            'debits' => $debits,
            'net' => $net,
        ];
    }

    /**
     * Fetch orders in the given date range.
     */
    public static function getOrdersByDateRange(PDO $pdo, ?string $startDate, ?string $endDate): array
    {
        [$normalizedStart, $normalizedEnd] = self::normalizeRange($startDate, $endDate);

        $stmt = $pdo->prepare(
            "SELECT po.*, p.name AS package_name
             FROM package_orders po
             LEFT JOIN packages p ON p.id = po.package_id
             WHERE po.created_at BETWEEN :startDate AND :endDate
             ORDER BY po.created_at ASC"
        );
        $stmt->execute([
            ':startDate' => $normalizedStart,
            ':endDate' => $normalizedEnd,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch balance transactions in the given date range.
     */
    public static function getBalanceTransactionsByDateRange(PDO $pdo, ?string $startDate, ?string $endDate): array
    {
        [$normalizedStart, $normalizedEnd] = self::normalizeRange($startDate, $endDate);

        $stmt = $pdo->prepare(
            "SELECT bt.*, u.name AS user_name, u.email AS user_email
             FROM balance_transactions bt
             INNER JOIN users u ON u.id = bt.user_id
             WHERE bt.created_at BETWEEN :startDate AND :endDate
             ORDER BY bt.created_at ASC"
        );
        $stmt->execute([
            ':startDate' => $normalizedStart,
            ':endDate' => $normalizedEnd,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function normalizeRange(?string $startDate, ?string $endDate): array
    {
        $start = self::parseDate($startDate) ?? new DateTimeImmutable('first day of this month');
        $end = self::parseDate($endDate) ?? new DateTimeImmutable('last day of this month');

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [
            $start->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
            $end->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
        ];
    }

    private static function parseDate(?string $date): ?DateTimeImmutable
    {
        if (empty($date)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return null;
        }

        return (new DateTimeImmutable())->setTimestamp($timestamp);
    }
}
