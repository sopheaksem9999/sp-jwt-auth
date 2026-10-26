<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Sopheak\JwtAuth\DTO\TokenPair;

final class TokenResponse
{
    private static array $extensions = [];

    public static function extend(callable $callback): void
    {
        self::$extensions[] = $callback;
    }

    public static function flushExtensions(): void
    {
        self::$extensions = [];
    }

    public static function passportCompatible(TokenPair $pair, array $extra = []): array
    {
        $response = array_merge([
            'token_type' => 'Bearer',
            'expires_in' => $pair->expiresIn(),
            'access_token' => $pair->accessToken,
            'refresh_token' => $pair->refreshToken,
        ], $extra);

        foreach (self::$extensions as $extension) {
            $response = $extension($response, $pair);
        }

        return $response;
    }

    /**
     * For host login/refresh routes: in cookie or both mode, set the refresh token as an
     * httpOnly cookie (plus the CSRF cookie) and, in cookie mode, drop it from the body.
     * A no-op in body mode, so routes can call it unconditionally.
     */
    public static function withRefreshCookie(JsonResponse|Response $response, TokenPair $pair): JsonResponse|Response
    {
        return RefreshCookie::attach($response, $pair);
    }
}
