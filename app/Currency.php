<?php

namespace App;

use App\Services\CurrencyService;

class Currency
{
    /**
     * @param float $amount
     * @param string $from
     * @param string $to
     * @return float
     */
    public static function convert($amount, $from = 'USD', $to = 'USD')
    {
        return (float) CurrencyService::convert((float) $amount, $from, $to);
    }

    /**
     * @param float $amount
     * @param string $currency
     * @return string
     */
    public static function format($amount, $currency = 'USD')
    {
        return CurrencyService::format((float) $amount, $currency);
    }

    /**
     * @param string $currency
     * @return string
     */
    public static function symbol($currency)
    {
        return CurrencyService::symbol($currency);
    }

    /**
     * @param string $from
     * @param string $to
     * @return float
     */
    public static function getRate($from, $to)
    {
        $from = strtoupper((string) $from);
        $to = strtoupper((string) $to);

        if ($from === $to) {
            return 1.0;
        }

        return CurrencyService::convert(1.0, $from, $to);
    }

    /**
     * Force refresh of a currency pair and persist the latest value.
     *
     * @param string $from
     * @param string $to
     * @return float|null
     */
    public static function refreshRate($from, $to)
    {
        $from = strtoupper((string) $from);
        $to = strtoupper((string) $to);

        $default = CurrencyService::defaultCurrency();

        if ($to !== $default) {
            CurrencyService::refreshRate($from);
            CurrencyService::refreshRate($to);

            return CurrencyService::convert(1.0, $from, $to);
        }

        return CurrencyService::refreshRate($from);
    }
}
