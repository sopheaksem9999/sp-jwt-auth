# SP JWT Auth

[![Latest Stable Version](https://img.shields.io/packagist/v/sopheak/sp-jwt-auth.svg)](https://packagist.org/packages/sopheak/sp-jwt-auth)
[![Total Downloads](https://img.shields.io/packagist/dt/sopheak/sp-jwt-auth.svg)](https://packagist.org/packages/sopheak/sp-jwt-auth)
[![PHP Version](https://img.shields.io/packagist/dependency-v/sopheak/sp-jwt-auth/php.svg)](composer.json)
[![License](https://img.shields.io/packagist/l/sopheak/sp-jwt-auth.svg)](LICENSE)
[![Security](https://github.com/sopheak/sp-jwt-auth/actions/workflows/security.yml/badge.svg)](https://github.com/sopheak/sp-jwt-auth/actions/workflows/security.yml)

`sopheak/sp-jwt-auth` is a modular Laravel authentication package for first-party JWT APIs, rotating opaque refresh tokens, account security workflows, API keys, external identity links, and optional OAuth server mode.

The package owns authentication infrastructure. Your application still owns password login, registration, user creation, tenants, roles, UI, response shape, delivery templates, and business authorization policy.

Public package links:

- Documentation: [sp-jwt-auth-docs.vercel.app](https://sp-jwt-auth-docs.vercel.app/)
- Packagist: [packagist.org/packages/sopheak/sp-jwt-auth](https://packagist.org/packages/sopheak/sp-jwt-auth)

## Features

| Feature | What it provides | Default | Guide |
| --- | --- | --- | --- |
| Core JWT | `sp-jwt` guard, signed access tokens, persisted `jti`, scopes and claims, refresh rotation, revocation, signing-key rotation, and JWKS | Enabled | [Core JWT](https://sp-jwt-auth-docs.vercel.app/guide/core-jwt.html) |
| Token context and scopes | Tenant, company, device, session, impersonation, and custom-claim context; scope middleware | Enabled | [Claims and scopes](https://sp-jwt-auth-docs.vercel.app/guide/token-context-scopes-claims.html) |
| MFA and OTP | MFA challenges and one-time codes delivered through your app's email or phone/SMS provider | Disabled | [MFA and OTP](https://sp-jwt-auth-docs.vercel.app/guide/mfa-otp.html) |
| First-factor OTP | Passwordless sign-in and sign-up with OTP sent to an email address or phone number; rate limits and app-owned user creation | Disabled | [First-factor OTP](https://sp-jwt-auth-docs.vercel.app/guide/first-factor-otp.html) |
| Email verification | One-time, hashed email-verification tokens with app-owned delivery | Disabled | [Email verification](https://sp-jwt-auth-docs.vercel.app/guide/email-verification.html) |
| Password reset | One-time, hashed reset tokens; your app retains password validation and persistence | Disabled | [Password reset](https://sp-jwt-auth-docs.vercel.app/guide/password-reset.html) |
| JWT token endpoints | Optional HTTP endpoints for refresh rotation and session revocation | Disabled | [Token endpoints](https://sp-jwt-auth-docs.vercel.app/guide/token-endpoints.html) |
| API keys | Scoped integration keys with HMAC validation, rotation, revocation, IP restrictions, and middleware | Disabled | [API keys](https://sp-jwt-auth-docs.vercel.app/guide/api-keys.html) |
| Social authentication and external identity | Socialite, OIDC, and provider-profile normalization and storage for Google, GitHub, and other app-owned social-login flows | Disabled | [External identity](https://sp-jwt-auth-docs.vercel.app/guide/external-identity.html) |
| OAuth server | Isolated OAuth storage with clients, consent, authorization code + PKCE, client credentials, revocation, introspection, and resource middleware | Disabled | [OAuth server](https://sp-jwt-auth-docs.vercel.app/guide/oauth-server.html) |
| Events, hooks, and middleware | Lifecycle events, token-context hooks, and middleware for JWT, API-key, and OAuth protection | Enabled | [Events and hooks](https://sp-jwt-auth-docs.vercel.app/guide/events-hooks.html) · [Middleware](https://sp-jwt-auth-docs.vercel.app/guide/middleware.html) |

## Core package features

The enabled-by-default core gives first-party Laravel APIs a complete, auditable token lifecycle while leaving application-specific login and authorization decisions in your app.

- **Laravel-native JWT authentication** — register the `sp-jwt` guard and protect routes with Laravel's standard `auth` middleware.
- **Signed, key-identified access tokens** — issue RSA-signed JWTs with `kid` and persisted `jti` values; publish public keys through JWKS and rotate signing keys without exposing private material.
- **Scoped, contextual claims** — use `TokenContext` to attach scopes and application-owned context such as company IDs, device IDs, session IDs, impersonation state, and custom claims.
- **Safe refresh-token rotation** — refresh tokens are opaque `id.secret` values; only an HMAC hash is stored. Rotation is transactional and can revoke a session or all user sessions when reuse is detected.
- **Explicit revocation controls** — revoke a single access token, a session, a device, or every active session for a user.
- **Extension points and observability** — lifecycle events and `HookRegistry` hooks let your app validate or enrich token context and react to issuing, refresh, revocation, and reuse detection.
- **Operational tooling** — Artisan commands generate and rotate keys, inspect JWKS output, prune expired records, and validate the installation configuration.

## Quick Start

Requires PHP `^8.3|^8.4|^8.5`, Laravel `^12.0|^13.0`, and RSA signing keys for the default `RS256` setup.

Install, configure the API guard, and run migrations:

```bash
composer require sopheak/sp-jwt-auth
php artisan sp-jwt-auth:install --keys
php artisan migrate
php artisan sp-jwt-auth:validate
```

The setup command publishes config, generates local signing keys, and configures the Laravel `api` guard. If your `config/auth.php` is custom, add it manually:

```php
'guards' => [
    'api' => [
        'driver' => 'sp-jwt',
        'provider' => 'users',
    ],
],
```

Create login and refresh endpoints in your Laravel app. Your app owns credential validation; the package owns token issuing, refresh rotation, and token response formatting.

```php
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Support\TokenResponse;

Route::post('/login', function (Request $request, JwtTokenService $jwt) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::query()->where('email', $credentials['email'])->first();

    if (! $user || ! Hash::check($credentials['password'], $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    $pair = $jwt->issueTokenPair(
        $user,
        TokenContext::make()->scopes(['profile.read']),
    );

    return TokenResponse::passportCompatible($pair);
});

Route::post('/refresh', function (Request $request, JwtTokenService $jwt) {
    $data = $request->validate([
        'refresh_token' => ['required', 'string'],
    ]);

    return TokenResponse::passportCompatible(
        $jwt->rotateRefreshToken($data['refresh_token']),
    );
});

Route::middleware('auth:api')->get('/me', function (Request $request) {
    return $request->user();
});
```

Call protected routes with the returned access token:

```http
Authorization: Bearer <access-token>
```

## Documentation

For configuration, token claims, refresh and revocation, account-security workflows, API keys, external identity, OAuth server mode, middleware, and deployment guidance, visit the [official sp-jwt-auth documentation](https://sp-jwt-auth-docs.vercel.app/).

## License

This package is open-source software licensed under the [MIT license](LICENSE).
