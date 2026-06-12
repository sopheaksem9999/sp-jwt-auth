---
title: "API Key Client Usage"
description: "Create and use scoped API keys for third-party integrations."
---

# API Key Client Usage

API keys are for machine clients, background jobs, and third-party integrations that do not use first-party user sessions. They are a good fit for service-to-service workers such as ERP or QuickBooks sync jobs that need package-level authentication to your API.

## Server-Side: Create a Key

```php
use Sopheak\JwtAuth\DTO\ApiKeyContext;
use Sopheak\JwtAuth\Services\ApiKeyService;

$result = app(ApiKeyService::class)->createApiKey(new ApiKeyContext(
    ownerType: 'company',
    ownerId: '42',
    name: 'QuickBooks sync worker',
    scopes: ['qbo.sync', 'invoices.read', 'invoices.write'],
    claims: ['company_id' => 42],
    allowedIps: ['203.0.113.0/24'],
));

// Return once — store immediately on the client
$plaintext = $result->plaintextKey;
// Format: spak_{publicId}.{secret}
```

## Client-Side: Use the Key

The client sends the key as a Bearer token:

```bash
curl -X POST https://api.example.com/integrations/invoices \
  -H "Authorization: Bearer spak_abc123def456.7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d" \
  -H "Content-Type: application/json" \
  -d '{"amount": 100}'
```

## Server-Side: Protect Routes

```php
Route::middleware(['sp.api_key', 'sp.api_key.scope:invoices.write'])
    ->post('/integrations/invoices', IntegrationInvoiceController::class);

Route::middleware(['sp.api_key', 'sp.api_key.scope:qbo.sync'])
    ->post('/integrations/qbo/sync', QboSyncController::class);
```

Available middleware:

| Alias | Purpose |
|---|---|
| `sp.api_key` | Authenticate API key bearer token |
| `sp.api_key.scope:<scope>` | Require every listed scope |
| `sp.api_key.any_scope:<scope1>,<scope2>` | Require any listed scope |

## Access the Principal

```php
$principal = $request->attributes->get('sp_api_key_principal');

$principal->ownerType;   // 'company'
$principal->ownerId;     // '42'
$principal->scopes;      // ['qbo.sync', 'invoices.write', 'invoices.read']
$principal->claims;      // ['company_id' => 42]
```

Use the owner fields for the entity that owns the integration key. Use claims for request-time context that middleware and controllers need to read.

## Rotate and Revoke

```php
// Rotate (old key is revoked, new plaintext key returned once)
$rotated = app(ApiKeyService::class)->rotateApiKey($apiKeyId);

// Revoke a single key
app(ApiKeyService::class)->revokeApiKey($apiKeyId);

// Revoke all keys for an owner
app(ApiKeyService::class)->revokeApiKeysForOwner('tenant', '42');
```

## Key Format

API keys follow the format `{prefix}_{publicId}.{secret}`:

- `prefix`: configured by `api_keys.prefix` (default `spak`)
- `publicId`: 16-character lookup key (stored in plaintext)
- `secret`: 32-byte random value (stored as HMAC hash)

The full key is returned only at creation and rotation time.
