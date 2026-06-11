---
title: "Troubleshooting"
description: "Common issues and resolutions for sopheak/sp-jwt-auth."
---

# Troubleshooting

## Token Validation Fails

**Symptom:** `Token invalid` or `Unable to parse token` on protected routes.

Check these in order:

1. Is the `api` guard configured to use `sp-jwt` in `config/auth.php`?
2. Is `SP_JWT_ACTIVE_KID` set to a key id that exists in config?
3. Does the signing key exist and match the algorithm? Run `php artisan sp-jwt-auth:keys --generate --kid=test` if needed.
4. Is the token expired? Default access TTL is 15 minutes.
5. Is the `jti` row still active in `sp_jwt_access_tokens`? Revoked rows return 401.

## Refresh Token Rejected

**Symptom:** `Invalid refresh token` on rotate.

- The `id.secret` format must be passed as-is. Check the client sends the full value.
- The token may have been rotated already — reuse detection fires on second use.
- The `hash_key_id` used to store the secret must still be active in config.

## Guard Not Found

**Symptom:** `auth guard [api] is not defined` or `driver [sp-jwt] not found`.

Run the install command to register the service provider:

```bash
php artisan sp-jwt-auth:install
```

The provider registers the `sp-jwt` guard driver in `boot()`. Verify it is loaded:

```bash
php artisan route:middleware | grep sp.jwt
```

## JWKS Endpoint Returns Empty

- Confirm signing keys exist in config under `keys.items`.
- Only keys in `active` or `previous` state are included.
- Run `php artisan sp-jwt-auth:jwks --debug` to inspect.

## OAuth Server Routes Not Registered

- Set `SP_JWT_OAUTH_SERVER_ENABLED=true`.
- Verify the config `oauth_server.enabled` is `true`.
- Routes are registered under the configured prefix (default `oauth`).

## Reuse Detection Fires Unexpectedly

The refresh token was already consumed. Common causes:

- Client retries the same refresh token after a network timeout.
- Client stores old refresh token after rotation.
- Concurrent refresh requests race on the same token.

The `reuse_detection` config controls the cascade behaviour.
