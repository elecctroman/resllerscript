<?php

namespace App;

class Mailer
{
    public static function send(string $to, string $subject, string $message): void
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
