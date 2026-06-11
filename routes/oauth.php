<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\Services\OAuthServerService;

Route::prefix((string) config('sp-jwt-auth.oauth_server.route_prefix', 'oauth'))->group(function (): void {
    Route::post('/token', static fn (OAuthServerService $oauth) => response()->json(
        $oauth->issueTokenFromRequest(request())->toArray(),
    ));

    Route::post('/revoke', static function (OAuthServerService $oauth): \Illuminate\Http\JsonResponse {
        $oauth->revokeToken((string) request('token'), is_string(request('token_type_hint')) ? request('token_type_hint') : null);

        return response()->json([], 200);
    });

    Route::post('/introspect', static fn (OAuthServerService $oauth) => response()->json(
        (array) $oauth->introspect((string) request('token')),
    ));
});
