<?php

namespace App;

use App\Mailer\SmtpTransport;

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
        $fromName = Settings::get('mail_from_name', Helpers::siteName());
        $replyTo = Settings::get('mail_reply_to');
        $footer = Settings::get('mail_footer');

        if ($footer) {
            $message .= "\n\n" . $footer;
        }

        $smtpEnabled = Settings::get('smtp_enabled');
        if ($smtpEnabled === '1') {
            $host = Settings::get('smtp_host');
            $port = (int)Settings::get('smtp_port', 587);
            $encryption = Settings::get('smtp_encryption', 'tls');
            $timeout = (int)Settings::get('smtp_timeout', 15);
            $username = Settings::get('smtp_username');
            $password = Settings::get('smtp_password');

            if ($host) {
                try {
                    $transport = new SmtpTransport($host, $port > 0 ? $port : 587, $encryption ?: 'tls', $timeout > 0 ? $timeout : 15, $username, $password);
                    $transport->send($fromAddress, $fromName, $to, $subject, $message, $replyTo);
                    return;
                } catch (\Throwable $exception) {
                    error_log('SMTP gönderimi başarısız: ' . $exception->getMessage());
                }
            }
        }

        $headers = sprintf('From: %s <%s>', $fromName, $fromAddress) . "\r\n";
        if ($replyTo) {
            $headers .= 'Reply-To: ' . $replyTo . "\r\n";
        }
        $headers .= 'X-Mailer: PHP/' . phpversion();

        @mail($to, $subject, $message, $headers);
    }
}
