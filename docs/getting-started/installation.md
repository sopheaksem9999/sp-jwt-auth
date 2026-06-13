---
title: "Installation"
description: "Install and configure sopheak/sp-jwt-auth in a Laravel app."
---

# Installation

This package is installed from a private Git repository. In the host Laravel application's `composer.json`, manually add a VCS repository entry:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/<org>/<repo>.git"
    }
  ]
}
```

If Composer or Git asks for authentication, use your GitHub username and a personal access token with read access to this repository. Do not put access tokens directly in `composer.json`; Composer can store credentials in `auth.json` after the prompt.

```bash
composer require sopheak/sp-jwt-auth:^0.1
php artisan sp-jwt-auth:setup --keys
php artisan migrate
php artisan sp-jwt-auth:validate
```

For local path testing before a Git tag exists:

```bash
composer config repositories.sp-jwt-auth '{"type":"path","url":"/absolute/path/to/sp-jwt-auth","options":{"versions":{"sopheak/sp-jwt-auth":"0.1.0"}}}'
composer require sopheak/sp-jwt-auth:^0.1
```

## Generate Signing Keys

```bash
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary --pem
```

This creates `storage/jwt-private-2026-06-primary.pem` and `storage/jwt-public-2026-06-primary.pem`, then writes `SP_JWT_ACTIVE_KID`, `SP_JWT_PRIVATE_KEY_PATH`, and `SP_JWT_PUBLIC_KEY_PATH` to `.env`. It also creates `SP_JWT_REFRESH_HASH_KEY` when the key is missing, without overwriting an existing refresh hash secret.

## Configure the API Guard

`sp-jwt-auth:setup` attempts to add this automatically. If your auth config is custom, add it manually. Keep the normal `web` guard for Blade, Livewire, Inertia, and session pages. Use `sp-jwt` for API routes.

```php
'guards' => [
    'api' => [
        'driver' => 'sp-jwt',
        'provider' => 'users',
    ],
],
```

## Register the Key in Config

The default published config reads generated key paths from `.env`:

```env
SP_JWT_ACTIVE_KID=2026-06-primary
SP_JWT_PRIVATE_KEY_PATH=storage/jwt-private-2026-06-primary.pem
SP_JWT_PUBLIC_KEY_PATH=storage/jwt-public-2026-06-primary.pem
SP_JWT_REFRESH_HASH_KEY=781578bb741cc355a3315f7bc9fa20877570b8f04aa7f4f2afd016c8ae854453
```

For custom key storage, replace `keys.items` in `config/sp-jwt-auth.php` with explicit inline keys or paths.

## Publish Config (Optional)

```bash
php artisan vendor:publish --tag=sp-jwt-auth-config
```

## Environment

```env
SP_JWT_ACTIVE_KID=2026-06-primary
SP_JWT_PRIVATE_KEY_PATH=storage/jwt-private-2026-06-primary.pem
SP_JWT_PUBLIC_KEY_PATH=storage/jwt-public-2026-06-primary.pem
SP_JWT_ISSUER=https://your-app.test
SP_JWT_ACCESS_TTL_MINUTES=15
SP_JWT_REFRESH_TTL_DAYS=30
SP_JWT_REFRESH_HASH_KEY=781578bb741cc355a3315f7bc9fa20877570b8f04aa7f4f2afd016c8ae854453
```

## Next Steps

- [Quick Start](./quick-start.md)
- [Configuration](../core-concepts/configuration.md)
- [Core JWT Tokens](../core-concepts/core-jwt.md)
