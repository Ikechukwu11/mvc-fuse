<?php
namespace Engine\Database;

use PDO;
use PDOException;

/**
 * Database Manager
 *
 * Handles the PDO connection based on environment variables.
 * Supports SQLite, MySQL, and PostgreSQL.
 */
class DB
{
    private static ?PDO $pdo = null;

    /**
     * Get the Singleton PDO instance.
     *
     * @return PDO
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            new self();
        }
        return self::$pdo;
    }

    /**
     * Initialize the Database connection.
     *
     * Reads configuration from config/database.php and .env.
     */
    private function __construct()
    {
        $config = require dirname(__DIR__, 3) . '/config/database.php';

        // Use env vars if available, fallback to config
        $driver = env('DB_CONNECTION', $config['default'] ?? 'mysql');

        // Map 'sqlite' to config key if needed, or just use config structure
        $dbConfig = $config['connections'][$driver] ?? [];

        try {
            if ($driver === 'sqlite') {
                $path = env('DB_DATABASE', $dbConfig['database'] ?? 'storage/database.sqlite');
                if (!str_starts_with($path, '/')) {
                     $path = dirname(__DIR__, 3) . '/' . $path;
                }
                // Ensure directory exists
                $dir = dirname($path);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                if (!file_exists($path)) touch($path);

                $dsn = "sqlite:$path";
                self::$pdo = new PDO($dsn);
            } else {
                // MySQL / PgSQL
                $host = env('DB_HOST', $dbConfig['host'] ?? '127.0.0.1');
                $port = env('DB_PORT', $dbConfig['port'] ?? 3306);
                $dbname = env('DB_DATABASE', $dbConfig['database'] ?? 'mvc');
                $user = env('DB_USERNAME', $dbConfig['username'] ?? 'root');
                $pass = env('DB_PASSWORD', $dbConfig['password'] ?? '');

                $dsn = "$driver:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
                self::$pdo = new PDO($dsn, $user, $pass);
            }

            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("DB Connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get the last inserted ID.
     *
     * @return string|false
     */
    public static function lastInsertId(): string|false
    {
        return self::pdo()->lastInsertId();
    }

    /**
     * Begin a new database transaction.
     *
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        return self::pdo()->beginTransaction();
    }

    /**
     * Commit the current transaction.
     *
     * @return bool
     */
    public static function commit(): bool
    {
        return self::pdo()->commit();
    }

    /**
     * Rollback the current transaction.
     *
     * @return bool
     */
    public static function rollBack(): bool
    {
        return self::pdo()->rollBack();
    }
}

