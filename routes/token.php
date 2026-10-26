<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Support\RefreshCookie;
use Sopheak\JwtAuth\Support\ResponseEnvelope;

Route::prefix((string) config('sp-jwt-auth.token_endpoints.route_prefix', 'auth'))->group(function (): void {
    Route::post('/token/refresh', static function (Request $request, JwtTokenService $jwt) {
        $token = null;
        $fromCookie = false;

        if (! RefreshCookie::usesCookie()) {
            $token = $request->validate(['refresh_token' => ['required', 'string']])['refresh_token'];
        } else {
            $body = $request->input('refresh_token');

            if (RefreshCookie::usesBody() && is_string($body) && $body !== '') {
                $token = $body;
            } else {
                $token = RefreshCookie::fromRequest($request);
                $fromCookie = $token !== null;
            }
        }

        if ($token === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 403, not 401: clients treat 401 as "session ended".
        if ($fromCookie && ! RefreshCookie::csrfValid($request)) {
            return response()->json(['message' => 'CSRF token mismatch.'], 403);
        }

        try {
            $pair = $jwt->rotateRefreshToken($token);
        } catch (AuthenticationException $exception) {
            if (! $fromCookie) {
                throw $exception;
            }

            return RefreshCookie::forget(response()->json(['message' => $exception->getMessage()], 401));
        }

        $payload = ['access_token' => $pair->accessToken];

        if (RefreshCookie::usesBody()) {
            $payload['refresh_token'] = $pair->refreshToken;
        }

        $payload += [
            'token_type' => 'Bearer',
            'expires_in' => $pair->expiresIn(),
        ];

        $response = response()->json(ResponseEnvelope::wrap($payload, (string) config('sp-jwt-auth.token_endpoints.response_envelope', 'raw')));

        return RefreshCookie::attach($response, $pair);
    });

    // Authenticated by the access token (bearer) or, failing that, by the refresh token
    // from the body or cookie, so logout still works after the access token expired.
    Route::post('/token/revoke', static function (Request $request, JwtTokenService $jwt) {
        $unauthenticated = static fn () => response()->json(['message' => 'Unauthenticated.'], 401);
        $token = auth((string) config('sp-jwt-auth.guard', 'api'))->user()?->token();
        $revoked = false;
        $usedCookie = false;

        if ($token !== null && $token->session_id !== null) {
            $jwt->revokeSession($token->session_id);
            $revoked = true;
        } else {
            $body = $request->input('refresh_token');
            $candidate = RefreshCookie::usesBody() && is_string($body) && $body !== '' ? $body : null;

            if ($candidate === null && RefreshCookie::usesCookie()) {
                $candidate = RefreshCookie::fromRequest($request);
                $usedCookie = $candidate !== null;
            }

            if ($candidate === null) {
                return $unauthenticated();
            }

            if ($usedCookie && ! RefreshCookie::csrfValid($request)) {
                return response()->json(['message' => 'CSRF token mismatch.'], 403);
            }

            $revoked = $jwt->revokeSessionByRefreshToken($candidate);
        }

        $body = ResponseEnvelope::wrap([], (string) config('sp-jwt-auth.token_endpoints.response_envelope', 'raw'));
        $response = $revoked
            ? response()->json($body === [] ? new stdClass() : $body)
            : $unauthenticated();

        // Clear even when the token was already revoked, so the browser drops it.
        return RefreshCookie::usesCookie() && $request->cookies->has(RefreshCookie::name())
            ? RefreshCookie::forget($response)
            : $response;
    });
});
