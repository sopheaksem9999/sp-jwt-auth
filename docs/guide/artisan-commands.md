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

## Keys

```bash
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary --pem --env
php artisan sp-jwt-auth:keys --rotate --kid=2026-07-primary
php artisan sp-jwt-auth:keys --retire --kid=2026-06-primary
php artisan sp-jwt-auth:keys --revoke --kid=2026-06-primary --compromised
```

Use this command to manage JWT signing key lifecycle.

Flags:

- `--pem`: Output `.pem` files instead of `.key`
- `--env`: Automatically write `SP_JWT_ACTIVE_KID` to `.env`
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
