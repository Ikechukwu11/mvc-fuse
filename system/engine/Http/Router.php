<?php
namespace Engine\Http;

use Engine\Core\Config;

/**
 * HTTP Router
 *
 * Handles route registration and matching against the current request.
 * Supports path parameters and middleware.
 */
class Router
{
    private array $routes = [];
    private array $params = [];
    private string $prefix = '';

    /**
     * Set a prefix for subsequent routes.
     *
     * @param string $prefix
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * Add a route with the specified method, path, and handler.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path URL path pattern (e.g., /users/{id})
     * @param callable|array $handler Controller array or closure
     * @param array $middleware Optional middleware stack for this route
     */
    public function add(string $method, string $path, callable|array $handler, array $middleware = []): void
    {
        $path = $this->prefix . $path;
        // Ensure single slash if prefix ends with / and path starts with /
        $path = str_replace('//', '/', $path);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /**
     * Match a request method and URI to a registered route.
     *
     * @param string $method
     * @param string $uri
     * @return array|null Returns route array with params if matched, null otherwise
     */
    public function match(string $method, string $uri): ?array
    {
        $method = strtoupper($method);
        $uri = parse_url($uri, PHP_URL_PATH);

        // Handle Base Path
        $basePath = Config::get('app.base_path', '');
        if (!empty($basePath) && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
            if ($uri === '' || !str_starts_with($uri, '/')) {
                $uri = '/' . $uri;
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = "#^" . $pattern . "$#";

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $route['params'] = $params;
                return $route;
            }
        }

        return null;
    }

    /**
     * Get all registered routes.
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
