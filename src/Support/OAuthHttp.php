<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Sopheak\JwtAuth\Contracts\OAuthErrorException;
use Sopheak\JwtAuth\Exceptions\OAuthMalformedTokenException;
use Sopheak\JwtAuth\Services\OAuthServerService;
use Throwable;

/**
 * HTTP layer of the OAuth routes: RFC 6749 / 7009 / 7662 response shapes.
 *
 * Set `oauth_server.legacy_responses` to keep the pre-0.1.25 shapes
 * (framework error bodies, camelCase introspection) during migration.
 */
final class OAuthHttp
{
    public static function token(OAuthServerService $oauth, Request $request): JsonResponse
    {
        try {
            return self::noStore(response()->json($oauth->issueTokenFromRequest($request)->toArray()));
        } catch (Throwable $throwable) {
            return self::fail($throwable);
        }
    }

    public static function revoke(OAuthServerService $oauth, Request $request): JsonResponse
    {
        try {
            $client = $oauth->authenticateClient($request);
            $hint = $request->input('token_type_hint');

            try {
                $oauth->revokeToken((string) $request->input('token'), is_string($hint) ? $hint : null, $client);
            } catch (OAuthMalformedTokenException) {
                // RFC 7009 §2.2: an unusable token is not an error.
            }

            return response()->json([], 200);
        } catch (Throwable $throwable) {
            return self::fail($throwable);
        }
    }

    public static function introspect(OAuthServerService $oauth, Request $request): JsonResponse
    {
        try {
            $payload = $oauth->introspect((string) $request->input('token'), $oauth->authenticateClient($request));

            return self::noStore(response()->json(
                self::legacy() ? (array) $payload : $payload->toRfcArray(),
            ));
        } catch (Throwable $throwable) {
            return self::fail($throwable);
        }
    }

    private static function fail(Throwable $throwable): JsonResponse
    {
        if (self::legacy()) {
            throw $throwable;
        }

        if ($throwable instanceof OAuthErrorException) {
            $response = response()->json([
                'error' => $throwable->oauthError(),
                'error_description' => $throwable->getMessage(),
            ], $throwable->oauthStatus());

            return $throwable->oauthStatus() === 401
                ? $response->header('WWW-Authenticate', 'Basic realm="oauth"')
                : $response;
        }

        if ($throwable instanceof AuthenticationException || $throwable instanceof InvalidArgumentException) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => $throwable->getMessage(),
            ], 400);
        }

        throw $throwable;
    }

    private static function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
    }

    private static function legacy(): bool
    {
        return (bool) config('sp-jwt-auth.oauth_server.legacy_responses', false);
    }
}
