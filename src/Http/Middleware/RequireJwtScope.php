<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireJwtScope
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = $request->user();

        foreach ($scopes as $scope) {
            if ($user === null || ! method_exists($user, 'tokenCan') || ! $user->tokenCan($scope)) {
                abort(403);
            }
        }

        return $next($request);
    }
}
