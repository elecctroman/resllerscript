<?php

namespace App;

use PDO;

/** @noinspection PhpUnused */
use App\Auth;

class Lang
{
    /**
     * @var string|null
     */
    private static $locale;

    /**
     * @var array<string,array<string,string>>
     */
    private static $cache = array();

    /**
     * @var array<int,string>|null
     */
    private static $availableLocales = null;

    /**
     * @var string|null
     */
    private static $defaultLocale = null;

    /**
     * @var bool|null
     */
    private static $languagesTableAvailable = null;

    /**
     * @var bool|null
     */
    private static $translationsTableAvailable = null;

    /**
     * @return void
     */
    public static function boot()
    {
        if (self::$locale) {
            return;
        }

        $default = self::defaultLocale();
        $sessionLocale = isset($_SESSION['locale']) ? strtolower((string)$_SESSION['locale']) : '';

        if ($sessionLocale === '') {
            $currentUser = Auth::currentUser();
            if ($currentUser && isset($currentUser['locale']) && $currentUser['locale'] !== null) {
                $sessionLocale = strtolower((string)$currentUser['locale']);
            }
        }

        $locale = $sessionLocale !== '' ? $sessionLocale : $default;

        if (!in_array($locale, self::availableLocales(), true)) {
            $locale = $default;
        }

        self::$locale = $locale;
    }

    /**
     * @param string $locale
     * @return void
     */
    public static function setLocale($locale)
    {
        $locale = strtolower((string)$locale);
        if (!in_array($locale, self::availableLocales(), true)) {
            $locale = self::defaultLocale();
        }

        $_SESSION['locale'] = $locale;
        self::$locale = $locale;
        self::load($locale);
    }

    /**
     * @return string
     */
    public static function locale()
    {
        if (!self::$locale) {
            self::boot();
        }

        return self::$locale ? self::$locale : self::defaultLocale();
    }

    /**
     * @return string
     */
    public static function htmlLocale()
    {
        $locale = self::locale();
        return str_replace('_', '-', strtolower($locale));
    }

    /**
     * @param string $key
     * @param string|null $default
     * @return string
     */
    public static function get($key, $default = null)
    {
        $locale = self::locale();
        $translations = self::load($locale);

        if (isset($translations[$key])) {
            return $translations[$key];
        }

        if ($locale !== self::defaultLocale()) {
            $fallbackTranslations = self::load(self::defaultLocale());
            if (isset($fallbackTranslations[$key])) {
                return $fallbackTranslations[$key];
            }
        }

        if ($default !== null) {
            return $default;
        }

        return $key;
    }

    /**
     * @param string $text
     * @param string|null $key
     * @return string
     */
    public static function line($text, $key = null)
    {
        $locale = self::locale();
        if ($locale === self::defaultLocale()) {
            return $text;
        }

        if ($key !== null) {
            $translated = self::get($key, null);
            if ($translated !== null && $translated !== $key) {
                return $translated;
            }
        }

        $translations = self::load($locale);
        if (isset($translations[$text])) {
            return $translations[$text];
        }

        if ($locale !== self::defaultLocale()) {
            $fallbackTranslations = self::load(self::defaultLocale());
            if (isset($fallbackTranslations[$text])) {
                return $fallbackTranslations[$text];
            }
        }

        return $text;
    }

    /**
     * @return array<int,string>
     */
    public static function availableLocales()
    {
        if (self::$availableLocales !== null) {
            return self::$availableLocales;
        }

        $locales = array();

        $fromDatabase = self::fetchLocalesFromDatabase();
        if ($fromDatabase) {
            $locales = $fromDatabase;
        } else {
            foreach (self::scanLocaleFiles() as $locale) {
                $locales[] = $locale;
            }
        }

        if (!$locales) {
            $locales = array('en');
        }

        self::$availableLocales = $locales;

        return self::$availableLocales;
    }

    /**
     * @return string
     */
    public static function defaultLocale()
    {
        if (self::$defaultLocale !== null) {
            return self::$defaultLocale;
        }

        $preferred = null;

        $defaultFromDatabase = self::fetchDefaultLocaleFromDatabase();
        if ($defaultFromDatabase !== null) {
            $preferred = $defaultFromDatabase;
        }

        if ($preferred === null && class_exists(Settings::class)) {
            $stored = Settings::get('platform_default_locale');
            if ($stored) {
                $preferred = strtolower((string) $stored);
            }
        }

        if ($preferred === null && defined('DEFAULT_LANGUAGE')) {
            $preferred = strtolower((string) DEFAULT_LANGUAGE);
        }

        $available = self::availableLocales();

        if ($preferred !== null && in_array($preferred, $available, true)) {
            self::$defaultLocale = $preferred;

            return self::$defaultLocale;
        }

        $fallback = in_array('en', $available, true) ? 'en' : (isset($available[0]) ? $available[0] : 'en');
        self::$defaultLocale = $fallback;

        return self::$defaultLocale;
    }

    /**
     * @param string $locale
     * @return array<string,string>
     */
    private static function load($locale)
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $translations = array();

