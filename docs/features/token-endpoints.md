---
title: "JWT Token Endpoints"
description: "HTTP endpoints for rotating refresh tokens and revoking sessions."
---

# JWT Token Endpoints

The token endpoints module exposes HTTP routes for refresh rotation and session revocation. They operate on the JWT-native `sp_jwt_access_tokens` and `sp_jwt_refresh_tokens` tables — no OAuth client is involved.

The module is config-gated and disabled by default.

## Enable the Module

Set `token_endpoints.enabled` or the `SP_JWT_TOKEN_ENDPOINTS_ENABLED` env var to `true`:

```bash
SP_JWT_TOKEN_ENDPOINTS_ENABLED=true
```

## Configuration

| Key | Env var | Default | Description |
| --- | --- | --- | --- |
| `token_endpoints.enabled` | `SP_JWT_TOKEN_ENDPOINTS_ENABLED` | `false` | Enable the module and register its routes. |
| `token_endpoints.route_prefix` | `SP_JWT_TOKEN_ENDPOINTS_ROUTE_PREFIX` | `auth` | URL prefix for the token routes. |
| `token_endpoints.response_envelope` | `SP_JWT_TOKEN_ENDPOINTS_RESPONSE_ENVELOPE` | `raw` | Response wrapping: `raw`, `laravel` (`{"data": ...}`), or a class implementing `Sopheak\JwtAuth\Contracts\ResponseEnvelope`. |

## Refresh a Token

`POST /auth/token/refresh` is unauthenticated by design — it recovers from an expired access token using a JWT-native refresh token.

Request:

```json
{"refresh_token": "r1.eyJhbGciOiJIUzI1NiJ9.secret"}
```

Response `200`:

```json
{
  "access_token": "eyJhbGciOiJSUzI1NiJ9...",
  "refresh_token": "r2.eyJhbGciOiJIUzI1NiJ9.secret",
  "token_type": "Bearer",
  "expires_in": 900
}
```

The endpoint rotates the refresh token exactly like `JwtTokenService::rotateRefreshToken()`: it locks the refresh row in a transaction, revokes the old refresh token, revokes the previous access token, issues a new access/refresh pair, and links the old refresh row via `replaced_by_id`.

Reuse detection applies: presenting an already rotated or revoked refresh token triggers the configured `reuse_detection` behavior and returns a generic `401`. The response does not reveal whether the token was invalid, reused, or expired.

## Revoke a Session

`POST /auth/token/revoke` requires a valid bearer access token. It is protected by `auth:api` — the package's `sp-jwt` guard.

Request:

```json
{}
```

Response `200`:

```json
{}
```

Revocation covers the whole session: all access and refresh rows for the current access token's `session_id` are revoked. Without a valid bearer token the endpoint returns `401`.
