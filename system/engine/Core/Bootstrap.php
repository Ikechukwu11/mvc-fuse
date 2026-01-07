<?php
namespace Engine\Core;

/**
 * Class Bootstrap
 *
 * Bootstraps the application by loading the autoloader, environment variables,
 * configuration, and setting up the environment.
 */
class Bootstrap
{
    /**
     * Initialize the application.
     *
     * - Loads Autoloader
     * - Loads Helpers
     * - Sets up directory paths
     * - Registers Namespaces
     * - Loads .env
     * - Loads Config
     * - Starts Session
     *
     * @return array Application context (paths, config, loader)
     */
    public static function init(): array
    {
        require __DIR__ . DIRECTORY_SEPARATOR . 'Autoloader.php';
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'helpers.php';
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fuse' . DIRECTORY_SEPARATOR . 'helpers.php';

        $root = dirname(__DIR__, 3);
        $appPath = $root . DIRECTORY_SEPARATOR . 'app';
        $enginePath = $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'engine';
        $configPath = $root . DIRECTORY_SEPARATOR . 'config';
        $routesPath = $root . DIRECTORY_SEPARATOR . 'routes';
        $storagePath = $root . DIRECTORY_SEPARATOR . 'storage';
        $viewsPath = $root . DIRECTORY_SEPARATOR . 'views';

        if (!is_dir($storagePath)) {
            @mkdir($storagePath . DIRECTORY_SEPARATOR . 'logs', 0777, true);
            @mkdir($storagePath . DIRECTORY_SEPARATOR . 'cache', 0777, true);
        }
        if (!is_dir($viewsPath)) {
            @mkdir($viewsPath, 0777, true);
        }

        $loader = new Autoloader();
        // Register Engine namespace to system/engine (not system/engine/Core)
        // so Engine\Core\Bootstrap maps to system/engine/Core/Bootstrap.php
        $loader->addNamespace('Engine', $enginePath);
        $loader->addNamespace('App', $appPath);
        $loader->register();

        // Load Environment
        if (file_exists($root . DIRECTORY_SEPARATOR . '.env')) {
            Env::load($root . DIRECTORY_SEPARATOR . '.env');
        }

        $config = [
            'app' => self::loadConfig($configPath . DIRECTORY_SEPARATOR . 'app.php', [
                'name' => 'MVC',
                'env' => 'local',
                'debug' => true,
                'url' => 'http://localhost:8000',
                'timezone' => 'UTC'
            ]),
            'database' => self::loadConfig($configPath . DIRECTORY_SEPARATOR . 'database.php', [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => '',
                'username' => '',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]),
        ];

        date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

        // Set session path: prefer environment override (e.g., Mobile persisted storage), fallback to project storage
        $sessionPath = getenv('SESSION_SAVE_PATH') ?: ($storagePath . '/sessions');
        if (!is_dir($sessionPath)) {
            @mkdir($sessionPath, 0777, true);
        }
        if (is_writable($sessionPath)) {
            session_save_path($sessionPath);
        }

        // Avoid sessions for static asset requests to prevent extra session files
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isAsset = (bool) preg_match('#\.(css|js|png|jpg|jpeg|webp|gif|svg|ico|woff|woff2|ttf|eot|otf|json|pdf|txt|xml)$#i', $uri);
        if (!$isAsset && session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Initialize Config
        Config::load($config);

        return [
            'paths' => compact('root', 'appPath', 'enginePath', 'configPath', 'routesPath', 'storagePath', 'viewsPath'),
            'config' => $config,
            'loader' => $loader,
        ];
    }

    /**
     * Load a configuration file with default fallbacks.
     *
     * @param string $file Path to config file
     * @param array $defaults Default values
     * @return array Merged configuration
     */
    protected static function loadConfig(string $file, array $defaults): array
    {
        if (is_file($file)) {
            $conf = require $file;
            if (is_array($conf)) {
                return array_replace($defaults, $conf);
            }
        }
        return $defaults;
    }
}
