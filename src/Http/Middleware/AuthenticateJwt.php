<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = (string) config('sp-jwt-auth.guard', 'api');

        if ($request->user($guard) === null) {
            abort(401);
        }

        return $next($request);
    }
}