        $jsonPath = __DIR__ . '/../lang/' . $locale . '.json';
        $phpPath = __DIR__ . '/../lang/' . $locale . '.php';

        if (is_file($jsonPath)) {
            $json = @file_get_contents($jsonPath);
            if (is_string($json) && $json !== '') {
                $data = json_decode($json, true);
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        if (is_string($key) && is_string($value)) {
                            $translations[$key] = $value;
                        }
                    }
                }
            }
        } elseif (is_file($phpPath)) {
            /** @var mixed $phpTranslations */
            $phpTranslations = include $phpPath;
            if (is_array($phpTranslations)) {
                foreach ($phpTranslations as $key => $value) {
                    if (is_string($key) && is_string($value)) {
                        $translations[$key] = $value;
                    }
                }
            }
        }

        $overrides = self::loadOverrides($locale);
        if ($overrides) {
            foreach ($overrides as $key => $value) {
                $translations[$key] = $value;
            }
        }

        self::$cache[$locale] = $translations;

        return self::$cache[$locale];
    }

    /**
     * @param string $buffer
     * @return string
     */
    public static function filterOutput($buffer)
    {
        $locale = self::locale();
        $default = self::defaultLocale();

        if ($locale === $default) {
            return $buffer;
        }

        $translations = self::load($locale);

        if (!$translations) {
            return $buffer;
        }

        foreach ($translations as $source => $translated) {
            if (!is_string($source) || !is_string($translated)) {
                continue;
            }

            if ($source === '' || $translated === '') {
                continue;
            }

            $buffer = str_replace($source, $translated, $buffer);
        }

        return $buffer;
    }

    /**
     * @param string $locale
     * @return array<string,string>
     */
    private static function loadOverrides($locale)
    {
        if (!self::translationsTableAvailable()) {
            return array();
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return array();
        }

        try {
            $stmt = $pdo->prepare('SELECT translation_key, translation_value FROM language_translations WHERE language_code = :code');
            $stmt->execute(array('code' => $locale));
        } catch (\Throwable $exception) {
            return array();
        }

        $overrides = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row['translation_key'], $row['translation_value'])) {
                continue;
            }

            $key = (string) $row['translation_key'];
            $value = (string) $row['translation_value'];

            if ($key === '') {
                continue;
            }

            $overrides[$key] = $value;
        }

        return $overrides;
    }

    /**
     * @return array<int,string>
     */
    private static function fetchLocalesFromDatabase()
    {
        if (!self::languagesTableAvailable()) {
            return array();
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return array();
        }

        try {
            $stmt = $pdo->query("SELECT code FROM languages WHERE is_active = 1 ORDER BY is_default DESC, name ASC");
        } catch (\Throwable $exception) {
            return array();
        }

        $locales = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row['code'])) {
                continue;
            }

            $code = strtolower((string) $row['code']);
            if ($code === '') {
                continue;
            }

            $locales[] = $code;
        }

        return $locales;
    }

    /**
     * @return string|null
     */
    private static function fetchDefaultLocaleFromDatabase()
    {
        if (!self::languagesTableAvailable()) {
            return null;
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return null;
        }

        try {
            $stmt = $pdo->query("SELECT code FROM languages WHERE is_default = 1 LIMIT 1");
        } catch (\Throwable $exception) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !isset($row['code'])) {
            return null;
        }

        $code = strtolower((string) $row['code']);

        return $code !== '' ? $code : null;
    }

    /**
     * @return array<int,string>
     */
    private static function scanLocaleFiles()
    {
        $path = __DIR__ . '/../lang';
        if (!is_dir($path)) {
            return array();
        }

        $locales = array();
        $items = scandir($path);
        if (!is_array($items)) {
            return array();
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (substr($item, -5) === '.json') {
                $locales[] = strtolower(substr($item, 0, -5));
            } elseif (substr($item, -4) === '.php') {
                $locales[] = strtolower(substr($item, 0, -4));
            }
        }

        return array_values(array_unique($locales));
    }

    /**
     * @return bool
     */
    private static function languagesTableAvailable()
    {
        if (self::$languagesTableAvailable !== null) {
            return self::$languagesTableAvailable;
        }

        try {
            $pdo = Database::connection();
            $result = $pdo->query("SHOW TABLES LIKE 'languages'");
            self::$languagesTableAvailable = $result && $result->fetchColumn() !== false;
        } catch (\Throwable $exception) {
            self::$languagesTableAvailable = false;
        }

        return self::$languagesTableAvailable;
    }

    /**
     * @return bool
     */
    private static function translationsTableAvailable()
    {
        if (self::$translationsTableAvailable !== null) {
            return self::$translationsTableAvailable;
        }

        try {
            $pdo = Database::connection();
            $result = $pdo->query("SHOW TABLES LIKE 'language_translations'");
            self::$translationsTableAvailable = $result && $result->fetchColumn() !== false;
        } catch (\Throwable $exception) {
            self::$translationsTableAvailable = false;
        }

        return self::$translationsTableAvailable;
    }
}
