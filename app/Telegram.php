<?php

namespace App;

class Telegram
{
    /**
     * @param string $message
     * @return void
     */
    public static function notify($message)
    {
        $botToken = defined('TELEGRAM_BOT_TOKEN') && TELEGRAM_BOT_TOKEN !== ''
            ? TELEGRAM_BOT_TOKEN
            : Settings::get('telegram_bot_token');
        $chatId = defined('TELEGRAM_CHAT_ID') && TELEGRAM_CHAT_ID !== ''
            ? TELEGRAM_CHAT_ID
            : Settings::get('telegram_chat_id');

        if (empty($botToken) || empty($chatId)) {
            return;
        }

        if (!is_string($message)) {
            $message = print_r($message, true);
        }

        $message = trim($message);
        if ($message === '') {
            return;
        }

        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', $botToken);
        $payload = http_build_query([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                ]);
                curl_exec($ch);
                curl_close($ch);
                return;
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        @file_get_contents($url, false, $context);
    }
}
