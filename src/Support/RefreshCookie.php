<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Sopheak\JwtAuth\DTO\TokenPair;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Optional httpOnly-cookie transport for refresh tokens
 * (`token_endpoints.refresh_transport` = body | cookie | both).
 *
 * The refresh cookie is httpOnly and scoped to the token endpoints. CSRF uses a
 * double-submit pair: a JavaScript-readable cookie plus a header that must match it.
 * All settings fall back to defaults here because a published config replaces the
 * whole `token_endpoints` array rather than merging into it.
 */
final class RefreshCookie
{
    public static function transport(): string
    {
        $transport = (string) config('sp-jwt-auth.token_endpoints.refresh_transport', 'body');

        return in_array($transport, ['body', 'cookie', 'both'], true) ? $transport : 'body';
    }

    public static function usesCookie(): bool
    {
        return self::transport() !== 'body';
    }

    /** Whether the refresh token may be sent in, and is returned in, the JSON body. */
    public static function usesBody(): bool
    {
        return self::transport() !== 'cookie';
    }

    public static function name(): string
    {
        return (string) self::setting('name', 'sp_refresh_token');
    }

    public static function csrfHeader(): string
    {
        return (string) config('sp-jwt-auth.token_endpoints.csrf.header', 'X-SP-CSRF');
    }

    public static function csrfCookieName(): string
    {
        return (string) config('sp-jwt-auth.token_endpoints.csrf.cookie', 'sp_csrf');
    }

    public static function fromRequest(Request $request): ?string
    {
        $value = $request->cookie(self::name());

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Double-submit check; always true when CSRF protection is switched off. */
    public static function csrfValid(Request $request): bool
    {
        if (! (bool) config('sp-jwt-auth.token_endpoints.csrf.enabled', true)) {
            return true;
        }

        $header = $request->header(self::csrfHeader());
        $cookie = $request->cookie(self::csrfCookieName());

        return is_string($header) && $header !== '' && is_string($cookie) && hash_equals($cookie, $header);
    }

    /**
     * Set the rotated refresh cookie (and CSRF cookie) on a response. In `cookie`
     * mode the refresh token is also removed from the JSON body.
     */
    public static function attach(JsonResponse|Response $response, TokenPair $pair): JsonResponse|Response
    {
        if (! self::usesCookie()) {
            return $response;
        }

        if (! self::usesBody() && $response instanceof JsonResponse) {
            $response->setData(self::withoutRefreshToken($response->getData(true), $pair->refreshToken));
        }

        $response->headers->setCookie(self::make(self::name(), $pair->refreshToken, $pair->refreshTokenExpiresAt->getTimestamp(), self::path(), true));

        if ((bool) config('sp-jwt-auth.token_endpoints.csrf.enabled', true)) {
            $response->headers->setCookie(self::make(self::csrfCookieName(), bin2hex(random_bytes(32)), $pair->refreshTokenExpiresAt->getTimestamp(), '/', false));
        }

        return $response;
    }

    /** Expire the refresh and CSRF cookies. */
    public static function forget(JsonResponse|Response $response): JsonResponse|Response
    {
        $response->headers->setCookie(self::make(self::name(), '', 1, self::path(), true));
        $response->headers->setCookie(self::make(self::csrfCookieName(), '', 1, '/', false));

        return $response;
    }

    private static function make(string $name, string $value, int $expires, string $path, bool $httpOnly): Cookie
    {
        $sameSite = strtolower((string) self::setting('same_site', 'strict'));
        $sameSite = in_array($sameSite, ['strict', 'lax', 'none'], true) ? $sameSite : 'strict';
        // SameSite=None is only honoured by browsers on Secure cookies.
        $secure = $sameSite === 'none' || (bool) self::setting('secure', true);
        $domain = self::setting('domain', null);

        return new Cookie($name, $value, $expires, $path, is_string($domain) && $domain !== '' ? $domain : null, $secure, $httpOnly, true, $sameSite);
    }

    private static function path(): string
    {
        $path = self::setting('path', null);

        return is_string($path) && $path !== ''
            ? $path
            : '/' . trim((string) config('sp-jwt-auth.token_endpoints.route_prefix', 'auth'), '/') . '/token';
    }

    private static function setting(string $key, mixed $default): mixed
    {
        return config('sp-jwt-auth.token_endpoints.refresh_cookie.' . $key, $default);
    }

    /** @param array<mixed> $data */
    private static function withoutRefreshToken(array $data, string $refreshToken): array
    {
        foreach ($data as $key => $value) {
            if ($key === 'refresh_token' && $value === $refreshToken) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = self::withoutRefreshToken($value, $refreshToken);
            }
        }

        return $data;
    }
}
