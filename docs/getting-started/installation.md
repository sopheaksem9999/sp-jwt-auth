---
title: "Installation"
description: "Install and configure sopheak/sp-jwt-auth in a Laravel app."
---

# Installation

```bash
composer require sopheak/sp-jwt-auth
php artisan sp-jwt-auth:install
php artisan migrate
```

## Generate Signing Keys

```bash
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary --pem --write-env
```

This creates `storage/jwt-private-2026-06-primary.pem` and `storage/jwt-public-2026-06-primary.pem`, then writes `SP_JWT_ACTIVE_KID` to `.env`.

## Configure the API Guard

Keep the normal `web` guard for Blade, Livewire, Inertia, and session pages. Use `sp-jwt` for API routes.

```php
'guards' => [
    'api' => [
        'driver' => 'sp-jwt',
        'provider' => 'users',
    ],
],
```

## Register the Key in Config

Add the generated key to `config/sp-jwt-auth.php` under `keys.items`:

```php
'keys' => [
    'active_kid' => env('SP_JWT_ACTIVE_KID'),
    'items' => [
        '2026-06-primary' => [
            'state' => 'active',
            'private_key_path' => base_path('storage/jwt-private-2026-06-primary.pem'),
            'public_key_path' => base_path('storage/jwt-public-2026-06-primary.pem'),
        ],
    ],
],
```

## Publish Config (Optional)

```bash
php artisan vendor:publish --tag=sp-jwt-auth-config
```

## Environment

```env
SP_JWT_ACTIVE_KID=2026-06-primary
SP_JWT_ISSUER=https://your-app.test
SP_JWT_ACCESS_TTL_MINUTES=15
SP_JWT_REFRESH_TTL_DAYS=30
SP_JWT_REFRESH_HASH_KEY=change-me-to-a-long-random-secret
```

## Next Steps

- [Quick Start](./quick-start.md)
- [Configuration](../core-concepts/configuration.md)
- [Core JWT Tokens](../core-concepts/core-jwt.md)
