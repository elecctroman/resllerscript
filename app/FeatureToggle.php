<?php

namespace App;

class FeatureToggle
{
    /**
     * @var array<string,bool>
     */
    private static $cache = array();

    /**
     * @var array<string,bool>
     */
    private static $defaults = array(
        'products' => true,
        'orders' => true,
        'packages' => true,
        'support' => true,
        'balance' => true,
        'api' => true,
        'premium_modules' => true,
    );

    /**
     * Determine if a feature is enabled.
     *
     * @param string $feature
     * @return bool
     */
    public static function isEnabled($feature)
    {
        $key = strtolower(trim((string)$feature));

        if ($key === '') {
            return true;
        }

        if (!array_key_exists($key, self::$cache)) {
            $stored = Settings::get('feature_' . $key);
            if ($stored === null) {
                self::$cache[$key] = isset(self::$defaults[$key]) ? (bool)self::$defaults[$key] : true;
            } else {
                self::$cache[$key] = $stored !== '0';
            }
        }

        return self::$cache[$key];
    }

    /**
     * Persist a feature flag.
     *
     * @param string $feature
     * @param bool   $enabled
     * @return void
     */
    public static function setEnabled($feature, $enabled)
    {
        $key = strtolower(trim((string)$feature));
        if ($key === '') {
            return;
        }

        Settings::set('feature_' . $key, $enabled ? '1' : '0');
        self::$cache[$key] = (bool)$enabled;
    }

    /**
     * @return array<string,bool>
     */
    public static function all()
    {
        $features = array();
        $keys = array_unique(array_merge(array_keys(self::$defaults), array('products', 'orders', 'packages', 'support', 'balance', 'api')));

        foreach ($keys as $key) {
            $features[$key] = self::isEnabled($key);
        }

        return $features;
    }
}
