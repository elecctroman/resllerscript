<?php

namespace App\Services;

use App\Auth;
use App\Database;
use App\Helpers;
use App\Notifications\ResellerNotifier;
use App\Telegram;
use PDO;
use PDOException;
use RuntimeException;

class PackageOrderService
{
    /**
     * @param array $order
     * @return array{user_id:int,password:?string}
     */
    public static function fulfill(array $order)
    {
        $pdo = Database::connection();

        try {
            $userStmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $userStmt->execute(['email' => $order['email']]);
            $existingUser = $userStmt->fetch(PDO::FETCH_ASSOC);

            $initialCredit = isset($order['initial_balance']) ? (float)$order['initial_balance'] : 0.0;

            $formData = array();
            if (isset($order['form_data']) && $order['form_data']) {
                $decoded = json_decode($order['form_data'], true);
                if (is_array($decoded)) {
                    $formData = $decoded;
                }
            }

            $providedPassword = isset($formData['password']) ? (string)$formData['password'] : '';
            $telegramBotToken = isset($formData['telegram_bot_token']) ? trim((string)$formData['telegram_bot_token']) : null;
            $telegramChatId = isset($formData['telegram_chat_id']) ? trim((string)$formData['telegram_chat_id']) : null;

            $generatedPassword = null;
            $userId = 0;

            if ($existingUser) {
                $userId = (int)$existingUser['id'];

                $updates = array();
                if ($existingUser['status'] !== 'active') {
                    $updates['status'] = 'active';
                }
                if ($telegramBotToken && empty($existingUser['telegram_bot_token'])) {
                    $updates['telegram_bot_token'] = $telegramBotToken;
                }
                if ($telegramChatId && empty($existingUser['telegram_chat_id'])) {
                    $updates['telegram_chat_id'] = $telegramChatId;
                }

                if ($updates) {
                    $sets = array();
                    $params = array('id' => $userId);
                    foreach ($updates as $column => $value) {
                        $sets[] = sprintf('%s = :%s', $column, $column);
                        $params[$column] = $value;
                    }
                    $sets[] = 'updated_at = NOW()';
                    $pdo->prepare(sprintf('UPDATE users SET %s WHERE id = :id', implode(', ', $sets)))->execute($params);
                }
            } else {
                $passwordToUse = $providedPassword !== '' ? $providedPassword : bin2hex(random_bytes(4));
                $generatedPassword = $passwordToUse;
                $userId = Auth::createUser(
                    $order['name'],
                    $order['email'],
                    $passwordToUse,
                    'reseller',
                    $initialCredit,
                    array(
                        'status' => 'active',
                        'telegram_bot_token' => $telegramBotToken,
                        'telegram_chat_id' => $telegramChatId,
                    )
                );
            }

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

            $mailOrder = $order;
            if (!isset($mailOrder['price']) && isset($mailOrder['total_amount'])) {
                $mailOrder['price'] = $mailOrder['total_amount'];
            }
            $mailOrder['email'] = $order['email'];

            $user = Auth::findUser($userId);
            if ($user) {
                $passwordForMessage = $generatedPassword !== null ? $generatedPassword : false;
                ResellerNotifier::sendOnboarding($user, $mailOrder, $passwordForMessage);
            }

            Telegram::notify(sprintf(
                "Yeni teslimat tamamlandı!\nBayi: %s\nPaket: %s\nTutar: %s",
                $order['name'],
                $order['package_name'],
                Helpers::formatCurrency((float)$order['price'], 'USD')
            ));

            return [
                'user_id' => $userId,
                'password' => $generatedPassword,
            ];
        } catch (\Throwable $exception) {
            $orderId = isset($order['id']) ? (int)$order['id'] : 0;
            error_log(sprintf('Package fulfillment failed for order #%d: %s', $orderId, $exception->getMessage()));

            throw new RuntimeException('Bayi hesabı oluşturulurken bir hata oluştu. Ayrıntılar error.log dosyasına kaydedildi.');
        }
    }

    /**
     * @param int $orderId
     * @return array|null
     */
    public static function loadOrder($orderId)
    {
        $pdo = Database::connection();
        $query = 'SELECT po.*, p.name AS package_name, p.initial_balance, p.price FROM package_orders po INNER JOIN packages p ON po.package_id = p.id WHERE po.id = :id';
        $stmt = null;

        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute(['id' => $orderId]);
        } catch (PDOException $exception) {
            if (self::missingInitialBalanceColumn($exception)) {
                $fallback = 'SELECT po.*, p.name AS package_name, p.price FROM package_orders po INNER JOIN packages p ON po.package_id = p.id WHERE po.id = :id';
                $stmt = $pdo->prepare($fallback);
                $stmt->execute(['id' => $orderId]);
            } else {
                throw $exception;
            }
        }

        $order = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$order) {
            return null;
        }

        if (!isset($order['initial_balance'])) {
            $order['initial_balance'] = 0.0;
        }
        $order['price'] = isset($order['total_amount']) ? (float)$order['total_amount'] : (float)$order['price'];

        return $order;
    }

    /**
     * @param int   $orderId
     * @param array $order
     * @return void
     */
    public static function markCompleted($orderId, array $order, $adminNote = null)
    {
        $pdo = Database::connection();
        $note = $adminNote;

        if ($note === null && array_key_exists('admin_note', $order)) {
            $note = $order['admin_note'];
        }

        if ($note !== null) {
            $note = trim((string)$note);
            if ($note === '') {
                $note = null;
            }
        }

        $pdo->prepare('UPDATE package_orders SET status = :status, admin_note = :admin_note, updated_at = NOW() WHERE id = :id')
            ->execute([
                'status' => 'completed',
                'admin_note' => $note,
                'id' => $orderId,
            ]);
    }

    /**
     * @param PDOException $exception
     * @return bool
     */
    private static function missingInitialBalanceColumn(PDOException $exception)
    {
        if ($exception->getCode() === '42S22') {
            return true;
        }

        $message = $exception->getMessage();

        return stripos($message, 'initial_balance') !== false && stripos($message, 'column') !== false;
    }
}
