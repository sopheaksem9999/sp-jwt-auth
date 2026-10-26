---
title: "Configuration"
description: "Configuration reference for core and optional sp-jwt-auth modules."
---

# Configuration

The publishable config file is `config/sp-jwt-auth.php`.

```bash
php artisan vendor:publish --tag=sp-jwt-auth-config
```

## Core JWT

Important core keys:

- `guard`: Laravel guard name, default `api`.
- `driver`: guard driver, default `sp-jwt`.
- `user_provider`: Laravel user provider, default `users`.
- `issuer`: JWT issuer claim.
- `audience`: optional JWT audience claim.
- `algorithm`: signing algorithm, default `RS256`.
- `access_ttl_minutes`: access JWT lifetime.
- `refresh_ttl_days`: refresh token lifetime.
- Refresh tokens always rotate; reuse of a rotated token is handled by `reuse_detection`.
- `clock_skew_seconds`: leeway (seconds) applied when validating `exp`, `nbf` and `iat`.
- `reuse_detection`: action after refresh token reuse, default `revoke_session`.

## Signing Keys

`keys.active_kid` selects the signing key. `keys.items` stores active, previous, retired, or compromised keys.

Do not use `APP_KEY` as the JWT signing key.

## Hash Keys

Refresh tokens, OTP codes, verification tokens, reset tokens, API keys, and OAuth secrets are stored as HMAC hashes. `hash_keys.active_id` selects the active hash key.

## Optional Modules

Optional module sections:

- `mfa`
- `first_factor_otp`
- `token_endpoints`
- `email_verification`
- `password_reset`
- `api_keys`
- `external_identities`
- `oauth_server`

Each optional module can be enabled independently. Core JWT does not require their dependencies, migrations, or runtime paths for normal first-party auth.

> **Informational flags.** `mfa.enabled`, `mfa.otp.enabled`, `email_verification.enabled`, `password_reset.enabled`, `api_keys.enabled` and `external_identities.enabled` are reported by `sp-jwt-auth:mcp` / `sp-jwt-auth:validate` but do not switch the services off; the services are available whenever their tables exist. Route-based modules (`first_factor_otp`, `token_endpoints`, `oauth_server`) are gated by their `enabled` flag.
> `api_keys.max_ttl_days` caps key lifetime, and keys without an expiry get `default_ttl_days` (or `max_ttl_days` unless `allow_never_expires` is true). `allowed_ips` accepts single IPs and CIDR ranges (IPv4/IPv6); an empty list or an unknown request IP rejects the key.
