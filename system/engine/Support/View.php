<?php
namespace Engine\Support;

/**
 * View Engine
 *
 * Handles loading and rendering of PHP view templates.
 */
class View
{
    /**
     * @var string Base directory for views
     */
    protected string $basePath;

    /**
     * Create a new View instance.
     *
     * @param string $basePath Absolute path to views directory
     */
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    /**
     * Render a view file.
     *
     * @param string $name View name (dot notation supported, e.g., 'auth.login')
     * @param array $data Data to extract into the view scope
     * @return string Rendered HTML
     */
    public function render(string $name, array $data = []): string
    {
        $file = $this->basePath . DIRECTORY_SEPARATOR . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $name) . '.php';
        if (!is_file($file)) {
            return '';
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean() ?: '';
    }
}

