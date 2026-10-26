<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\Services\OAuthServerService;
use Sopheak\JwtAuth\Support\OAuthHttp;

Route::prefix((string) config('sp-jwt-auth.oauth_server.route_prefix', 'oauth'))->group(function (): void {
    Route::post('/token', static fn (OAuthServerService $oauth, Request $request) => OAuthHttp::token($oauth, $request));

    Route::post('/revoke', static fn (OAuthServerService $oauth, Request $request) => OAuthHttp::revoke($oauth, $request));

    Route::post('/introspect', static fn (OAuthServerService $oauth, Request $request) => OAuthHttp::introspect($oauth, $request));
});
