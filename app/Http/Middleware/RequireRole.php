<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(
            $request->user() && in_array($request->user()->role->value, $roles, true),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
