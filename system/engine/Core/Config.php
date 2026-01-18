<?php

namespace Engine\Core;

/**
 * Class Config
 *
 * Global configuration manager.
 * Stores configuration items in a static array and provides dot-notation access.
 */
class Config
{
    /**
     * @var array Configuration store
     */
    private static array $items = [];

    /**
     * Load configuration items.
     *
     * @param array $items
     * @return void
     */
    public static function load(array $items): void
    {
        self::$items = $items;
    }

    /**
     * Get a configuration value using dot notation.
     *
     * Example: Config::get('app.name', 'My App')
     *
     * @param string $key Dot-notated key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $config = self::$items;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                return $default;
            }
            $config = $config[$k];
        }

        return $config;
    }

    /**
     * Get all configuration items.
     *
     * @return array
     */
    public static function all(): array
    {
        return self::$items;
    }
}
