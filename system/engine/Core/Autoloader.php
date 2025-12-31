<?php
namespace Engine\Core;

/**
 * Class Autoloader
 * 
 * Simple PSR-4 compliant autoloader.
 * Register namespaces and their base directories to autoload classes.
 */
class Autoloader
{
    /**
     * @var array<string, string> Map of namespace prefixes to base directories.
     */
    protected array $prefixes = [];

    /**
     * Add a namespace prefix and its base directory.
     * 
     * @param string $prefix Namespace prefix (e.g., "App\\")
     * @param string $baseDir Base directory path
     * @return void
     */
    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->prefixes[$prefix] = $baseDir;
    }

    /**
     * Register the autoloader with SPL.
     * 
     * @return void
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Loads the class file for a given class name.
     * 
     * @param string $class Fully qualified class name
     * @return void
     */
    public function loadClass(string $class): void
    {
        $class = ltrim($class, '\\');
        foreach ($this->prefixes as $prefix => $baseDir) {
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                continue;
            }
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
}
