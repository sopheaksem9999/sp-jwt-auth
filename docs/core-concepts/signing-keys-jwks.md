---
title: "Signing Keys and JWKS"
description: "Generate signing keys, rotate key ids, and publish JWKS."
---

# Signing Keys and JWKS

Access JWTs are signed with package-configured signing keys. The package supports active, previous, retired, and compromised key states through `keys.items`.

## Generate Keys

```bash
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary
```

This writes the generated key paths and active key id to `.env` by default. It also creates `SP_JWT_REFRESH_HASH_KEY` when the app does not have one yet.

For environments that manage `.env` outside Artisan, use `--no-write-env` and copy the printed values into your deployment secret manager.

## Configure Active Key

Set the active key id:

```env
SP_JWT_ACTIVE_KID=2026-06-primary
SP_JWT_PRIVATE_KEY_PATH=storage/jwt-private-2026-06-primary.key
SP_JWT_PUBLIC_KEY_PATH=storage/jwt-public-2026-06-primary.key
SP_JWT_REFRESH_HASH_KEY=your-random-refresh-hash-secret
```

The active key signs new JWTs. Previous keys can still verify old JWTs during a rotation grace period.

## JWKS

JWKS exposes public key material only.

```bash
php artisan sp-jwt-auth:jwks --pretty
```

When enabled, the package registers:

```text
/.well-known/sp-jwt-auth/jwks.json
```

## Security Notes

- Do not sign JWTs with `APP_KEY`.
- Do not expose private keys through JWKS.
- Mark compromised key ids in config so verification rejects them.
- Rotate keys before expiration or suspected exposure.
