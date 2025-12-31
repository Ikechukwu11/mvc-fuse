<?php
namespace Engine\Http\Middleware;

use Engine\Http\Middleware;
use Engine\Http\Request;
use Engine\Http\Response;

class JsonBodyParser implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if ($request->isJson()) {
            // Check if body is already populated (e.g. by Request constructor or other middleware)
            if (empty($request->body)) {
                $raw = file_get_contents('php://input') ?: '';
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $request->body = $decoded;
                }
            }
        }
        return $next($request);
    }
}

