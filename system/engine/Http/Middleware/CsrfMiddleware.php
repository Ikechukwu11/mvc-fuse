<?php
namespace Engine\Http\Middleware;

use Engine\Http\Middleware;
use Engine\Http\Request;
use Engine\Http\Response;

class CsrfMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Skip API routes
        if (str_starts_with($request->uri, '/api/')) {
            return $next($request);
        }

        $token = $_SESSION['_csrf'] ?? null;
        $incoming = $request->header('X-CSRF-TOKEN') ?? $request->header('X-CSRF') ?? $request->body['_token'] ?? null;
        if (!$token || !$incoming || !hash_equals((string)$token, (string)$incoming)) {
            $res = new Response();
            return $res->setStatus(419)->html('<h1>CSRF Token Mismatch</h1>', 419);
        }
        return $next($request);
    }

    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(20));
        }
        return $_SESSION['_csrf'];
    }
}

