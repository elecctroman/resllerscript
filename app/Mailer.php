<?php

namespace App;

class Mailer
{
    /**
     * @param string $to
     * @param string $subject
     * @param string $message
     * @return void
     */
    public static function send($to, $subject, $message)
    {
        $fromAddress = Settings::get('mail_from_address', 'no-reply@example.com');
        $fromName = Settings::get('mail_from_name', 'Bayi Yönetim Sistemi');
        $replyTo = Settings::get('mail_reply_to');
        $footer = Settings::get('mail_footer');

        if ($footer) {
            $message .= "\n\n" . $footer;
        }

        $headers = sprintf('From: %s <%s>', $fromName, $fromAddress) . "\r\n";
        if ($replyTo) {
            $headers .= 'Reply-To: ' . $replyTo . "\r\n";
        }
        $headers .= 'X-Mailer: PHP/' . phpversion();

        @mail($to, $subject, $message, $headers);
    }
}
