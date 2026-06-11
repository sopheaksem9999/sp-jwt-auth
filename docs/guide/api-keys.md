---
title: "API Keys"
description: "Create, validate, rotate, and revoke scoped integration API keys."
---

# API Keys

API keys are designed for SaaS integrations and machine clients that do not use first-party user sessions.

The package stores:

- Public id for lookup.
- HMAC hash of the secret.
- Owner type and owner id.
- Optional creator type and creator id.
- Scopes and claims.
- Optional allowed IPs.
- Expiration and revocation state.

## Create an API Key

```php
use Sopheak\JwtAuth\DTO\ApiKeyContext;
use Sopheak\JwtAuth\Services\ApiKeyService;

$result = app(ApiKeyService::class)->createApiKey(new ApiKeyContext(
    ownerType: 'tenant',
    ownerId: '42',
    name: 'ERP sync',
    scopes: ['invoices.write'],
    claims: ['tenant_id' => 42],
));

$plaintext = $result->plaintextKey;
```

The plaintext key is returned only once. Store it in the client system immediately.

## Validate an API Key

```php
$principal = app(ApiKeyService::class)->validateApiKey(
    plaintextKey: $request->bearerToken(),
    ipAddress: $request->ip(),
);
```

`ApiKeyPrincipal` exposes:

- `apiKeyId`
- `ownerType`
- `ownerId`
- `scopes`
- `claims`
- `expiresAt`

## Protect Routes

```php
Route::middleware(['sp.api_key', 'sp.api_key.scope:invoices.write'])
    ->post('/integrations/invoices', Controller::class);
```

Use `sp.api_key.any_scope` when any one scope is acceptable.

## Rotate and Revoke

```php
$rotated = app(ApiKeyService::class)->rotateApiKey($apiKeyId);

app(ApiKeyService::class)->revokeApiKey($apiKeyId);

app(ApiKeyService::class)->revokeApiKeysForOwner('tenant', '42');
```

Rotation revokes the old key and returns a new plaintext key once.
