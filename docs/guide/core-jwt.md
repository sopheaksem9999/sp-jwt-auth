---
title: "Core JWT Tokens"
description: "Issue, validate, and use first-party JWT access tokens."
---

# Core JWT Tokens

Core JWT is the default package module. It provides:

- `sp-jwt` Laravel guard driver.
- Signed JWT access tokens.
- Persisted access token `jti` rows.
- Opaque rotating refresh tokens.
- Scope and claim support.
- Passport-compatible token helpers.

## Issue a Token Pair

```php
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;

$pair = app(JwtTokenService::class)->issueTokenPair(
    $user,
    TokenContext::make()->scopes(['profile.read']),
);
```

The access token is a JWT. The refresh token is an opaque `id.secret` value.

## Access Token Validation

`JwtTokenService::validateAccessToken()` checks:

- JWT structure.
- Signature and configured `kid`.
- Issuer.
- Configured audience, when set.
- JWT expiry.
- Persisted `sp_jwt_access_tokens` row.
- Revocation state.
- Database expiry.

## Laravel Guard

Configure the guard:

```php
'api' => [
    'driver' => 'sp-jwt',
    'provider' => 'users',
],
```

Then protect routes with normal Laravel auth middleware:

```php
Route::middleware('auth:api')->get('/me', MeController::class);
```

## User Token Helpers

Add `HasJwtTokens` to authenticatable models that need Passport-like helpers:

```php
use Sopheak\JwtAuth\Traits\HasJwtTokens;

class User extends Authenticatable
{
    use HasJwtTokens;
}
```

Then use:

```php
$request->user()->token();
$request->user()->tokenCan('profile.read');
```
