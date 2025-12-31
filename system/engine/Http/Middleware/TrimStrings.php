<?php
namespace Engine\Http\Middleware;

use Engine\Http\Middleware;
use Engine\Http\Request;
use Engine\Http\Response;

class TrimStrings implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        foreach ($request->body as $k => $v) {
            if (is_string($v)) {
                $request->body[$k] = trim($v);
            }
        }
        return $next($request);
    }
}

