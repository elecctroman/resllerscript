<?php

namespace App\Notifications;

use App\Helpers;
use App\Telegram;

class ResellerNotifier
{
    private const USER_PREFERENCES = array(
        'order_completed' => 'notify_order_completed',
        'balance_approved' => 'notify_balance_approved',
        'support_replied' => 'notify_support_replied',
    );

    /**
     * @param array $user
     * @param array $order
     * @param bool  $passwordProvided
     * @return bool
     */
    public static function sendOnboarding(array $user, array $order, $passwordProvided = false)
    {
        $loginUrl = Helpers::url('index.php', true);
        $name = self::escape(isset($user['name']) ? $user['name'] : 'Bayi');
        $packageName = isset($order['package_name']) ? self::escape($order['package_name']) : '';
        $price = isset($order['price']) ? Helpers::formatCurrency((float)$order['price'], 'USD') : null;

        $lines = array(
            '👋 <b>Hoş geldiniz ' . $name . '!</b>',
            '🚀 Bayilik paketiniz başarıyla aktifleştirildi.',
            '',
            '🔑 <b>Giriş Bilgileriniz</b>',
            '• E-posta: <code>' . self::escape(isset($user['email']) ? $user['email'] : '') . '</code>',
        );

        if ($passwordProvided) {
            $lines[] = '• Şifre: <code>' . self::escape($passwordProvided) . '</code>';
        } else {
            $lines[] = '• Şifre: Kayıt sırasında belirlediğiniz şifreyi kullanabilirsiniz.';
        }

        if ($packageName !== '') {
            $lines[] = '';
            $lines[] = '📦 Paket: <b>' . $packageName . '</b>';
        }

        if ($price !== null) {
            $lines[] = '💳 Ödenen Tutar: <b>' . self::escape($price) . '</b>';
        }

        $lines[] = '';
        $lines[] = '➡️ <a href="' . self::escape($loginUrl) . '">Panele giriş yapmak için tıklayın</a>';

        $message = implode("\n", $lines);

        return self::send($user, $message, 'onboarding', true);
    }

    /**
     * @param array $order
     * @param array $user
     * @return bool
     */
    public static function sendOrderCompleted(array $order, array $user)
    {
        if (!self::shouldNotify($user, 'order_completed')) {
            return false;
        }

        $lines = array(
            '✅ <b>Siparişiniz tamamlandı!</b>',
        );

        if (isset($order['id'])) {
            $lines[] = '🧾 Sipariş No: <b>#' . self::escape($order['id']) . '</b>';
        }
        if (isset($order['product_name'])) {
            $lines[] = '📦 Ürün: <b>' . self::escape($order['product_name']) . '</b>';
        }
        if (isset($order['quantity'])) {
            $lines[] = '🔢 Adet: <b>' . self::escape($order['quantity']) . '</b>';
        }
        if (isset($order['price'])) {
            $lines[] = '💰 Tutar: <b>' . self::escape(Helpers::formatCurrency((float)$order['price'], 'USD')) . '</b>';
        }

        if (!empty($order['admin_note'])) {
            $lines[] = '';
            $lines[] = '📝 Yönetici Notu: ' . self::escape($order['admin_note']);
        }

        $lines[] = '';
        $lines[] = '📂 <a href="' . self::escape(Helpers::url('orders.php', true)) . '">Detayları panelden görüntüleyin</a>';

        return self::send($user, implode("\n", $lines), 'order_completed');
    }

    /**
     * @param array  $user
     * @param array  $request
     * @param string $adminNote
     * @return bool
     */
    public static function sendBalanceApproved(array $user, array $request, $adminNote = '')
    {
        if (!self::shouldNotify($user, 'balance_approved')) {
            return false;
        }

        $lines = array(
            '💳 <b>Bakiye talebiniz onaylandı!</b>',
            '✅ Tutar: <b>' . self::escape(Helpers::formatCurrency((float)$request['amount'], 'USD')) . '</b>',
        );

        if (!empty($request['payment_method'])) {
            $lines[] = '🏦 Yöntem: <b>' . self::escape($request['payment_method']) . '</b>';
        }

        if (!empty($request['reference'])) {
            $lines[] = '🔖 Referans: <code>' . self::escape($request['reference']) . '</code>';
        }

        if ($adminNote !== '') {
            $lines[] = '';
            $lines[] = '📝 Yönetici Notu: ' . self::escape($adminNote);
        }

        $lines[] = '';
        $lines[] = '📊 <a href="' . self::escape(Helpers::url('balance.php', true)) . '">Bakiye hareketlerini inceleyin</a>';

        return self::send($user, implode("\n", $lines), 'balance_approved');
    }

