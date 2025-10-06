<?php

namespace App\Services;

use App\Database;
use PDO;
use PDOException;

class CurrencyService
{
    /**
     * @var array<int,array<string,mixed>>|null
     */
    private static $currencyCache = null;

    /**
     * @return bool
     */
    public static function isReady()
    {
        try {
            $pdo = Database::connection();
        } catch (PDOException $exception) {
            return false;
        }

        try {
            $result = $pdo->query("SHOW TABLES LIKE 'currencies'");
        } catch (PDOException $exception) {
            return false;
        }

        return $result && $result->fetchColumn() !== false;
    }

    /**
     * @param bool $includeInactive
     * @return array<int,array<string,mixed>>
     */
    public static function currencies($includeInactive = true)
    {
        if (self::$currencyCache !== null) {
            if ($includeInactive) {
                return self::$currencyCache;
            }

            return array_values(array_filter(self::$currencyCache, static function ($currency) {
                return isset($currency['is_active']) && (int) $currency['is_active'] === 1;
            }));
        }

        if (!self::isReady()) {
            return array();
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->query('SELECT id, code, name, symbol, rate, decimals, is_default, is_active, auto_update, last_rate_at, created_at, updated_at FROM currencies ORDER BY is_default DESC, name ASC');
        } catch (PDOException $exception) {
            error_log('[CurrencyService] currencies sorgusu başarısız: ' . $exception->getMessage());
            self::$currencyCache = array();

            return array();
        }

        $rows = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($row['code'])) {
                $row['code'] = strtoupper((string) $row['code']);
            }
            $rows[] = $row;
        }

        self::$currencyCache = $rows;

        if ($includeInactive) {
            return $rows;
        }

        return array_values(array_filter($rows, static function ($currency) {
            return isset($currency['is_active']) && (int) $currency['is_active'] === 1;
        }));
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function currenciesByCode()
    {
        $map = array();
        foreach (self::currencies(true) as $currency) {
            if (!isset($currency['code'])) {
                continue;
            }
            $map[strtoupper((string) $currency['code'])] = $currency;
        }

        return $map;
    }

    /**
     * @return string
     */
    public static function defaultCurrency()
    {
        foreach (self::currencies(true) as $currency) {
            if (isset($currency['is_default']) && (int) $currency['is_default'] === 1) {
                return isset($currency['code']) ? strtoupper((string) $currency['code']) : 'USD';
            }
        }

        return 'USD';
    }

    /**
     * @param string $code
     * @return array<string,mixed>|null
     */
    public static function find($code)
    {
        $code = strtoupper((string) $code);
        $currencies = self::currenciesByCode();

        return isset($currencies[$code]) ? $currencies[$code] : null;
    }

    /**
     * @param string $code
     * @param string $name
     * @param string $symbol
     * @param float  $rate
     * @param int    $decimals
     * @param bool   $isActive
     * @param bool   $isDefault
     * @param bool   $autoUpdate
     * @return bool
     */
    public static function create($code, $name, $symbol, $rate = 1.0, $decimals = 2, $isActive = true, $isDefault = false, $autoUpdate = false)
    {
        if (!self::isReady()) {
            return false;
        }

        $code = strtoupper(trim((string) $code));
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            return false;
        }

        $rate = (float) $rate;
        if ($rate <= 0) {
            $rate = 1.0;
        }

        $decimals = (int) $decimals;
        if ($decimals < 0) {
            $decimals = 0;
        } elseif ($decimals > 6) {
            $decimals = 6;
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('INSERT INTO currencies (code, name, symbol, rate, decimals, is_default, is_active, auto_update, created_at) VALUES (:code, :name, :symbol, :rate, :decimals, :is_default, :is_active, :auto_update, NOW())');
            $stmt->execute(array(
                'code' => $code,
                'name' => $name !== '' ? $name : $code,
                'symbol' => $symbol !== '' ? $symbol : self::defaultSymbol($code),
                'rate' => $rate,
                'decimals' => $decimals,
                'is_default' => $isDefault ? 1 : 0,
                'is_active' => $isActive ? 1 : 0,
                'auto_update' => $autoUpdate ? 1 : 0,
            ));
        } catch (PDOException $exception) {
            error_log('[CurrencyService] create başarısız: ' . $exception->getMessage());
            return false;
        }

        if ($isDefault) {
            self::setDefault($code);
        }

        self::flush();

        return true;
    }

    /**
     * @param string $code
     * @param array<string,mixed> $attributes
     * @return bool
     */
    public static function update($code, array $attributes)
    {
        if (!self::isReady()) {
            return false;
        }

        $code = strtoupper(trim((string) $code));
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            return false;
        }

        $fields = array();
        $params = array('code' => $code);

        if (isset($attributes['name'])) {
            $fields[] = 'name = :name';
            $params['name'] = (string) $attributes['name'];
        }

        if (isset($attributes['symbol'])) {
            $fields[] = 'symbol = :symbol';
            $params['symbol'] = (string) $attributes['symbol'];
        }

        if (isset($attributes['rate'])) {
            $fields[] = 'rate = :rate';
            $params['rate'] = max(0.000001, (float) $attributes['rate']);
            $fields[] = 'last_rate_at = NOW()';
        }

        if (isset($attributes['decimals'])) {
            $fields[] = 'decimals = :decimals';
            $decimals = (int) $attributes['decimals'];
            if ($decimals < 0) {
                $decimals = 0;
            } elseif ($decimals > 6) {
                $decimals = 6;
            }
            $params['decimals'] = $decimals;
        }

        if (isset($attributes['is_active'])) {
            $fields[] = 'is_active = :is_active';
            $params['is_active'] = (int) $attributes['is_active'] ? 1 : 0;
        }

        if (isset($attributes['auto_update'])) {
            $fields[] = 'auto_update = :auto_update';
            $params['auto_update'] = (int) $attributes['auto_update'] ? 1 : 0;
        }

        if (!$fields) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('UPDATE currencies SET ' . implode(', ', $fields) . ' WHERE code = :code');
            $stmt->execute($params);
        } catch (PDOException $exception) {
            error_log('[CurrencyService] update başarısız: ' . $exception->getMessage());
            return false;
        }

        self::flush();

        return true;
    }

    /**
     * @param string $code
     * @return bool
     */
    public static function setDefault($code)
    {
        if (!self::isReady()) {
            return false;
        }

        $code = strtoupper(trim((string) $code));
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            return false;
        }

        try {
            $pdo = Database::connection();
            $pdo->beginTransaction();
            $pdo->exec('UPDATE currencies SET is_default = 0');
            $stmt = $pdo->prepare('UPDATE currencies SET is_default = 1, is_active = 1, rate = 1, last_rate_at = NOW() WHERE code = :code');
            $stmt->execute(array('code' => $code));
            $pdo->commit();
        } catch (PDOException $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[CurrencyService] setDefault başarısız: ' . $exception->getMessage());
            return false;
        }

        self::flush();

        return true;
    }

    /**
     * @param string $code
     * @param bool   $active
     * @return bool
     */
    public static function setActive($code, $active)
    {
        return self::update($code, array('is_active' => $active ? 1 : 0));
    }

    /**
     * @param float  $amount
     * @param string $from
     * @param string $to
     * @return float
     */
    public static function convert($amount, $from, $to)
    {
        $from = strtoupper((string) $from);
        $to = strtoupper((string) $to);

        if ($from === $to) {
            return (float) $amount;
        }

        $currencies = self::currenciesByCode();
        $default = self::defaultCurrency();

        if (!isset($currencies[$from])) {
            $from = $default;
        }

        if (!isset($currencies[$to])) {
            $to = $default;
        }

        $amount = (float) $amount;

        if ($from !== $default) {
            $rateFrom = isset($currencies[$from]['rate']) ? (float) $currencies[$from]['rate'] : 1.0;
            if ($rateFrom <= 0) {
                $rateFrom = 1.0;
            }
            $amount = $amount * $rateFrom;
        }

        if ($to === $default) {
            return $amount;
        }

        $rateTo = isset($currencies[$to]['rate']) ? (float) $currencies[$to]['rate'] : 1.0;
        if ($rateTo <= 0) {
            $rateTo = 1.0;
        }

        return $amount / $rateTo;
    }

    /**
     * @param float  $amount
     * @param string $currency
     * @return string
     */
    public static function format($amount, $currency)
    {
        $currency = strtoupper((string) $currency);
        $currencies = self::currenciesByCode();

        $symbol = self::defaultSymbol($currency);
        $decimals = 2;

        if (isset($currencies[$currency])) {
            $symbol = isset($currencies[$currency]['symbol']) && $currencies[$currency]['symbol'] !== ''
                ? (string) $currencies[$currency]['symbol']
                : self::defaultSymbol($currency);
            if (isset($currencies[$currency]['decimals'])) {
                $decimals = max(0, min(6, (int) $currencies[$currency]['decimals']));
            }
        }

        $amount = (float) $amount;
        $formatted = number_format($amount, $decimals, '.', ',');

        return $symbol . $formatted;
    }

    /**
     * @param string $currency
     * @return string
     */
    public static function symbol($currency)
    {
        $currency = strtoupper((string) $currency);
        $currencies = self::currenciesByCode();

        if (isset($currencies[$currency]) && isset($currencies[$currency]['symbol']) && $currencies[$currency]['symbol'] !== '') {
            return (string) $currencies[$currency]['symbol'];
        }

        return self::defaultSymbol($currency);
    }

    /**
     * @param string $code
     * @return float|null
     */
    public static function refreshRate($code)
    {
        $code = strtoupper((string) $code);
        $default = self::defaultCurrency();

        if ($code === $default) {
            self::update($code, array('rate' => 1.0));

            return 1.0;
        }

        $rate = self::fetchExternalRate($code, $default);
        if ($rate !== null && $rate > 0) {
            self::update($code, array('rate' => $rate));
        }

        self::flush();

        return $rate;
    }

    /**
     * @return void
     */
    public static function flush()
    {
        self::$currencyCache = null;
    }

    /**
     * @param string $code
     * @return string
     */
    private static function defaultSymbol($code)
    {
        switch (strtoupper($code)) {
            case 'TRY':
                return '₺';
            case 'EUR':
                return '€';
            case 'GBP':
                return '£';
            case 'USD':
            default:
                return '$';
        }
    }

    /**
     * @param string $from
     * @param string $to
     * @return float|null
     */
    private static function fetchExternalRate($from, $to)
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        $endpoints = array(
            array(
                'type' => 'convert',
                'url' => 'https://api.exchangerate.host/convert?from=' . urlencode($from) . '&to=' . urlencode($to),
            ),
            array(
                'type' => 'latest',
                'url' => 'https://open.er-api.com/v6/latest/' . urlencode($from),
            ),
            array(
                'type' => 'latest',
                'url' => 'https://api.exchangerate-api.com/v4/latest/' . urlencode($from),
            ),
        );

        foreach ($endpoints as $endpoint) {
            $response = self::httpGet($endpoint['url']);
            if (!$response) {
                continue;
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                continue;
            }

            if ($endpoint['type'] === 'convert') {
                if (isset($data['info']['rate'])) {
                    $rate = (float) $data['info']['rate'];
                    if ($rate > 0) {
                        return $rate;
                    }
                }

                if (isset($data['result'])) {
                    $rate = (float) $data['result'];
                    if ($rate > 0) {
                        return $rate;
                    }
                }
            } else {
                if (isset($data['rates'][$to])) {
                    $rate = (float) $data['rates'][$to];
                    if ($rate > 0) {
                        return $rate;
                    }
                }
            }
        }

        return null;
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
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ResellerPanelBot/1.0)');
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
                    'header' => "User-Agent: Mozilla/5.0 (compatible; ResellerPanelBot/1.0)\r\n",
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
