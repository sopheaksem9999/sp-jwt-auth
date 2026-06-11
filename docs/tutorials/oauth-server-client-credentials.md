---
title: "OAuth Server — Client Credentials"
description: "Machine-to-machine OAuth client credentials grant for service integrations."
---

# OAuth Server — Client Credentials

The client credentials grant is for machine-to-machine communication where no user is involved. The client authenticates itself and receives a token scoped to its own permissions.

## 1. Create an M2M Client

```php
use Sopheak\JwtAuth\DTO\OAuthClientData;
use Sopheak\JwtAuth\Services\OAuthClientRepository;

$secret = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
    name: 'Data Sync Worker',
    allowedGrants: ['client_credentials'],
    allowedScopes: ['invoices.read', 'reports.generate'],
    confidential: true,
));

$clientId = $secret->client->id;
$clientSecret = $secret->plaintextSecret;

// Store $clientSecret securely. It will never be returned again.
```

The client can optionally be owned by a tenant or organization:

```php
$secret = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
    name: 'Tenant 42 Sync Worker',
    allowedGrants: ['client_credentials'],
    allowedScopes: ['invoices.read'],
    ownerType: 'tenant',
    ownerId: '42',
));
```

## 2. Issue a Token

```bash
curl -X POST https://api.example.com/oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials" \
  -d "client_id=$CLIENT_ID" \
  -d "client_secret=$CLIENT_SECRET" \
  -d "scope=invoices.read reports.generate"
```

```json
{
    "access_token": "spoat_abc123...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "scopes": ["invoices.read", "reports.generate"]
}
```

The server verifies the client secret against the HMAC hash, validates the requested scopes, and issues a token.

## 3. Use the Token

```bash
curl -X GET https://api.example.com/partner/invoices \
  -H "Authorization: Bearer spoam_abc123..."
```

## 4. Protect Routes

```php
Route::middleware(['sp.oauth', 'sp.oauth.scope:invoices.read'])
    ->get('/partner/invoices', PartnerInvoiceController::class);
```

## 5. Access the Principal

```php
$principal = $request->attributes->get('sp_oauth_principal');

$principal->grantType;  // 'client_credentials'
$principal->clientId;   // UUID of the client
$principal->userId;     // null (no user involved)
$principal->scopes;     // ['invoices.read', 'reports.generate']
$principal->can('invoices.read'); // true
```

## 6. Rotate the Client Secret

```php
$newSecret = app(OAuthClientRepository::class)->rotateSecret($clientId);
// $newSecret->plaintextSecret is returned once
```

## 7. Revoke the Client

```php
app(OAuthClientRepository::class)->revokeClient($clientId);
// All tokens for this client are immediately invalidated
```

## Key Differences from Authorization Code

| Aspect | Authorization Code | Client Credentials |
|---|---|---|
| User involved | Yes | No |
| `user_id` on token | Set to approving user | `null` |
| Refresh tokens | Yes | No |
| PKCE | Required for public clients | N/A |
| Typical use | Third-party apps | Internal services, cron jobs, webhooks |
