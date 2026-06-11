<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sopheak\JwtAuth\DTO\OAuthPrincipal;
use Symfony\Component\HttpFoundation\Response;

final class RequireOAuthClient
{
    public function handle(Request $request, Closure $next, string ...$clientIds): Response
    {
        $principal = $request->attributes->get('sp_oauth_principal');

        if (! $principal instanceof OAuthPrincipal) {
            abort(403);
        }

        if ($clientIds === [] || $clientIds === [''] || in_array($principal->clientId, $clientIds, true)) {
            return $next($request);
        }

        abort(403);
    }
}
