<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sopheak\JwtAuth\Services\ApiKeyService;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateApiKey
{
    public function __construct(private ApiKeyService $apiKeys)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer === null || $bearer === '') {
            abort(401);
        }

        $request->attributes->set(
            'sp_api_key_principal',
            $this->apiKeys->validateApiKey($bearer, $request->ip()),
        );

        return $next($request);
    }
}
