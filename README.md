# SP JWT Auth

`sopheak/sp-jwt-auth` is a modular Laravel authentication package for first-party JWT APIs, rotating opaque refresh tokens, account security workflows, API keys, external identity links, and optional OAuth server mode.

The package owns authentication infrastructure. Your application still owns password login, registration, user creation, tenants, roles, UI, response shape, delivery templates, and business authorization policy.

## Features

| Module | What it provides | Default |
| --- | --- | --- |
| Core JWT | `sp-jwt` guard, signed JWT access tokens, persisted `jti`, opaque rotating refresh tokens, scopes, claims, revocation, key rotation, JWKS, events, hooks | Enabled |
| Account Security | MFA challenge broker, hashed OTP codes, email verification tokens, password reset tokens, app-owned sender contracts | Disabled |
| API Keys | Scoped integration keys with public-id lookup, HMAC secret validation, rotation, revocation, IP restrictions, middleware | Disabled |
| External Identity | Normalized Socialite/OIDC-style identity DTO, provider contract, external identity storage | Disabled |
| OAuth Server | Separate `sp_oauth_*` storage, clients, consents, authorization-code + PKCE, refresh tokens, client credentials, revocation, introspection, resource middleware | Disabled |

## Requirements

- PHP `^8.3|^8.4|^8.5`
- Laravel `^12.0|^13.0`
- `firebase/php-jwt`
- RSA signing keys for the default `RS256` setup

Optional integrations are kept in Composer `suggest`:

- `laravel/socialite`
- `socialiteproviders/manager`
- `league/oauth2-client`
- `league/oauth2-server`

## Installation

```bash
composer require sopheak/sp-jwt-auth
php artisan sp-jwt-auth:install --keys
php artisan migrate
```

Configure the Laravel API guard:

```php
'guards' => [
    'api' => [
        'driver' => 'sp-jwt',
        'provider' => 'users',
    ],
],
```

Keep Laravel's normal `web` guard for Blade, Livewire, Inertia, and session pages.

## Configuration

Publish the config when needed:

```bash
php artisan vendor:publish --tag=sp-jwt-auth-config
```

Common environment keys:

```env
SP_JWT_GUARD=api
SP_JWT_USER_PROVIDER=users
SP_JWT_ISSUER=https://app.example.com
SP_JWT_AUDIENCE=app-api
SP_JWT_ALGORITHM=RS256
SP_JWT_ACCESS_TTL_MINUTES=15
SP_JWT_REFRESH_TTL_DAYS=60
SP_JWT_REUSE_DETECTION=revoke_session
SP_JWT_ACTIVE_KID=2026-06-primary
SP_JWT_HASH_KEY_ID=default
SP_JWT_REFRESH_HASH_KEY=change-me-to-a-long-random-secret
```

Optional modules have their own config sections:

- `mfa`
- `email_verification`
- `password_reset`
- `api_keys`
- `external_identities`
- `oauth_server`

## Core JWT Usage

Your app validates credentials, resolves a user, builds a `TokenContext`, then asks the package to issue tokens.

```php
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Support\TokenResponse;

$pair = app(JwtTokenService::class)->issueTokenPair(
    $user,
    TokenContext::make()
        ->subject('tenant', '42')
        ->scopes(['invoices.read', 'invoices.write'])
        ->claims(['tenant_id' => 42]),
);

return TokenResponse::passportCompatible($pair);
```

Protect routes with Laravel auth middleware:

```php
Route::middleware(['auth:api'])->get('/me', MeController::class);

Route::middleware(['auth:api', 'sp.jwt.scope:invoices.read'])
    ->get('/invoices', InvoiceIndexController::class);
```

Add Passport-like helpers to user models:

```php
use Sopheak\JwtAuth\Traits\HasJwtTokens;

class User extends Authenticatable
{
    use HasJwtTokens;
}
```

```php
$request->user()->token();
$request->user()->tokenCan('invoices.read');
```

## Refresh and Revocation

Refresh tokens are returned as `id.secret`. Only the HMAC hash of the secret is stored.

```php
$pair = app(JwtTokenService::class)->rotateRefreshToken(
    $request->input('refresh_token'),
);
```

Revoke one access token, one session, or all sessions for a user:

```php
$token = $request->user()->token();

app(JwtTokenService::class)->revokeAccessToken($token->id);
app(JwtTokenService::class)->revokeSession($token->session_id);
app(JwtTokenService::class)->revokeAllForUser($request->user());
```

## Account Security

Account security brokers can be called from controllers, Livewire actions, queued jobs, or service classes. Delivery is app-owned through sender contracts.

```php
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Services\EmailVerificationBroker;
use Sopheak\JwtAuth\Services\MfaChallengeBroker;
use Sopheak\JwtAuth\Services\OtpChallengeBroker;
use Sopheak\JwtAuth\Services\PasswordResetBroker;

$challenge = app(MfaChallengeBroker::class)->create($user, TokenContext::make());

$otp = app(OtpChallengeBroker::class)->createOtp(
    $challenge,
    new OtpDestination('email', 'user@example.com', 'u***@example.com'),
);

$context = app(OtpChallengeBroker::class)->verifyOtp($challenge->id, $otp->plaintextCode);

$verification = app(EmailVerificationBroker::class)
    ->createVerificationToken($user, $user->email);

$verified = app(EmailVerificationBroker::class)
    ->verifyEmailToken($verification->token);

$reset = app(PasswordResetBroker::class)->createResetToken($user, $user->email);
$result = app(PasswordResetBroker::class)->consumeResetToken($reset->token);
```

