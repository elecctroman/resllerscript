<?php

namespace App;

use App\Mailer\SmtpTransport;

class Mailer
{
    /**
     * @param string               $to
     * @param string               $subject
     * @param string               $message
     * @param array<string,mixed>  $options
     * @return bool
     */
    public static function send($to, $subject, $message, array $options = array())
    {
        $options = array_merge(
            array(
                'is_html' => false,
                'alt_body' => null,
            ),
            $options
        );

        $isHtml = !empty($options['is_html']);
        $altBody = isset($options['alt_body']) ? $options['alt_body'] : null;

        $fromAddress = Settings::get('mail_from_address', 'no-reply@example.com');
        $fromName = Settings::get('mail_from_name', Helpers::siteName());
        $replyTo = Settings::get('mail_reply_to');
        $footer = Settings::get('mail_footer');

        if (!$isHtml && $footer) {
            $message .= "\n\n" . $footer;
        }

        $prepared = self::prepareMessage($message, $isHtml, $altBody);

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
                    $transport->send(
                        $fromAddress,
                        $fromName,
                        $to,
                        $subject,
                        $prepared['body'],
                        $replyTo,
                        array('headers' => $prepared['headers'])
                    );

                    return true;
                } catch (\Throwable $exception) {
                    error_log('SMTP gönderimi başarısız: ' . $exception->getMessage());
                }
            }
        }

        if (function_exists('mail')) {
            $headerLines = self::buildHeaderLines($fromName, $fromAddress, $replyTo, $prepared['headers']);
            $headerString = implode("\r\n", $headerLines);

            try {
                $result = @call_user_func('\\mail', $to, $subject, $prepared['body'], $headerString);
                if ($result) {
                    return true;
                }
            } catch (\Throwable $exception) {
                error_log('PHP mail() fallback failed: ' . $exception->getMessage());
            }
        }

        error_log('PHP mail() fonksiyonu bu sunucuda kullanılamıyor veya başarısız oldu.');

        return false;
    }

    /**
     * @param string      $body
     * @param bool        $isHtml
     * @param string|null $altBody
     * @return array{body:string,headers:array<int,string>}
     */
    private static function prepareMessage($body, $isHtml, $altBody = null)
    {
        if ($isHtml) {
            $htmlBody = self::normalizeLineEndings($body);
            $textBody = $altBody !== null && $altBody !== '' ? $altBody : self::htmlToText($body);
            $textBody = self::normalizeLineEndings($textBody);

            $boundary = '=_Reseller_' . bin2hex(random_bytes(12));

            $parts = array();
            $parts[] = '--' . $boundary;
            $parts[] = 'Content-Type: text/plain; charset=UTF-8';
            $parts[] = 'Content-Transfer-Encoding: 8bit';
            $parts[] = '';
            $parts[] = $textBody;
            $parts[] = '--' . $boundary;
            $parts[] = 'Content-Type: text/html; charset=UTF-8';
            $parts[] = 'Content-Transfer-Encoding: quoted-printable';
            $parts[] = '';
            $parts[] = self::encodeQuotedPrintable($htmlBody);
            $parts[] = '--' . $boundary . '--';
            $parts[] = '';

            return array(
                'body' => implode("\r\n", $parts),
                'headers' => array(
                    'MIME-Version: 1.0',
                    'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                ),
            );
        }

        return array(
            'body' => self::normalizeLineEndings($body),
            'headers' => array(
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ),
        );
    }

    /**
     * @param string               $fromName
     * @param string               $fromAddress
     * @param string|null          $replyTo
     * @param array<int,string>    $additional
     * @return array<int,string>
     */
    private static function buildHeaderLines($fromName, $fromAddress, $replyTo, array $additional)
    {
        $headers = array();
        $headers[] = sprintf('From: %s <%s>', $fromName, $fromAddress);
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        foreach ($additional as $header) {
            if (stripos($header, 'X-Mailer:') === 0) {
                continue;
            }
            $headers[] = $header;
        }

        $headers[] = 'X-Mailer: ResellerPanel';

        return $headers;
    }

    /**
     * @param string $text
     * @return string
     */
    private static function normalizeLineEndings($text)
    {
        $text = str_replace(array("\r\n", "\r"), "\n", $text);

        return str_replace("\n", "\r\n", $text);
    }

    /**
     * @param string $html
     * @return string
     */
    private static function htmlToText($html)
    {
        $html = preg_replace('/<\\/(p|div|h[1-6]|li)>/i', "\0\n", $html);
        $html = preg_replace('/<(br\s*\\/?)>/i', "\n", $html);

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text);
    }

    /**
     * @param string $body
     * @return string
     */
    private static function encodeQuotedPrintable($body)
    {
        if (function_exists('quoted_printable_encode')) {
            return quoted_printable_encode($body);
        }

        return preg_replace_callback(
            '/[=\\x80-\\xFF]/',
            function ($matches) {
                return '=' . strtoupper(bin2hex($matches[0]));
            },
            $body
        );
    }
}
