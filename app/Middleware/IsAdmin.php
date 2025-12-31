<?php

namespace App\Middleware;

use Engine\Http\Middleware;
use Engine\Http\Request;

class IsAdmin implements Middleware
{
    public function handle(Request $request, callable $next)
    {
        if (session_get('user_role') !== 'admin') {
            http_response_code(403);
            return [403, '<h1>403 Forbidden</h1><p>Admins only.</p>'];
        }

        return $next($request);
    }
}
