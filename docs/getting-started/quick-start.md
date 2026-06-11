---
title: "Quick Start"
description: "Issue your first JWT token pair and protect an API route."
---

# Quick Start

## 1. Install

```bash
composer require sopheak/sp-jwt-auth
php artisan sp-jwt-auth:install
php artisan migrate
```

## 2. Generate Keys

```bash
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary --pem --env
```

Then add the key to `config/sp-jwt-auth.php` under `keys.items`:

```php
'items' => [
    '2026-06-primary' => [
        'state' => 'active',
        'private_key_path' => base_path('storage/jwt-private-2026-06-primary.pem'),
        'public_key_path' => base_path('storage/jwt-public-2026-06-primary.pem'),
    ],
],
```

## 3. Configure Guard

```php
// config/auth.php
'guards' => [
    'api' => ['driver' => 'sp-jwt', 'provider' => 'users'],
],
```

## 4. Issue a Token Pair

```php
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Support\TokenResponse;

$pair = app(JwtTokenService::class)->issueTokenPair(
    $user,
    TokenContext::make()->scopes(['profile.read']),
);

return TokenResponse::passportCompatible($pair);
```

## 5. Protect a Route

```php
Route::middleware('auth:api')->get('/me', MeController::class);
```

## 6. Refresh

```php
$pair = app(JwtTokenService::class)->rotateRefreshToken(
    $request->input('refresh_token'),
);
```
