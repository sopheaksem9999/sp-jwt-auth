<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sopheak\JwtAuth\DTO\ApiKeyPrincipal;
use Symfony\Component\HttpFoundation\Response;

final class RequireApiKeyScope
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $principal = $request->attributes->get('sp_api_key_principal');

        foreach ($scopes as $scope) {
            if (! $principal instanceof ApiKeyPrincipal || ! $principal->can($scope)) {
                abort(403);
            }
        }

        return $next($request);
    }
}
