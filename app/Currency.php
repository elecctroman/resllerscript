<?php

namespace App;

class Currency
{
    /**
     * @var array<string,float>
     */
    private static $rateCache = array();

    /**
     * @param float $amount
     * @param string $from
     * @param string $to
     * @return float
     */
    public static function convert($amount, $from = 'USD', $to = 'USD')
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return (float)$amount;
        }

        $rate = self::getRate($from, $to);
        return (float)$amount * $rate;
    }

    /**
     * @param float $amount
     * @param string $currency
     * @return string
     */
    public static function format($amount, $currency = 'USD')
    {
        $currency = strtoupper($currency);
        $symbol = self::symbol($currency);
        $formatted = number_format((float)$amount, 2, ',', '.');

        if ($currency === 'USD') {
            $formatted = number_format((float)$amount, 2, '.', ',');
        }

        return $symbol . $formatted;
    }

    /**
     * @param string $currency
     * @return string
     */
    public static function symbol($currency)
    {
        $currency = strtoupper($currency);
        switch ($currency) {
            case 'TRY':
                return '₺';
            case 'USD':
            default:
                return '$';
        }
    }

    /**
     * @param string $from
     * @param string $to
     * @return float
     */
    public static function getRate($from, $to)
    {
        $key = $from . '_' . $to;

        if (isset(self::$rateCache[$key])) {
            return self::$rateCache[$key];
        }

        $storedRate = Settings::get('currency_rate_' . $key);
        $storedTimestamp = Settings::get('currency_rate_' . $key . '_updated');

        if ($storedRate !== null && $storedTimestamp !== null) {
            $age = time() - (int)$storedTimestamp;
            if ($age < 1800) {
                $rate = (float)$storedRate;
                self::$rateCache[$key] = $rate;
                return $rate;
            }
        }

        $rate = self::fetchRate($from, $to);
        if ($rate <= 0) {
            if ($storedRate !== null) {
                $rate = (float)$storedRate;
            } else {
                $rate = 1.0;
            }
        } else {
            Settings::set('currency_rate_' . $key, (string)$rate);
            Settings::set('currency_rate_' . $key . '_updated', (string)time());
        }

        self::$rateCache[$key] = $rate;
        return $rate;
    }

    /**
     * @param string $from
     * @param string $to
     * @return float
     */
    private static function fetchRate($from, $to)
    {
        $endpoint = 'https://api.exchangerate.host/convert?from=' . urlencode($from) . '&to=' . urlencode($to);

        $response = self::httpGet($endpoint);
        if (!$response) {
            return 0.0;
        }

        $data = json_decode($response, true);
        if (is_array($data) && isset($data['info']) && isset($data['info']['rate'])) {
            return (float)$data['info']['rate'];
        }

        if (is_array($data) && isset($data['result'])) {
            return (float)$data['result'];
        }

        return 0.0;
    }

    /**
     * @param string $url
     * @return string|null
     */
    private static function httpGet($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result !== false) {
                return $result;
            }
        }

        if (ini_get('allow_url_fopen')) {
            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => 10,
                ),
            ));
            $result = @file_get_contents($url, false, $context);
            if ($result !== false) {
                return $result;
            }
        }

        return null;
    }
}
