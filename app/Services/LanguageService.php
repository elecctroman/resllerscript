<?php

namespace App\Services;

use App\Database;
use App\Lang;
use PDO;
use PDOException;
use ReflectionClass;

class LanguageService
{
    /**
     * @var array<int,array<string,mixed>>|null
     */
    private static $languagesCache = null;

    /**
     * @var array<string,array<string,string>>
     */
    private static $catalogCache = array();

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
            $resultLanguages = $pdo->query("SHOW TABLES LIKE 'languages'");
            $resultTranslations = $pdo->query("SHOW TABLES LIKE 'language_translations'");
        } catch (PDOException $exception) {
            return false;
        }

        return $resultLanguages && $resultLanguages->fetchColumn() !== false
            && $resultTranslations && $resultTranslations->fetchColumn() !== false;
    }

    /**
     * @param bool $includeInactive
     * @return array<int,array<string,mixed>>
     */
    public static function languages($includeInactive = true)
    {
        if (self::$languagesCache !== null) {
            if ($includeInactive) {
                return self::$languagesCache;
            }

            return array_values(array_filter(self::$languagesCache, static function ($language) {
                return isset($language['is_active']) && (int) $language['is_active'] === 1;
            }));
        }

        if (!self::isReady()) {
            $fallback = array();
            foreach (Lang::availableLocales() as $code) {
                $fallback[] = array(
                    'code' => $code,
                    'name' => strtoupper($code),
                    'native_name' => strtoupper($code),
                    'is_default' => $code === Lang::defaultLocale() ? 1 : 0,
                    'is_active' => 1,
                );
            }

            self::$languagesCache = $fallback;

            return $includeInactive ? $fallback : array_values(array_filter($fallback, static function ($language) {
                return isset($language['is_active']) && (int) $language['is_active'] === 1;
            }));
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->query('SELECT id, code, name, native_name, is_default, is_active, created_at, updated_at FROM languages ORDER BY is_default DESC, name ASC');
        } catch (PDOException $exception) {
            error_log('[LanguageService] languages sorgusu başarısız: ' . $exception->getMessage());
            self::$languagesCache = array();

            return array();
        }

        $languages = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $languages[] = $row;
        }

        self::$languagesCache = $languages;

        if ($includeInactive) {
            return $languages;
        }

        return array_values(array_filter($languages, static function ($language) {
            return isset($language['is_active']) && (int) $language['is_active'] === 1;
        }));
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function find($code)
    {
        $code = strtolower((string) $code);
        if ($code === '') {
            return null;
        }

        foreach (self::languages(true) as $language) {
            if (isset($language['code']) && strtolower((string) $language['code']) === $code) {
                return $language;
            }
        }

        return null;
    }

    /**
     * @return string
     */
    public static function defaultCode()
    {
        $languages = self::languages(true);

        foreach ($languages as $language) {
            if (isset($language['is_default']) && (int) $language['is_default'] === 1) {
                return strtolower((string) $language['code']);
            }
        }

        return Lang::defaultLocale();
    }

    /**
     * @param string $code
     * @param string $name
     * @param string $nativeName
     * @param bool   $isActive
     * @param bool   $isDefault
     * @return bool
     */
    public static function create($code, $name, $nativeName, $isActive = true, $isDefault = false)
    {
        if (!self::isReady()) {
            return false;
        }

        $code = strtolower(trim((string) $code));
        if ($code === '') {
            return false;
        }

        try {
            $pdo = Database::connection();

            $stmt = $pdo->prepare('INSERT INTO languages (code, name, native_name, is_default, is_active, created_at) VALUES (:code, :name, :native_name, :is_default, :is_active, NOW())');
            $stmt->execute(array(
                'code' => $code,
                'name' => $name !== '' ? $name : strtoupper($code),
                'native_name' => $nativeName !== '' ? $nativeName : strtoupper($code),
                'is_default' => $isDefault ? 1 : 0,
                'is_active' => $isActive ? 1 : 0,
            ));
        } catch (PDOException $exception) {
            error_log('[LanguageService] create başarısız: ' . $exception->getMessage());
            return false;
        }

        if ($isDefault) {
            self::setDefault($code);
        }

        self::flushCaches($code);

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

        $code = strtolower(trim((string) $code));
        if ($code === '') {
            return false;
        }

        $fields = array();
        $params = array('code' => $code);

        if (isset($attributes['name'])) {
            $fields[] = 'name = :name';
            $params['name'] = (string) $attributes['name'];
        }

        if (isset($attributes['native_name'])) {
            $fields[] = 'native_name = :native_name';
            $params['native_name'] = (string) $attributes['native_name'];
        }

        if (isset($attributes['is_active'])) {
            $fields[] = 'is_active = :is_active';
            $params['is_active'] = (int) $attributes['is_active'] ? 1 : 0;
        }

        if (!$fields) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('UPDATE languages SET ' . implode(', ', $fields) . ' WHERE code = :code');
            $stmt->execute($params);
        } catch (PDOException $exception) {
            error_log('[LanguageService] update başarısız: ' . $exception->getMessage());
            return false;
        }

        self::flushCaches($code);

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

        $code = strtolower(trim((string) $code));
        if ($code === '') {
            return false;
        }

        try {
            $pdo = Database::connection();
            $pdo->beginTransaction();
            $pdo->exec('UPDATE languages SET is_default = 0');
            $stmt = $pdo->prepare('UPDATE languages SET is_default = 1, is_active = 1 WHERE code = :code');
            $stmt->execute(array('code' => $code));
            $pdo->commit();
        } catch (PDOException $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[LanguageService] setDefault başarısız: ' . $exception->getMessage());
            return false;
        }

        self::flushCaches($code);

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
     * @param string $locale
     * @return array<string,string>
     */
    public static function catalog($locale)
    {
        $locale = strtolower((string) $locale);
        if ($locale === '') {
            $locale = Lang::defaultLocale();
        }

        if (isset(self::$catalogCache[$locale])) {
            return self::$catalogCache[$locale];
        }

        $catalog = self::readLocaleFile($locale);
        $overrides = self::fetchOverrides($locale);

        if ($overrides) {
            foreach ($overrides as $key => $value) {
                $catalog[$key] = $value;
            }
        }

        self::$catalogCache[$locale] = $catalog;

        return $catalog;
    }

    /**
     * @return array<int,string>
     */
    public static function keys()
    {
        $keys = array();

        foreach (self::catalog(self::defaultCode()) as $key => $value) {
            $keys[] = (string) $key;
        }

        foreach (self::languages(true) as $language) {
            if (!isset($language['code'])) {
                continue;
            }
            $code = (string) $language['code'];
            foreach (self::fetchOverrides($code) as $key => $value) {
                $keys[] = (string) $key;
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @param string $locale
     * @param string $key
     * @param string $value
     * @return bool
     */
    public static function saveTranslation($locale, $key, $value)
    {
        if (!self::isReady()) {
            return false;
        }

        $locale = strtolower(trim((string) $locale));
        $key = (string) $key;

        if ($locale === '' || $key === '') {
            return false;
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('INSERT INTO language_translations (language_code, translation_key, translation_value, updated_at, created_at) VALUES (:code, :t_key, :t_value, NOW(), NOW())
                ON DUPLICATE KEY UPDATE translation_value = VALUES(translation_value), updated_at = NOW()');
            $stmt->execute(array(
                'code' => $locale,
                't_key' => $key,
                't_value' => $value,
            ));
        } catch (PDOException $exception) {
            error_log('[LanguageService] saveTranslation başarısız: ' . $exception->getMessage());
            return false;
        }

        self::flushCaches($locale);
        self::persistLocaleFile($locale);

        return true;
    }

    /**
     * @param string $locale
     * @param string $key
     * @return bool
     */
    public static function deleteTranslation($locale, $key)
    {
        if (!self::isReady()) {
            return false;
        }

        $locale = strtolower(trim((string) $locale));
        $key = (string) $key;

        if ($locale === '' || $key === '') {
            return false;
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('DELETE FROM language_translations WHERE language_code = :code AND translation_key = :t_key');
            $stmt->execute(array('code' => $locale, 't_key' => $key));
        } catch (PDOException $exception) {
            error_log('[LanguageService] deleteTranslation başarısız: ' . $exception->getMessage());
            return false;
        }

        self::flushCaches($locale);
        self::persistLocaleFile($locale);

        return true;
    }

    /**
     * @param string $locale
     * @return void
     */
    public static function persistLocaleFile($locale)
    {
        $catalog = self::catalog($locale);
        $path = __DIR__ . '/../../lang/' . $locale . '.json';

        $encoded = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }

        @file_put_contents($path, $encoded . PHP_EOL, LOCK_EX);
    }

    /**
     * @return void
     */
    public static function flushCaches($locale = null)
    {
        self::$languagesCache = null;
        self::$catalogCache = array();

        if ($locale !== null) {
            $locale = strtolower((string) $locale);
            unset($_SESSION['locale_cache_' . $locale]);
        }

        // Force Lang to reload on next request.
        try {
            $reflection = new ReflectionClass(Lang::class);
            if ($reflection->hasProperty('cache')) {
                $property = $reflection->getProperty('cache');
                $property->setAccessible(true);
                $property->setValue(null, array());
            }
            if ($reflection->hasProperty('availableLocales')) {
                $availableProperty = $reflection->getProperty('availableLocales');
                $availableProperty->setAccessible(true);
                $availableProperty->setValue(null, null);
            }
            if ($reflection->hasProperty('defaultLocale')) {
                $defaultProperty = $reflection->getProperty('defaultLocale');
                $defaultProperty->setAccessible(true);
                $defaultProperty->setValue(null, null);
            }
        } catch (\Throwable $reflectionException) {
            // ignore cache flush errors
        }
    }

    /**
     * @param string $locale
     * @return array<string,string>
     */
    private static function readLocaleFile($locale)
    {
        $pathJson = __DIR__ . '/../../lang/' . $locale . '.json';
        $pathPhp = __DIR__ . '/../../lang/' . $locale . '.php';

        $catalog = array();

        if (is_file($pathJson)) {
            $json = @file_get_contents($pathJson);
            if (is_string($json) && $json !== '') {
                $data = json_decode($json, true);
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        if (is_string($key) && is_string($value)) {
                            $catalog[$key] = $value;
                        }
                    }
                }
            }
        } elseif (is_file($pathPhp)) {
            /** @var mixed $legacy */
            $legacy = include $pathPhp;
            if (is_array($legacy)) {
                foreach ($legacy as $key => $value) {
                    if (is_string($key) && is_string($value)) {
                        $catalog[$key] = $value;
                    }
                }
            }
        }

        return $catalog;
    }

    /**
     * @param string $locale
     * @return array<string,string>
     */
    private static function fetchOverrides($locale)
    {
        if (!self::isReady()) {
            return array();
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT translation_key, translation_value FROM language_translations WHERE language_code = :code');
            $stmt->execute(array('code' => $locale));
        } catch (PDOException $exception) {
            error_log('[LanguageService] override sorgusu başarısız: ' . $exception->getMessage());
            return array();
        }

        $rows = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row['translation_key'], $row['translation_value'])) {
                continue;
            }

            $key = (string) $row['translation_key'];
            if ($key === '') {
                continue;
            }

            $rows[$key] = (string) $row['translation_value'];
        }

        return $rows;
    }
}
