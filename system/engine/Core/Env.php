<?php

namespace Engine\Core;

/**
 * Class Env
 * 
 * Environment variable loader and accessor.
 * Reads from .env files and populates $_ENV, $_SERVER, and getenv().
 */
class Env
{
    /**
     * @var array Loaded environment variables
     */
    protected static array $vars = [];

    /**
     * Load environment variables from a file.
     * 
     * @param string $path Path to .env file
     * @return void
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);

                // Remove quotes if present
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }

                self::$vars[$key] = $value;
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    /**
     * Get an environment variable.
     * 
     * Handles type conversion for true, false, null, and empty.
     * 
     * @param string $key Variable name
     * @param mixed $default Default value
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$vars[$key] ?? $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        return $value;
    }
}
