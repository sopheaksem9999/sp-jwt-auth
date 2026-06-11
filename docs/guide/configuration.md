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
- `rotate_refresh_tokens`: refresh tokens rotate by default.
- `reuse_detection`: action after refresh token reuse, default `revoke_session`.

## Signing Keys

`keys.active_kid` selects the signing key. `keys.items` stores active, previous, retired, or compromised keys.

Do not use `APP_KEY` as the JWT signing key.

## Hash Keys

Refresh tokens, OTP codes, verification tokens, reset tokens, API keys, and OAuth secrets are stored as HMAC hashes. `hash_keys.active_id` selects the active hash key.

## Optional Modules

Optional module sections:

- `mfa`
- `email_verification`
- `password_reset`
- `api_keys`
- `external_identities`
- `oauth_server`

Each optional module can be enabled independently. Core JWT does not require their dependencies, migrations, or runtime paths for normal first-party auth.
