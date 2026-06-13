---
title: "Migration from Sanctum or Passport"
description: "Common patterns for switching existing apps to sp-jwt-auth."
---

# Migration from Sanctum or Passport

This guide covers common migration patterns. The right approach depends on whether you can issue new tokens to all clients or need to support existing tokens during a transition.

## Conceptual Differences

| Area | Sanctum | Passport | sp-jwt-auth |
|---|---|---|---|
| Token format | Hashed random string | JWT (Bearer) | JWT (Bearer) |
| Refresh tokens | Not built-in | Built-in | Built-in |
| Token storage | `personal_access_tokens` | `oauth_access_tokens` | `sp_jwt_access_tokens` |
| Guard driver | `sanctum` | `passport` | `sp-jwt` |
| Scopes | Abilities | Scopes | Scopes |
| Token on user | `$user->currentAccessToken()` | `$user->token()` | `$user->token()` |

## Quick Switch (New Tokens for All Clients)

If you can force all clients to obtain new tokens:

1. Replace the guard in `config/auth.php`:

```diff
 'api' => [
-    'driver' => 'sanctum',
+    'driver' => 'sp-jwt',
     'provider' => 'users',
 ],
```

2. Update your login controller to use `JwtTokenService` instead of Sanctum/Passport token issuance.

3. Update route middleware if using Sanctum's token abilities:

```diff
-Route::middleware('auth:sanctum')->get('/me', MeController::class);
+Route::middleware('auth:api')->get('/me', MeController::class);
```

4. Run the package install and migrate:

```bash
php artisan sp-jwt-auth:install
php artisan sp-jwt-auth:keys --generate --pem
php artisan migrate
```

## Gradual Migration (Dual Auth)

Support both old and new tokens during a transition period:

```php
// config/auth.php
'guards' => [
    'api' => [
        'driver' => 'sp-jwt',
        'provider' => 'users',
    ],
    'legacy' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```

```php
Route::middleware(['auth:api,legacy'])->group(function () {
    // Routes that accept both token types
});
```

Once all clients have migrated, remove the legacy guard and Sanctum tables.

## Passport to sp-jwt-auth Similarities

If migrating from Passport, the token response is intentionally compatible:

```php
// Both return the same shape
TokenResponse::passportCompatible($pair);
```

The `HasJwtTokens` trait mirrors Passport's `HasApiTokens`:

```php
// Passport
$request->user()->token();
$request->user()->tokenCan('scope');

// sp-jwt-auth
$request->user()->token();
$request->user()->tokenCan('scope');
```

## Data Migration

To carry existing users forward without forcing re-login, you can issue sp-jwt-auth tokens server-side for active sessions during the migration window. This is app-specific and depends on your session/token tracking.
