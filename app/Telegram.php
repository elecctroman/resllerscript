<?php

namespace App;

class Telegram
{
    /**
     * @param string $message
     * @param array  $options
     * @return void
     */
    public static function notify($message, array $options = array())
    {
        if (!is_string($message)) {
            $message = print_r($message, true);
        }

        $message = trim($message);
        if ($message === '') {
            return;
        }

        $botToken = isset($options['bot_token']) && $options['bot_token'] !== ''
            ? $options['bot_token']
            : (defined('TELEGRAM_BOT_TOKEN') && TELEGRAM_BOT_TOKEN !== ''
                ? TELEGRAM_BOT_TOKEN
                : Settings::get('telegram_bot_token'));

        $chatId = isset($options['chat_id']) && $options['chat_id'] !== ''
            ? $options['chat_id']
            : (defined('TELEGRAM_CHAT_ID') && TELEGRAM_CHAT_ID !== ''
                ? TELEGRAM_CHAT_ID
                : Settings::get('telegram_chat_id'));

        if ($botToken === null || $botToken === '' || $chatId === null || $chatId === '') {
            return;
        }

        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', $botToken);
        $payload = http_build_query(array(
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => isset($options['parse_mode']) ? $options['parse_mode'] : 'HTML',
            'disable_web_page_preview' => isset($options['disable_preview']) ? (int)$options['disable_preview'] : 1,
        ));

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, array(
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                ));
                curl_exec($ch);
                curl_close($ch);
                return;
            }
        }

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ),
        ));

        @file_get_contents($url, false, $context);
    }
}
