<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sopheak\JwtAuth\DTO\OAuthPrincipal;
use Symfony\Component\HttpFoundation\Response;

final class RequireOAuthScope
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $principal = $request->attributes->get('sp_oauth_principal');

        foreach ($scopes as $scope) {
            if (! $principal instanceof OAuthPrincipal || ! $principal->can($scope)) {
                abort(403);
            }
        }

        return $next($request);
    }
}
