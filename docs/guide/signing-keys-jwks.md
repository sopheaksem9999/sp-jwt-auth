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

## Configure Active Key

Set the active key id:

```env
SP_JWT_ACTIVE_KID=2026-06-primary
```

The active key signs new JWTs. Previous keys (state `previous`, or listed in `SP_JWT_PREVIOUS_KIDS`) can still verify old JWTs until those tokens expire; remove a previous key once the longest-lived token signed by it has expired. Keys in state `compromised` (or listed in `SP_JWT_COMPROMISED_KIDS`) are rejected immediately and left out of the JWKS.

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
