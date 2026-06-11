---
title: "OAuth Server"
description: "Enable third-party OAuth clients with authorization-code + PKCE and client credentials."
---

# OAuth Server

OAuth server mode is optional and disabled by default. It is for third-party clients, consent, and integration APIs.

OAuth server tokens use separate `sp_oauth_*` tables and `sp.oauth` middleware. They do not share first-party `sp_jwt_*` token storage.

## Enable OAuth Server Mode

```env
SP_JWT_OAUTH_SERVER_ENABLED=true
```

Routes are registered under `oauth_server.route_prefix`, default `oauth`.

## Create a Client

```php
use Sopheak\JwtAuth\DTO\OAuthClientData;
use Sopheak\JwtAuth\Services\OAuthClientRepository;

$secret = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
    name: 'ERP Connector',
    redirectUris: ['https://client.example/callback'],
    allowedGrants: ['authorization_code', 'refresh_token'],
    allowedScopes: ['invoices.read'],
));
```

Confidential client secrets are returned once and stored as HMAC hashes.

## Authorization Code + PKCE

Validate the incoming authorization request:

```php
$authorization = app(OAuthServerService::class)
    ->validateAuthorizationRequest($request);
```

After the app-owned consent UI approves access:

```php
use Sopheak\JwtAuth\DTO\OAuthConsentContext;

$code = app(OAuthServerService::class)->approveAuthorizationRequest(
    $authorization,
    $user,
    new OAuthConsentContext(scopes: ['invoices.read'], remember: true),
);
```

The client exchanges the code at:

```text
POST /oauth/token
```

Authorization codes are one-time use and expire quickly.

## Client Credentials

Create a client with `client_credentials` grant:

```php
$secret = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
    name: 'M2M Client',
    allowedGrants: ['client_credentials'],
    allowedScopes: ['invoices.read'],
));
```

The token endpoint accepts:

```text
grant_type=client_credentials
client_id=...
client_secret=...
scope=invoices.read
```

Client-credentials tokens authenticate as clients, not users.

## Protect Resource Routes

```php
Route::middleware(['sp.oauth', 'sp.oauth.scope:invoices.read'])
    ->get('/partner/invoices', Controller::class);
```

Available middleware:

- `sp.oauth`
- `sp.oauth.scope:<scope>`
- `sp.oauth.any_scope:<scope1>,<scope2>`
- `sp.oauth.client:<client_id>`

## Revoke and Introspect

```php
app(OAuthServerService::class)->revokeToken($token);

$payload = app(OAuthServerService::class)->introspect($token);
```
