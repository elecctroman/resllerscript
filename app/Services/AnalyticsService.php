<?php declare(strict_types=1);

namespace App\Services;

use App\Database;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;

final class AnalyticsService
{
    /**
     * @param int $userId
     * @param string $email
     * @return array<string,mixed>
     */
    public static function buildForUser(int $userId, string $email): array
    {
        $pdo = Database::connection();
        $metrics = array(
            'order_trend' => array(),
            'profit_trend' => array(),
            'top_products' => array(),
            'package_orders' => array(),
            'balance_projection' => array(
                'average_daily_spend' => 0.0,
                'days_remaining' => null,
            ),
        );

        $windowDays = 30;
        $end = new DateTimeImmutable('today');
        $start = $end->sub(new DateInterval('P' . $windowDays . 'D'));
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->add(new DateInterval('P1D')));

        $trend = array();
        foreach ($period as $day) {
            /** @var DateTimeImmutable $day */
            $key = $day->format('Y-m-d');
            $trend[$key] = array('orders' => 0, 'revenue' => 0.0, 'profit' => 0.0);
        }

        $stmt = $pdo->prepare(
            "SELECT DATE(po.created_at) AS order_day, SUM(po.total_amount) AS revenue, COUNT(*) AS total_orders, " .
            "SUM(po.total_amount - COALESCE(pr.cost_price_try,0) * COALESCE(po.quantity,1)) AS profit " .
            "FROM product_orders po INNER JOIN products pr ON po.product_id = pr.id " .
            "WHERE po.user_id = :user_id AND po.created_at >= :start GROUP BY order_day"
        );
        $stmt->execute(array(
            'user_id' => $userId,
            'start' => $start->format('Y-m-d 00:00:00'),
        ));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $day = isset($row['order_day']) ? (string)$row['order_day'] : '';
            if (isset($trend[$day])) {
                $trend[$day]['orders'] = (int)$row['total_orders'];
                $trend[$day]['revenue'] = (float)$row['revenue'];
                $trend[$day]['profit'] = (float)$row['profit'];
            }
        }

        $metrics['order_trend'] = array();
        $metrics['profit_trend'] = array();
        $totalRevenue = 0.0;
        foreach ($trend as $day => $values) {
            $metrics['order_trend'][] = array(
                'day' => $day,
                'orders' => $values['orders'],
                'revenue' => round($values['revenue'], 2),
            );
            $metrics['profit_trend'][] = array(
                'day' => $day,
                'profit' => round($values['profit'], 2),
            );
            $totalRevenue += $values['revenue'];
        }

        $productStmt = $pdo->prepare(
            "SELECT pr.name, SUM(po.total_amount) AS revenue, COUNT(*) AS total_orders " .
            "FROM product_orders po INNER JOIN products pr ON po.product_id = pr.id " .
            "WHERE po.user_id = :user_id GROUP BY po.product_id ORDER BY revenue DESC LIMIT 10"
        );
        $productStmt->execute(array('user_id' => $userId));
        $metrics['top_products'] = array_map(static function (array $row) {
            return array(
                'name' => isset($row['name']) ? (string)$row['name'] : '-',
                'revenue' => isset($row['revenue']) ? round((float)$row['revenue'], 2) : 0.0,
                'orders' => isset($row['total_orders']) ? (int)$row['total_orders'] : 0,
            );
        }, $productStmt->fetchAll(PDO::FETCH_ASSOC));

        $packageStmt = $pdo->prepare(
            "SELECT DATE(created_at) AS order_day, SUM(total_amount) AS revenue, COUNT(*) AS total_orders " .
            "FROM package_orders WHERE email = :email AND created_at >= :start GROUP BY order_day"
        );
        $packageStmt->execute(array(
            'email' => $email,
            'start' => $start->format('Y-m-d 00:00:00'),
        ));
        $metrics['package_orders'] = $packageStmt->fetchAll(PDO::FETCH_ASSOC);

        $averageDaily = $windowDays > 0 ? ($totalRevenue / $windowDays) : 0.0;
        $metrics['balance_projection']['average_daily_spend'] = round($averageDaily, 2);

        $balanceStmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id LIMIT 1');
        $balanceStmt->execute(array('id' => $userId));
        $balance = (float)$balanceStmt->fetchColumn();
        if ($averageDaily > 0) {
            $metrics['balance_projection']['days_remaining'] = round($balance / $averageDaily, 1);
        }
        $metrics['balance_projection']['balance'] = round($balance, 2);

        return $metrics;
    }
}
