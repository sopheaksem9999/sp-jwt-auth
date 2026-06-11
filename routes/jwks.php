<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\Signing\JwksFormatter;
use Sopheak\JwtAuth\Signing\SigningKeyRepository;

Route::get(config('sp-jwt-auth.keys.jwks_route', '/.well-known/sp-jwt-auth/jwks.json'), function (
    SigningKeyRepository $keys,
    JwksFormatter $formatter,
) {
    return response()->json($formatter->format($keys->publicKeys(activeOnly: false)));
})->name('sp-jwt-auth.jwks');
