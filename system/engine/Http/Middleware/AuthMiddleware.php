<?php
namespace Engine\Http\Middleware;

use Engine\Http\Middleware;
use Engine\Http\Request;
use Engine\Http\Response;

/**
 * Auth Middleware
 *
 * Protects routes by requiring a valid user session.
 */
class AuthMiddleware implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * Checks if 'user_id' exists in the session. If not, returns 401 Unauthorized.
     *
     * @param Request $request
     * @param callable $next
     * @return mixed
     */
    public function handle(Request $request, callable $next): mixed
    {
        if (!isset($_SESSION['user_id'])) {
            $res = new Response();
            return $res->setStatus(401)->html('<h1>Unauthorized</h1>', 401);
        }
        return $next($request);
    }
}
