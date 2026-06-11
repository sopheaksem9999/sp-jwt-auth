---
title: "Middleware"
description: "Route middleware provided by sopheak/sp-jwt-auth."
---

# Middleware

The package registers middleware aliases from the service provider.

## First-Party JWT

```php
Route::middleware(['sp.jwt'])->get('/me', Controller::class);
Route::middleware(['auth:api', 'sp.jwt.scope:reports.read'])->get('/reports', Controller::class);
Route::middleware(['auth:api', 'sp.jwt.any_scope:admin,support'])->get('/support', Controller::class);
```

Aliases:

- `sp.jwt`
- `sp.jwt.scope`
- `sp.jwt.any_scope`

`auth:api` remains the normal Laravel guard middleware. `sp.jwt` is a convenience wrapper around the configured package guard.

## API Keys

```php
Route::middleware(['sp.api_key'])->post('/integrations/ping', Controller::class);
Route::middleware(['sp.api_key', 'sp.api_key.scope:invoices.write'])->post('/integrations/invoices', Controller::class);
Route::middleware(['sp.api_key', 'sp.api_key.any_scope:admin,invoices.write'])->post('/integrations/admin', Controller::class);
```

Aliases:

- `sp.api_key`
- `sp.api_key.scope`
- `sp.api_key.any_scope`

The authenticated API key principal is stored on the request attribute `sp_api_key_principal`.

## OAuth Resource Tokens

```php
Route::middleware(['sp.oauth'])->get('/partner/me', Controller::class);
Route::middleware(['sp.oauth', 'sp.oauth.scope:invoices.read'])->get('/partner/invoices', Controller::class);
Route::middleware(['sp.oauth', 'sp.oauth.client:client-id'])->get('/partner/client-only', Controller::class);
```

Aliases:

- `sp.oauth`
- `sp.oauth.scope`
- `sp.oauth.any_scope`
- `sp.oauth.client`

The authenticated OAuth principal is stored on the request attribute `sp_oauth_principal`.
