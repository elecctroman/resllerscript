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
        if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID') || empty(TELEGRAM_BOT_TOKEN) || empty(TELEGRAM_CHAT_ID)) {
            return;
        }

        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', TELEGRAM_BOT_TOKEN);
        $payload = http_build_query([
            'chat_id' => TELEGRAM_CHAT_ID,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);

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
