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

Access token claims are stored in the `sp_jwt_access_tokens.claims` JSON column and cast to an array on `JwtAccessToken`. The package does not store the raw JWT string on the token model.

After the `sp-jwt` guard authenticates a request, read claims from the attached token:

```php
$token = $request->user()?->token();

$tenantId = $token?->claim('tenant_id');
$companyId = $token?->claim('company_id');
$claims = $token?->claims ?? [];
```

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
TokenContext::make()
    ->subject('company', '1001')
    ->claims([
        'company_id' => 1001,
        'company_ids' => [1001, 1002],
    ]);
```

Use `subject_type` / `subject_id` for the primary active tenant/company/workspace context because those columns are indexed on the access-token table. Use claims for app-readable request context. The package does not add hardcoded `company_id` columns because tenant models vary by application.

The package stores subject and claim data but does not enforce tenant policy. The consuming application owns tenant authorization rules.

## Token Responses

`TokenResponse::passportCompatible()` accepts extra fields for app-owned response data:

```php
return TokenResponse::passportCompatible($pair, [
    'company_id' => $pair->accessTokenRecord->claim('company_id'),
    'impersonated' => $pair->accessTokenRecord->claim('impersonated', false),
]);
```

A global token-response transformer is not part of the current API. If you need one, register the extra fields in your controller or response resource for now.

## Device and Session

`TokenContext` supports device and session metadata. If no session id is supplied, the package creates one when issuing tokens.

Use session ids to revoke all tokens for a single logged-in device/session.
