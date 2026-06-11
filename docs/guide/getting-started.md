---
title: "Getting Started"
description: "Install and configure sopheak/sp-jwt-auth in a Laravel app."
---

# Getting Started

Install the package, publish config/migrations, generate keys, and configure Laravel's API guard.

```bash
composer require sopheak/sp-jwt-auth
php artisan sp-jwt-auth:install --keys
php artisan migrate
```

## Configure the API Guard

Keep the normal `web` guard for Blade, Livewire, Inertia, and session pages. Use `sp-jwt` for API routes.

```php
'guards' => [
    'api' => [
        'driver' => 'sp-jwt',
        'provider' => 'users',
    ],
],
```

The package reads the configured Laravel user provider. It does not own password login, registration, tenant selection, roles, or response shape.

## Issue the First Token Pair

After the application validates credentials and resolves a user, pass the user to `JwtTokenService`.

```php
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Support\TokenResponse;

$pair = app(JwtTokenService::class)->issueTokenPair(
    $user,
    TokenContext::make()
        ->subject('tenant', '42')
        ->scopes(['invoices.read'])
        ->claims(['tenant_id' => 42]),
);

return TokenResponse::passportCompatible($pair);
```

## Protect API Routes

```php
Route::middleware(['auth:api'])->get('/me', MeController::class);

Route::middleware(['auth:api', 'sp.jwt.scope:invoices.read'])
    ->get('/invoices', InvoiceIndexController::class);
```

## Optional Modules

Optional modules are disabled by default:

- Account security: MFA challenges, OTP, email verification, password reset tokens.
- API keys: expirable scoped integration keys.
- External identity: Socialite/OIDC identity normalization and storage.
- OAuth server: third-party OAuth clients, consent, authorization code + PKCE, client credentials.
