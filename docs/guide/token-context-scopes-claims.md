---
title: "Token Context, Scopes, and Claims"
description: "Use TokenContext to attach scopes, claims, subject, device, and session data."
---

# Token Context, Scopes, and Claims

`TokenContext` describes what should be embedded in the token pair and persisted in token rows.

```php
use Sopheak\JwtAuth\DTO\TokenContext;

$context = TokenContext::make()
    ->subject('tenant', '42')
    ->scopes(['invoices.read', 'invoices.write'])
    ->claims(['tenant_id' => 42]);
```

## Scopes

Scopes are stored on access and refresh rows and embedded in the JWT payload as `scopes`.

```php
Route::middleware(['auth:api', 'sp.jwt.scope:invoices.read'])
    ->get('/invoices', Controller::class);
```

Use `sp.jwt.any_scope` when any one scope is enough.

```php
Route::middleware(['auth:api', 'sp.jwt.any_scope:admin,support'])
    ->get('/support', Controller::class);
```

## Claims

Claims are app-defined JSON-safe values.

Reserved JWT claim names cannot be used as custom claims:

- `iss`
- `sub`
- `aud`
- `exp`
- `nbf`
- `iat`
- `jti`
- `sid`
- `scopes`
- `subject`

## Subject Context

Subject context is useful for tenant, company, workspace, or account selection.

```php
TokenContext::make()->subject('company', '1001');
```

The package stores subject data but does not enforce tenant policy. The consuming application owns tenant authorization rules.

## Device and Session

`TokenContext` supports device and session metadata. If no session id is supplied, the package creates one when issuing tokens.

Use session ids to revoke all tokens for a single logged-in device/session.
