---
title: "Quick Start"
description: "Issue your first JWT token pair and protect an API route."
---

# Quick Start

## 1. Install

```bash
composer require sopheak/sp-jwt-auth
php artisan sp-jwt-auth:setup --keys
php artisan migrate
php artisan sp-jwt-auth:validate
```

## 2. Generate or Rotate Keys

```bash
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary --pem
```

Use this when you skipped `sp-jwt-auth:setup --keys` or when you need to rotate to a named key id. The command updates `.env` by default and creates `SP_JWT_REFRESH_HASH_KEY` when it is missing. The default published config reads generated key paths from `.env`:

```env
SP_JWT_ACTIVE_KID=2026-06-primary
SP_JWT_PRIVATE_KEY_PATH=storage/jwt-private-2026-06-primary.pem
SP_JWT_PUBLIC_KEY_PATH=storage/jwt-public-2026-06-primary.pem
SP_JWT_REFRESH_HASH_KEY=your-random-refresh-hash-secret
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
