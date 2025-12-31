<?php

use Engine\Support\View;
use Engine\Http\Middleware\CsrfMiddleware;
use Engine\Core\Config;

/**
 * Get a configuration value.
 *
 * @param string $key Dot-notated key
 * @param mixed $default Default value
 * @return mixed
 */
function config(string $key, mixed $default = null)
{
    return Config::get($key, $default);
}

/**
 * Get an environment variable.
 *
 * @param string $key Variable name
 * @param mixed $default Default value
 * @return mixed
 */
function env(string $key, mixed $default = null)
{
    return \Engine\Core\Env::get($key, $default);
}

/**
 * Render a view template.
 *
 * @param string $name View name (e.g., 'home.index')
 * @param array $data Data to pass to the view
 * @param string|null $layout Optional layout to wrap the view
 * @return string Rendered HTML
 */
function view(string $name, array $data = [], ?string $layout = null)
{
    $base = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'views';
    $v = new View($base);
    $content = $v->render($name, $data);
    if ($layout) {
        $data['content'] = $content;
        return $v->render($layout, $data);
    }
    return $content;
}

/**
 * Escape HTML special characters.
 *
 * @param mixed $value Value to escape
 * @return string Escaped string
 */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Get the current CSRF token.
 *
 * @return string
 */
function csrf_token(): string
{
    return CsrfMiddleware::token();
}

/**
 * Get a Query Builder instance for a table.
 *
 * @param string $table Table name
 * @return \Engine\Database\QueryBuilder
 */
function qb(string $table): \Engine\Database\QueryBuilder
{
    return \Engine\Database\QueryBuilder::table($table);
}

/**
 * Get a session value.
 *
 * @param string $key Session key
 * @param mixed $default Default value
 * @return mixed
 */
function session_get(string $key, $default = null)
{
    return $_SESSION[$key] ?? $default;
}

/**
 * Set a session value.
 *
 * @param string $key Session key
 * @param mixed $value Value to store
 * @return void
 */
function session_put(string $key, $value): void
{
    $_SESSION[$key] = $value;
}

/**
 * Remove a session value.
 *
 * @param string $key Session key
 * @return void
 */
function session_forget(string $key): void
{
    unset($_SESSION[$key]);
}

/**
 * Flash a message to the session.
 *
 * @param string $key Flash key
 * @param mixed $value Value to flash
 * @return void
 */
function flash(string $key, $value): void
{
    $_SESSION['_flash'][$key] = $value;
}

/**
 * Get and clear a flashed message from the session.
 *
 * @param string $key Flash key
 * @param mixed $default Default value
 * @return mixed
 */
function flash_get(string $key, $default = null)
{
    $val = $_SESSION['_flash'][$key] ?? $default;
    if (isset($_SESSION['_flash'][$key])) {
        unset($_SESSION['_flash'][$key]);
    }
    return $val;
}

/**
 * Redirect to a given path.
 *
 * @param string $path Target path
 * @return void
 */
function redirect(string $path)
{
    header("Location: $path");
    exit;
}

/**
 * Generate a URL for an asset.
 *
 * @param string $path Asset path relative to public/
 * @return string Full URL
 */
function asset(string $path): string
{
    $url = rtrim(config('app.url'), '/');
    $base = rtrim(config('app.base_path', ''), '/');
    return $url . $base . '/' . ltrim($path, '/');
}

/**
 * Get the Storage class or a file URL.
 *
 * @param string|null $path File path for URL generation, or null for class name
 * @return string|class-string
 */
function storage(string $path = '')
{
    if (is_null($path)) {
        return \Engine\Storage\Storage::class;
    }
    return \Engine\Storage\Storage::url($path);
}

/**
 * Get the absolute path to a storage file.
 *
 * @param string $path Relative path
 * @return string Absolute path
 */
function storage_path(string $path = ''): string
{
    return \Engine\Storage\Storage::path($path);
}

if (!function_exists('dd')) {
    function dd(...$args)
    {
        $styles = [
            'pre' => 'background:#1e1e1e;color:#ecf0f1;padding:16px;border-radius:8px;font-size:14px;line-height:1.5;font-family:Menlo,Consolas,monospace;',
            'string' => 'color:#2ecc71;',
            'integer' => 'color:#3498db;',
            'double' => 'color:#9b59b6;',
            'boolean' => 'color:#e67e22;',
            'NULL' => 'color:#95a5a6;',
            'array' => 'color:#f1c40f;',
            'object' => 'color:#e74c3c;',
            'resource' => 'color:#1abc9c;',
            'default' => 'color:#bdc3c7;',
        ];

        $output = "<pre style=\"{$styles['pre']}\">";

        foreach ($args as $i => $arg) {
            $output .= "\n<span style=\"color:#e74c3c;\">--- [dd #" . ($i + 1) . "] ---</span>\n";
            $output .= parse_colored_var($arg, $styles) . "\n";
        }

        $output .= '</pre>';

        if (function_exists('wp_die')) {
            wp_die($output); // Proper WordPress termination
        } else {
            die($output);
        }
    }

    function parse_colored_var($var, $styles, $depth = 0)
    {
        $indent = str_repeat('  ', $depth);
        switch (gettype($var)) {
            case 'string':
                return "<span style=\"{$styles['string']}\">\"$var\"</span>";
            case 'integer':
                return "<span style=\"{$styles['integer']}\">$var</span>";
            case 'double':
                return "<span style=\"{$styles['double']}\">$var</span>";
            case 'boolean':
                return "<span style=\"{$styles['boolean']}\">" . ($var ? 'true' : 'false') . "</span>";
            case 'NULL':
                return "<span style=\"{$styles['NULL']}\">null</span>";
            case 'array':
                $result = "<span style=\"{$styles['array']}\">array</span> (\n";
                foreach ($var as $key => $value) {
                    $result .= $indent . "  [$key] => " . parse_colored_var($value, $styles, $depth + 1) . "\n";
                }
                return $result . $indent . ")";
            case 'object':
                $class = get_class($var);
                $result = "<span style=\"{$styles['object']}\">object($class)</span> (\n";
                foreach ((array) $var as $key => $value) {
                    $result .= $indent . "  [$key] => " . parse_colored_var($value, $styles, $depth + 1) . "\n";
                }
                return $result . $indent . ")";
            case 'resource':
                return "<span style=\"{$styles['resource']}\">resource</span>";
            default:
                return "<span style=\"{$styles['default']}\">" . htmlspecialchars(print_r($var, true)) . "</span>";
        }
    }
}

function route(string $path): string
{
    return BASE_PATH . $path;
}
