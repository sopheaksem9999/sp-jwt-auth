<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\Services\JwtTokenService;

Route::prefix((string) config('sp-jwt-auth.token_endpoints.route_prefix', 'auth'))->group(function (): void {
    Route::post('/token/refresh', static function (Request $request, JwtTokenService $jwt) {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $pair = $jwt->rotateRefreshToken($data['refresh_token']);

        return response()->json([
            'access_token' => $pair->accessToken,
            'refresh_token' => $pair->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $pair->expiresIn(),
        ]);
    });

    Route::post('/token/revoke', static function () {
        $user = auth()->user();
        $token = $user?->token();

        if ($token === null || $token->session_id === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        app(JwtTokenService::class)->revokeSession($token->session_id);

        return response()->json([], 200);
    })->middleware('auth:api');
});
