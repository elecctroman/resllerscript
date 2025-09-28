<?php

namespace App\Services;

use App\Auth;
use App\Database;
use App\Mailer;
use App\Telegram;
use PDO;

class PackageOrderService
{
    /**
     * @param array $order
     * @return array{user_id:int,password:?string}
     */
    public static function fulfill(array $order)
    {
        $pdo = Database::connection();

        $userStmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $userStmt->execute(['email' => $order['email']]);
        $existingUser = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            $userId = (int)$existingUser['id'];
            $password = null;
        } else {
            $password = bin2hex(random_bytes(4));
            $userId = Auth::createUser($order['name'], $order['email'], $password, 'reseller', (float)$order['initial_balance']);
        }

        $initialCredit = isset($order['initial_balance']) ? (float)$order['initial_balance'] : 0.0;

        if ($initialCredit > 0 && !$existingUser) {
            $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')
                ->execute([
                    'user_id' => $userId,
                    'amount' => $initialCredit,
                    'type' => 'credit',
                    'description' => $order['package_name'] . ' paket başlangıç bakiyesi',
                ]);
        } elseif ($initialCredit > 0 && $existingUser) {
            $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')
                ->execute([
                    'user_id' => $userId,
                    'amount' => $initialCredit,
                    'type' => 'credit',
                    'description' => $order['package_name'] . ' paket başlangıç bakiyesi',
                ]);

            $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')
                ->execute([
                    'amount' => $initialCredit,
                    'id' => $userId,
                ]);
        }

        $hostName = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'bayi-paneli';
        $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $hostName . '/';

        $message = "Merhaba {$order['name']},\n\n" .
            "Bayilik hesabınız aktif edilmiştir.\n" .
            "Panel girişi: {$loginUrl}\n" .
            "Kullanıcı adı: {$order['email']}\n";

        if ($password) {
            $message .= "Geçici şifre: {$password}\n";
        } else {
            $message .= "Kayıtlı şifrenizle giriş yapabilirsiniz.\n";
        }

        $message .= "\nSatın aldığınız paket: {$order['package_name']}\nTutar: $" . number_format((float)$order['price'], 2, '.', ',') . "\n\nİyi çalışmalar.";

        Mailer::send($order['email'], 'Bayilik Hesabınız Hazır', $message);

        Telegram::notify(sprintf(
            "Yeni teslimat tamamlandı!\nBayi: %s\nPaket: %s\nTutar: $%s",
            $order['name'],
            $order['package_name'],
            number_format((float)$order['price'], 2, '.', ',')
        ));

        return [
            'user_id' => $userId,
            'password' => $password,
        ];
    }

    /**
     * @param int $orderId
     * @return array|null
     */
    public static function loadOrder($orderId)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT po.*, p.name AS package_name, p.initial_balance, p.price FROM package_orders po INNER JOIN packages p ON po.package_id = p.id WHERE po.id = :id');
        $stmt->execute(['id' => $orderId]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return null;
        }

        $order['price'] = isset($order['total_amount']) ? (float)$order['total_amount'] : (float)$order['price'];

        return $order;
    }

    /**
     * @param int   $orderId
     * @param array $order
     * @return void
     */
    public static function markCompleted($orderId, array $order)
    {
        $pdo = Database::connection();
        $pdo->prepare('UPDATE package_orders SET status = :status, updated_at = NOW() WHERE id = :id')
            ->execute([
                'status' => 'completed',
                'id' => $orderId,
            ]);
    }
}
