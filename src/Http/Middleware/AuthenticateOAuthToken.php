<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Sopheak\JwtAuth\Exceptions\OAuthMalformedTokenException;
use Sopheak\JwtAuth\Services\OAuthServerService;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateOAuthToken
{
    public function __construct(private OAuthServerService $oauth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer === null || $bearer === '') {
            abort(401);
        }

        try {
            $principal = $this->oauth->validateResourceToken($bearer);
        } catch (AuthenticationException|OAuthMalformedTokenException) {
            abort(401);
        }

        $request->attributes->set('sp_oauth_principal', $principal);

        return $next($request);
    }
}