    /**
     * @param array  $user
     * @param array  $ticket
     * @param string $replyMessage
     * @return bool
     */
    public static function sendSupportReply(array $user, array $ticket, $replyMessage)
    {
        if (!self::shouldNotify($user, 'support_replied')) {
            return false;
        }

        $subject = isset($ticket['subject']) ? $ticket['subject'] : 'Destek Talebi';

        $lines = array(
            '💬 <b>Destek talebinize yanıt var!</b>',
            '📌 Konu: <b>' . self::escape($subject) . '</b>',
            '',
            '✉️ Yanıt: ' . self::escape($replyMessage),
            '',
            '🔍 <a href="' . self::escape(Helpers::url('support.php', true)) . '">Tüm destek geçmişinizi görüntüleyin</a>',
        );

        return self::send($user, implode("\n", $lines), 'support_replied');
    }

    /**
     * @param array $user
     * @param array $product
     * @param int   $added
     * @param int   $available
     * @return bool
     */
    public static function sendStockRestocked(array $user, array $product, int $added, int $available)
    {
        $productName = isset($product['name']) ? $product['name'] : ('Ürün #' . (isset($product['id']) ? $product['id'] : ''));
        $lines = array(
            '🔔 <b>Stok yenilendi!</b>',
            '📦 Ürün: <b>' . self::escape($productName) . '</b>',
        );

        if ($added > 0) {
            $lines[] = '➕ Yeni eklenen stok: <b>' . self::escape((string) $added) . '</b>';
        }

        $lines[] = '📊 Anlık stok: <b>' . self::escape((string) $available) . '</b>';

        if (isset($product['price'])) {
            $currency = isset($product['currency']) ? (string) $product['currency'] : 'USD';
            $lines[] = '💰 Panel fiyatı: <b>' . self::escape(Helpers::formatCurrency((float) $product['price'], $currency)) . '</b>';
        }

        $query = urlencode($productName);
        $lines[] = '';
        $lines[] = '🛒 <a href="' . self::escape(Helpers::url('products.php?q=' . $query, true)) . '">Hemen sipariş verin</a>';

        return self::send($user, implode("\n", $lines), 'stock_restock', true);
    }

    /**
     * @param array $user
     * @param float $threshold
     * @param string $deadline
     * @param float $deficit
     * @param int   $graceDays
     * @return bool
     */
    public static function sendLowBalanceWarning(array $user, $threshold, $deadline, $deficit, $graceDays)
    {
        $lines = array(
            '⚠️ <b>Düşük bakiye uyarısı</b>',
            '💼 Hesabınızın bakiyesi belirlenen minimum tutarın altına düştü.',
            '',
            '📉 Mevcut Bakiyeniz: <b>' . self::escape(Helpers::formatCurrency((float)$user['balance'])) . '</b>',
            '🎯 Minimum Bakiye: <b>' . self::escape(Helpers::formatCurrency((float)$threshold)) . '</b>',
            '➕ Yüklenmesi Gereken: <b>' . self::escape(Helpers::formatCurrency((float)$deficit)) . '</b>',
            '',
            '⏳ Pasife Alınma Süresi: <b>' . self::escape($graceDays . ' gün') . '</b>',
            '📅 Son Gün: <b>' . self::escape($deadline) . '</b>',
            '',
            '💡 Minimum tutarı yükleyerek hesabınızı aktif tutabilirsiniz.',
        );

        return self::send($user, implode("\n", $lines), 'low_balance', true);
    }

    /**
     * @param array  $user
     * @param string $message
     * @return bool
     */
    public static function sendDirect(array $user, $message)
    {
        return self::send($user, $message, 'direct', true);
    }

    /**
     * @param array  $user
     * @param string $message
     * @param string $type
     * @param bool   $force
     * @return bool
     */
    private static function send(array $user, $message, $type, $force = false)
    {
        $botToken = isset($user['telegram_bot_token']) ? trim((string)$user['telegram_bot_token']) : '';
        $chatId = isset($user['telegram_chat_id']) ? trim((string)$user['telegram_chat_id']) : '';

        if ($botToken === '' || $chatId === '') {
            return false;
        }

        if (!$force && !self::shouldNotify($user, $type)) {
            return false;
        }

        Telegram::notify($message, array(
            'bot_token' => $botToken,
            'chat_id' => $chatId,
        ));

        return true;
    }

    /**
     * @param array  $user
     * @param string $type
     * @return bool
     */
    private static function shouldNotify(array $user, $type)
    {
        if (!isset(self::USER_PREFERENCES[$type])) {
            return true;
        }

        $column = self::USER_PREFERENCES[$type];

        if (!isset($user[$column])) {
            return true;
        }

        return $user[$column] !== '0' && $user[$column] !== 0;
    }

    /**
     * @param string $value
     * @return string
     */
    private static function escape($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
