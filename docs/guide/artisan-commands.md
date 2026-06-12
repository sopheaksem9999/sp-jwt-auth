---
title: "Artisan Commands"
description: "Command reference for sopheak/sp-jwt-auth."
---

# Artisan Commands

## Install

```bash
php artisan sp-jwt-auth:install --keys
```

Publishes package config and migrations. With `--keys`, it also generates signing key material.

## Setup

```bash
php artisan sp-jwt-auth:setup --keys
php artisan sp-jwt-auth:setup --force
```

Publishes client scaffolding, attempts to add the `sp-jwt` API guard to `config/auth.php`, and optionally generates local PEM key files. With `--keys`, it writes `SP_JWT_ACTIVE_KID`, `SP_JWT_PRIVATE_KEY_PATH`, `SP_JWT_PUBLIC_KEY_PATH`, and `SP_JWT_REFRESH_HASH_KEY` to `.env`. Use `--skip-auth-guard` when the host app has a custom auth config and you want to add the guard manually.

## Validate

```bash
php artisan sp-jwt-auth:validate
php artisan sp-jwt-auth:validate --fix
```

Checks the configured guard, user provider, active signing key, hash key, and JWKS route. With `--fix`, it republishes package config/migrations and attempts the same safe `config/auth.php` guard patch as setup.

## Keys

```bash
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary --pem --write-env
php artisan sp-jwt-auth:keys --rotate --kid=2026-07-primary
php artisan sp-jwt-auth:keys --retire --kid=2026-06-primary
php artisan sp-jwt-auth:keys --revoke --kid=2026-06-primary --compromised
```

Use this command to manage JWT signing key lifecycle.

Flags:

- `--pem`: Output `.pem` files instead of `.key`
- `--write-env`: Automatically write `SP_JWT_ACTIVE_KID` to `.env`
- `--force`: Overwrite existing key files
- `--path`: Output directory (default: `storage`)

## JWKS

```bash
php artisan sp-jwt-auth:jwks --pretty
php artisan sp-jwt-auth:jwks --output=storage/app/jwks.json
php artisan sp-jwt-auth:jwks --active-only
```

Prints or writes public JWKS payloads.

## Prune

```bash
php artisan sp-jwt-auth:prune --expired-days=30 --revoked-days=30
php artisan sp-jwt-auth:prune --dry-run
```

Deletes old expired or revoked token rows.
