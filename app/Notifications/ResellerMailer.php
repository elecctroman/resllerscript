<?php

namespace App\Notifications;

use App\Helpers;
use App\Mailer;
use App\Settings;

class ResellerMailer
{
    private const GLOBAL_KEYS = array(
        'order_completed' => 'mail_notify_order_completed',
        'balance_approved' => 'mail_notify_balance_approved',
        'support_replied' => 'mail_notify_support_replied',
    );

    private const USER_KEYS = array(
        'order_completed' => 'notify_order_completed',
        'balance_approved' => 'notify_balance_approved',
        'support_replied' => 'notify_support_replied',
    );

    /**
     * @param array      $order
     * @param string|null $password
     * @return bool
     */
    public static function sendOnboarding(array $order, $password = null)
    {
        $loginUrl = Helpers::url('', true);
        $title = 'Bayilik Hesabınız Hazır';
        $intro = 'Bayilik paketiniz başarıyla aktif edildi. Aşağıdaki bilgilerle panele giriş yaparak işlemlerinize başlayabilirsiniz.';

        $items = array(
            array('label' => 'Panel Adresi', 'value' => $loginUrl),
            array('label' => 'Kullanıcı Adı', 'value' => isset($order['email']) ? (string)$order['email'] : ''),
        );

        if ($password) {
            $items[] = array('label' => 'Geçici Şifre', 'value' => $password);
        } else {
            $items[] = array('label' => 'Şifre', 'value' => 'Mevcut şifreniz ile giriş yapabilirsiniz.');
        }

        if (isset($order['package_name'])) {
            $items[] = array('label' => 'Satın Alınan Paket', 'value' => (string)$order['package_name']);
        }

        if (isset($order['price'])) {
            $items[] = array('label' => 'Toplam Tutar', 'value' => Helpers::formatCurrency((float)$order['price'], 'USD'));
        }

        $cta = array(
            'label' => 'Panele Giriş Yap',
            'url' => $loginUrl,
        );

        $html = EmailTemplate::render($title, $intro, $items, $cta);
        $plain = EmailTemplate::renderPlain($title, $intro, $items, $cta);

        return Mailer::send($order['email'], $title, $html, array('is_html' => true, 'alt_body' => $plain));
    }

    /**
     * @param array $order
     * @param array $user
     * @return bool
     */
    public static function sendOrderCompleted(array $order, array $user)
    {
        if (!self::allowed('order_completed', $user)) {
            return false;
        }

        $title = 'Siparişiniz Tamamlandı';
        $intro = sprintf('Merhaba %s, siparişini verdiğiniz ürün başarıyla teslim edildi.', isset($user['name']) ? $user['name'] : '');

        $items = array(
            array('label' => 'Sipariş No', 'value' => isset($order['id']) ? '#' . $order['id'] : '-'),
        );

        if (isset($order['product_name'])) {
            $items[] = array('label' => 'Ürün', 'value' => (string)$order['product_name']);
        }
        if (isset($order['quantity'])) {
            $items[] = array('label' => 'Adet', 'value' => (string)$order['quantity']);
        }
        if (isset($order['price'])) {
            $items[] = array('label' => 'Tutar', 'value' => Helpers::formatCurrency((float)$order['price'], 'USD'));
        }

        $cta = array(
            'label' => 'Sipariş Geçmişini Görüntüle',
            'url' => Helpers::url('orders.php', true),
        );

        $html = EmailTemplate::render($title, $intro, $items, $cta);
        $plain = EmailTemplate::renderPlain($title, $intro, $items, $cta);

        return Mailer::send($user['email'], $title, $html, array('is_html' => true, 'alt_body' => $plain));
    }

    /**
     * @param array  $user
     * @param array  $request
     * @param string $adminNote
     * @return bool
     */
    public static function sendBalanceApproved(array $user, array $request, $adminNote = '')
    {
        if (!self::allowed('balance_approved', $user)) {
            return false;
        }

        $title = 'Bakiye Yüklemeniz Onaylandı';
        $intro = 'Bakiye talebiniz başarıyla onaylandı. Özet bilgileri aşağıda bulabilirsiniz.';

        $items = array(
            array('label' => 'Tutar', 'value' => Helpers::formatCurrency((float)$request['amount'], 'USD')),
        );

        if (isset($request['payment_method'])) {
            $items[] = array('label' => 'Ödeme Yöntemi', 'value' => (string)$request['payment_method']);
        }
        if (!empty($request['reference'])) {
            $items[] = array('label' => 'Referans', 'value' => (string)$request['reference']);
        }
        if ($adminNote) {
            $items[] = array('label' => 'Yönetici Notu', 'value' => $adminNote);
        }

        $cta = array(
            'label' => 'Bakiye Hareketlerini Gör',
            'url' => Helpers::url('balance.php', true),
        );

        $html = EmailTemplate::render($title, $intro, $items, $cta);
        $plain = EmailTemplate::renderPlain($title, $intro, $items, $cta);

        return Mailer::send($user['email'], $title, $html, array('is_html' => true, 'alt_body' => $plain));
    }

    /**
     * @param array  $user
     * @param array  $ticket
     * @param string $replyMessage
     * @return bool
     */
    public static function sendSupportReply(array $user, array $ticket, $replyMessage)
    {
        if (!self::allowed('support_replied', $user)) {
            return false;
        }

        $title = 'Destek Talebiniz Yanıtlandı';
        $subjectLine = isset($ticket['subject']) ? (string)$ticket['subject'] : 'Destek Talebi';
        $intro = sprintf('Merhaba %s, "%s" konulu destek talebinize yeni bir yanıt var.', isset($user['name']) ? $user['name'] : '', $subjectLine);

        $items = array(
            array('label' => 'Konu', 'value' => $subjectLine),
            array('label' => 'Yanıt', 'value' => $replyMessage, 'is_html' => false),
        );

        $cta = array(
            'label' => 'Talebi Görüntüle',
            'url' => Helpers::url('support.php', true),
        );

        $html = EmailTemplate::render($title, $intro, $items, $cta);
        $plain = EmailTemplate::renderPlain($title, $intro, $items, $cta);

        return Mailer::send($user['email'], $title, $html, array('is_html' => true, 'alt_body' => $plain));
    }

    /**
     * @param string $type
     * @param array  $user
     * @return bool
     */
    private static function allowed($type, array $user = array())
    {
        if (!isset(self::GLOBAL_KEYS[$type])) {
            return true;
        }

        $globalKey = self::GLOBAL_KEYS[$type];
        $globalSetting = Settings::get($globalKey);
        if ($globalSetting === '0') {
            return false;
        }

        if (!isset(self::USER_KEYS[$type])) {
            return true;
        }

        if (!PreferenceManager::ensureUserColumns()) {
            return true;
        }

        $userKey = self::USER_KEYS[$type];
        if (isset($user[$userKey]) && (string)$user[$userKey] === '0') {
            return false;
        }

        return true;
    }
}
