<?php

namespace App;

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
     * @return void
     */
    public static function boot()
    {
        if (self::$locale) {
            return;
        }

        $default = self::defaultLocale();
        $locale = isset($_SESSION['locale']) ? strtolower((string)$_SESSION['locale']) : $default;

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
        return $locale === 'en' ? 'en' : 'tr';
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
        return array('tr', 'en');
    }

    /**
     * @return string
     */
    public static function defaultLocale()
    {
        if (defined('DEFAULT_LANGUAGE')) {
            $value = strtolower((string)DEFAULT_LANGUAGE);
            if (in_array($value, self::availableLocales(), true)) {
                return $value;
            }
        }

        return 'tr';
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

        $path = __DIR__ . '/../lang/' . $locale . '.php';
        if (file_exists($path)) {
            /** @var array<string,string> $translations */
            $translations = include $path;
            if (is_array($translations)) {
                self::$cache[$locale] = $translations;
                return $translations;
            }
        }

        self::$cache[$locale] = array();
        return self::$cache[$locale];
    }

    /**
     * @param string $buffer
     * @return string
     */
    public static function filterOutput($buffer)
    {
        $locale = self::locale();

        if ($locale === self::defaultLocale()) {
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
}
