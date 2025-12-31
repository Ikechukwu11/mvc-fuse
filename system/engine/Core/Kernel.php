<?php
namespace Engine\Core;

use Engine\Http\Router;
use Engine\Http\Request;
use Engine\Http\Response;

/**
 * HTTP Kernel
 *
 * Orchestrates the request lifecycle: loads routes, runs middleware,
 * dispatches controller actions, and sends the response.
 */
class Kernel
{
    protected Router $router;
    protected array $middleware = [];

    /**
     * Create a new Kernel instance.
     *
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;

        // Register Global Middleware
        $this->middleware = [
            \Engine\Http\Middleware\JsonBodyParser::class,
            \Engine\Http\Middleware\TrimStrings::class,
            \Engine\Http\Middleware\CsrfMiddleware::class,
        ];
    }

    /**
     * Handle the incoming HTTP request.
     *
     * @param Request $request
     */
    public function handle(Request $request)
    {
        // Load routes
        $router = $this->router;

        // Load Web Routes
        require_once dirname(__DIR__, 3) . '/routes/web.php';

        // Load API Routes with prefix
        $this->loadApiRoutes($router);

        // Match route
        $route = $this->router->match($request->method, $request->uri);

        if (!$route) {
            $this->sendResponse(404, '404 Not Found');
            return;
        }

        // Combine global and route-specific middleware
        $middlewareStack = array_merge($this->middleware, $route['middleware'] ?? []);

        // Dispatch through middleware pipeline
        $response = $this->runMiddleware($request, $middlewareStack, function ($req) use ($route) {
            return $this->dispatch($route['handler'], $route['params'] ?? [], $req);
        });

        // Send final response
        if ($response instanceof Response) {
            $response->send();
        } elseif (is_array($response)) {
            // Check if it's a standard [status, body] array
            if (isset($response[0]) && is_int($response[0])) {
                $this->sendResponse($response[0], $response[1]);
            } else {
                // Otherwise treat as JSON data
                $this->sendResponse(200, $response);
            }
        } elseif (is_string($response)) {
            $this->sendResponse(200, $response);
        }
    }

    /**
     * Load API routes with /api prefix.
     *
     * @param Router $router
     * @return void
     */
    protected function loadApiRoutes(Router $router): void
    {
        $apiFile = dirname(__DIR__, 3) . '/routes/api.php';
        if (file_exists($apiFile)) {
            // Use a temporary router to capture API routes, then prefix and merge
            // OR simply manually include and use a helper to prefix.
            // But since Router doesn't support groups yet, let's just include it
            // and let the file handle it? No, user wants automatic prefix.
            // Simple approach: Decorate or temporarily swap the 'add' method?
            // Safer: Just include the file, but wrap the $router in a proxy?

            // Let's implement a simple group simulation here for now.
            // We will manually prefix in the file for now? No, user said "api.php for api on demand"
            // and "include maybe web.php and api.php".
            // Let's modify Router to support prefixes or groups.

            // For now, let's just wrap the inclusion and manually prefix inside Router or here.
            // Actually, simplest is to add a group() method to Router.

            // BUT, to keep it simple without changing Router too much yet:
            // Let's just modify Router to allow a temporary prefix state.

            $router->setPrefix('/api');
            require $apiFile;
            $router->setPrefix(''); // Reset
        }
    }

    /**
     * Run the middleware pipeline.
     *
     * @param Request $request
     * @param array $middlewares
     * @param callable $target Final destination (controller)
     * @return mixed
     */
    protected function runMiddleware(Request $request, array $middlewares, callable $target)
    {
        $next = $target;

        foreach (array_reverse($middlewares) as $middleware) {
            $next = function ($req) use ($middleware, $next) {
                if (is_string($middleware)) {
                    $middleware = new $middleware();
                }
                return $middleware->handle($req, $next);
            };
        }

        return $next($request);
    }

    /**
     * Dispatch the request to the controller.
     *
     * @param callable|array $handler
     * @param array $params
     * @param Request|null $request
     * @return mixed
     */
    protected function dispatch(callable|array $handler, array $params = [], ?Request $request = null)
    {
        // Resolve the reflection target
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            $callback = [$controller, $method];
            $reflection = new \ReflectionMethod($controller, $method);
        } else {
            $callback = $handler;
            $reflection = new \ReflectionFunction($handler);
        }

        $args = [];
        
        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // Inject Request object if type-hinted
            if ($type && !$type->isBuiltin() && $type->getName() === Request::class && $request) {
                $args[] = $request;
                continue;
            }

            // Inject route parameters by name
            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }

            // Fallback for non-named params (if route didn't use named groups, which our Router does)
            // But just in case, or if we want to support order-based injection for closures
            // (though our Router ensures named params for routes defined with {param})
            
            // Use default value if available
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // If we have nullable type, pass null
            if ($type && $type->allowsNull()) {
                $args[] = null;
                continue;
            }

            // If we are here, we can't resolve the argument. 
            // We could try to shift from $params if it has numeric keys, but Router filters them out.
            // Let's just pass null or let it fail naturally with a better error?
            // Throwing is better.
            throw new \ArgumentCountError("Unable to resolve argument: \${$name} for handler.");
        }

        return call_user_func_array($callback, $args);
    }

    /**
     * Send the HTTP response.
     *
     * @param int $status
     * @param mixed $body
     */
    protected function sendResponse(int $status, $body)
    {
        if ($body instanceof \Engine\Response) {
            $body->send();
            return;
        }

        http_response_code($status);

        if (is_array($body) || is_object($body)) {
            header('Content-Type: application/json');
            echo json_encode($body);
        } else {
            echo $body;
        }
    }
}
