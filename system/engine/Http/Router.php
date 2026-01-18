<?php

namespace Engine\Http;

use Engine\Core\Config;

/**
 * Class Router
 *
 * A modern, fluent HTTP router for custom PHP MVC.
 *
 * Features:
 *  - Route registration for GET, POST, PUT, PATCH, DELETE
 *  - Inline route names or fluent ->name() syntax
 *  - Middleware support (per route or group)
 *  - Route parameter constraints (regex)
 *  - Optional parameter defaults
 *  - Route prefix and route grouping
 *  - Current route helpers
 *  - Fallback route support
 *
 * Breaking changes:
 *  - HTTP verb methods now return `static` (required for fluent chaining)
 *  - `add()` now accepts optional `$name` argument
 *
 *  ✅ Usage Examples
 *  1. Inline route name
 *  $router->get('/profile', [ProfileController::class, 'index'], [new AuthMiddleware()], 'profile.index');
 *
 *  2. Fluent ->name()
 *  $router->get('/users', [UserController::class, 'index'])->name('users.index');
 *
 *  3. Middleware & prefix chaining
 *  $router->prefix('/admin')
 *       ->middleware([new AuthMiddleware()])
 *       ->get('/dashboard', [AdminController::class, 'index'])
 *       ->name('admin.dashboard');
 *
 *  4. Parameter constraints
 *  $router->get('/users/{id}', [UserController::class, 'show'])
 *       ->where('id', '\d+')
 *       ->name('users.show');
 *
 *  5. Parameter defaults
 *  $router->get('/posts/{id}/{slug?}', [PostController::class, 'show'])
 *       ->defaults(['slug' => 'default-slug'])
 *       ->name('posts.show');
 *
 *  6. Route groups
 *  $router->group(['prefix'=>'/admin', 'middleware'=>[new AuthMiddleware()]], function($router) {
 *  $router->get('/users', [AdminUserController::class, 'index'])->name('admin.users');
 *  });
 *
 *   7. Fallback (404)
 *   $router->fallback([ErrorController::class, 'notFound']);
 */


class Router
{
    private array $routes = [];
    private array $namedRoutes = [];
    private ?array $currentRoute = null;
    private ?int $lastRouteIndex = null;

    private array $currentConstraints = [];
    private array $currentDefaults = [];
    private array $groupStack = [];
    private string $manualPrefix = '';


    /** @var callable|array|null */
    private $fallbackHandler = null;

    /* -------------------------------------------------------------
     | Route Modifiers
     |-------------------------------------------------------------*/

    public function prefix(string $prefix): static
    {
        $this->manualPrefix = $prefix;
        return $this;
    }

    public function setPrefix(string $prefix): static
    {
        $this->manualPrefix = $prefix;
        return $this;
    }

    public function where(string $param, string $regex): static
    {
        $this->currentConstraints[$param] = $regex;
        return $this;
    }

    public function defaults(array $defaults): static
    {
        $this->currentDefaults = $defaults;
        return $this;
    }

    public function middleware(array $middleware): static
    {
        if ($this->lastRouteIndex === null) {
            throw new \RuntimeException('middleware() must be called after defining a route.');
        }

        $this->routes[$this->lastRouteIndex]['middleware'] =
            array_merge($this->routes[$this->lastRouteIndex]['middleware'], $middleware);

        return $this;
    }

    public function name(string $name): static
    {
        if ($this->lastRouteIndex === null) {
            throw new \RuntimeException('name() must be called after defining a route.');
        }

        $this->routes[$this->lastRouteIndex]['name'] = $name;
        $this->namedRoutes[$name] = $this->routes[$this->lastRouteIndex];
        return $this;
    }

    /* -------------------------------------------------------------
     | HTTP VERBS
     |-------------------------------------------------------------*/

    public function get(string $path, callable|array $handler, array $middleware = [], ?string $name = null): static
    {
        $this->add('GET', $path, $handler, $middleware, $name);
        return $this;
    }

    public function post(string $path, callable|array $handler, array $middleware = [], ?string $name = null): static
    {
        $this->add('POST', $path, $handler, $middleware, $name);
        return $this;
    }

    public function put(string $path, callable|array $handler, array $middleware = [], ?string $name = null): static
    {
        $this->add('PUT', $path, $handler, $middleware, $name);
        return $this;
    }

    public function patch(string $path, callable|array $handler, array $middleware = [], ?string $name = null): static
    {
        $this->add('PATCH', $path, $handler, $middleware, $name);
        return $this;
    }

    public function delete(string $path, callable|array $handler, array $middleware = [], ?string $name = null): static
    {
        $this->add('DELETE', $path, $handler, $middleware, $name);
        return $this;
    }

    /* -------------------------------------------------------------
     | Core Add
     |-------------------------------------------------------------*/

    private function add(string $method, string $path, callable|array $handler, array $middleware = [], ?string $name = null): void
    {
        $prefix = $this->manualPrefix;
        $groupMiddleware = [];

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
        }

        $fullPath = str_replace('//', '/', $prefix . $path);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
            'name' => $name,
            'constraints' => $this->currentConstraints,
            'defaults' => $this->currentDefaults,
        ];

        $this->lastRouteIndex = array_key_last($this->routes);

        if ($name) {
            $this->namedRoutes[$name] = $this->routes[$this->lastRouteIndex];
        }

        // Reset temp modifiers but keep prefixes intact
        $this->currentConstraints = [];
        $this->currentDefaults = [];
        $this->manualPrefix = '';
    }


    /* -------------------------------------------------------------
     | Groups
     |-------------------------------------------------------------*/

    public function group(array $options, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix' => $options['prefix'] ?? '',
            'middleware' => $options['middleware'] ?? [],
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    /* -------------------------------------------------------------
     | Matching
     |-------------------------------------------------------------*/

    public function match(string $method, string $uri): ?array
    {
        $uri = parse_url($uri, PHP_URL_PATH);

        $basePath = Config::get('app.base_path', '');
        if ($basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) continue;

            $pattern = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($route) {
                return $route['constraints'][$m[1]] ?? '([^/]+)';
            }, $route['path']);

            if (preg_match("#^$pattern$#", $uri, $matches)) {
                $route['params'] = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->currentRoute = $route;
                return $route;
            }
        }

        return null;
    }

    /* -------------------------------------------------------------
     | Helpers
     |-------------------------------------------------------------*/

    public function fallback($handler): static
    {
        $this->fallbackHandler = $handler;
        return $this;
    }

    public function getFallback()
    {
        return $this->fallbackHandler;
    }

    public function current(): ?array
    {
        return $this->currentRoute;
    }

    public function currentRouteName(): ?string
    {
        return $this->currentRoute['name'] ?? null;
    }

    public function currentRoutePath(): ?string
    {
        return $this->currentRoute['path'] ?? null;
    }
}