Available sender contracts:

- `OtpChannelSender`
- `EmailVerificationSender`
- `PasswordResetSender`

## API Keys

API keys are for third-party integrations and machine clients. The full plaintext key is returned only at creation or rotation time.

```php
use Sopheak\JwtAuth\DTO\ApiKeyContext;
use Sopheak\JwtAuth\Services\ApiKeyService;

$key = app(ApiKeyService::class)->createApiKey(new ApiKeyContext(
    ownerType: 'tenant',
    ownerId: '42',
    name: 'ERP sync',
    scopes: ['invoices.write'],
    claims: ['tenant_id' => 42],
));
```

Protect integration routes:

```php
Route::middleware(['sp.api_key', 'sp.api_key.scope:invoices.write'])
    ->post('/integrations/invoices', IntegrationInvoiceController::class);
```

Rotate or revoke:

```php
$rotated = app(ApiKeyService::class)->rotateApiKey($apiKeyId);
app(ApiKeyService::class)->revokeApiKey($apiKeyId);
app(ApiKeyService::class)->revokeApiKeysForOwner('tenant', '42');
```

## External Identity

External identity support normalizes provider profiles. The app decides whether to link, create, or deny a local user.

```php
use Sopheak\JwtAuth\DTO\ExternalIdentity;
use Sopheak\JwtAuth\Services\ExternalIdentityStore;

app(ExternalIdentityStore::class)->store(new ExternalIdentity(
    provider: 'google',
    providerUserId: $providerUser->getId(),
    email: $providerUser->getEmail(),
    emailVerified: true,
    name: $providerUser->getName(),
    rawProfile: $providerUser->user,
), $user);
```

Provider adapters can implement `Sopheak\JwtAuth\Contracts\ExternalIdentityProvider`.

## OAuth Server Mode

OAuth server mode is disabled by default and uses separate `sp_oauth_*` tables. It is for third-party clients, not normal first-party SPA/mobile login.

```env
SP_JWT_OAUTH_SERVER_ENABLED=true
```

Create a client:

```php
use Sopheak\JwtAuth\DTO\OAuthClientData;
use Sopheak\JwtAuth\Services\OAuthClientRepository;

$client = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
    name: 'ERP Connector',
    redirectUris: ['https://client.example/callback'],
    allowedGrants: ['authorization_code', 'refresh_token'],
    allowedScopes: ['invoices.read'],
));
```

Protect OAuth resource routes:

```php
Route::middleware(['sp.oauth', 'sp.oauth.scope:invoices.read'])
    ->get('/partner/invoices', PartnerInvoiceController::class);
```

OAuth client-credentials tokens authenticate as clients, not users.

## Middleware

| Middleware | Purpose |
| --- | --- |
| `sp.jwt` | Authenticate with the configured first-party JWT guard |
| `sp.jwt.scope:<scope>` | Require every listed JWT scope |
| `sp.jwt.any_scope:<scope1>,<scope2>` | Require any listed JWT scope |
| `sp.api_key` | Authenticate an API key bearer token |
| `sp.api_key.scope:<scope>` | Require every listed API key scope |
| `sp.api_key.any_scope:<scope1>,<scope2>` | Require any listed API key scope |
| `sp.oauth` | Authenticate an OAuth resource token |
| `sp.oauth.scope:<scope>` | Require every listed OAuth scope |
| `sp.oauth.any_scope:<scope1>,<scope2>` | Require any listed OAuth scope |
| `sp.oauth.client:<client_id>` | Restrict OAuth access to a client id |

## Commands

```bash
php artisan sp-jwt-auth:install --keys
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary
php artisan sp-jwt-auth:jwks --pretty
php artisan sp-jwt-auth:prune --expired-days=30 --revoked-days=30
```

## Events and Hooks

The package emits lifecycle events for:

- Token issue, refresh, revocation, sessions, and refresh reuse detection.
- MFA, OTP, email verification, and password reset.
- API key creation, use, revocation, and rotation.
- External identity resolution.
- OAuth clients, consents, authorization approval, token issue, and token revocation.

`HookRegistry` supports token-context validation, token-context mutation, and after-issue hooks for app-owned policy.

## Security Notes

- JWTs are signed with package signing keys, never `APP_KEY`.
- JWKS exposes public keys only.
- Refresh tokens, OTP codes, verification tokens, reset tokens, API keys, OAuth client secrets, and OAuth opaque tokens are stored as HMAC hashes.
- Refresh rotation runs in a transaction and detects reuse.
- OAuth tokens use separate storage and middleware from first-party JWT tokens.
- Optional modules are disabled by default and can be enabled incrementally.

## Documentation

- [Guide index](docs/guide/index.md)
- [Getting started](docs/guide/getting-started.md)
- [Core JWT](docs/guide/core-jwt.md)
- [Account security](docs/guide/mfa-otp.md)
- [API keys](docs/guide/api-keys.md)
- [External identity](docs/guide/external-identity.md)
- [OAuth server](docs/guide/oauth-server.md)
- [Events and hooks](docs/guide/events-hooks.md)

## Development

```bash
composer install
composer quality
```

`composer quality` runs Rector dry-run, PHPStan, and PHPUnit.

## License

This package is proprietary software.
