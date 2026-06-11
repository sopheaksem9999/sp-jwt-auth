<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        $request->attributes->set('sp_oauth_principal', $this->oauth->validateResourceToken($bearer));

        return $next($request);
    }
}
