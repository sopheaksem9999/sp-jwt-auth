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
{"refresh_token": "0f8c3b52-9a41-4f6e-8d27-1c5e7a9b3d04.9b1f…64 hex characters…"}
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

## Refresh token in an httpOnly cookie

Browser apps can keep the refresh token out of JavaScript. Set `SP_JWT_REFRESH_TRANSPORT`:

| Value | Behaviour |
|---|---|
| `body` (default) | Unchanged: the refresh token is in the JSON body, in and out. |
| `cookie` | The refresh token is only ever in an httpOnly cookie. Login, refresh and the OTP verify step still return `access_token`, `token_type` and `expires_in` in the body, but no `refresh_token`. A `refresh_token` body field is ignored. |
| `both` | Body and cookie, for migrating clients. |

```env
SP_JWT_REFRESH_TRANSPORT=cookie
SP_JWT_REFRESH_COOKIE_NAME=sp_refresh_token
SP_JWT_REFRESH_COOKIE_SAME_SITE=strict   # strict | lax | none
SP_JWT_REFRESH_COOKIE_SECURE=true
SP_JWT_REFRESH_COOKIE_DOMAIN=
SP_JWT_REFRESH_COOKIE_PATH=              # default /{route_prefix}/token
SP_JWT_REFRESH_CSRF_ENABLED=true
SP_JWT_REFRESH_CSRF_HEADER=X-SP-CSRF
SP_JWT_REFRESH_CSRF_COOKIE=sp_csrf
```

- **Rotation:** every `POST /auth/token/refresh` sets the new cookie in the same response that revokes the old token. Reuse detection is unchanged; a failed cookie refresh returns 401 and clears the cookie.
- **Logout:** `POST /auth/token/revoke` works with the access token as before, or with the cookie alone (so logout still works after the access token expired). It clears the cookie even if the token was already revoked.
- **CSRF (double-submit):** with the cookie as the credential, every response that sets the refresh cookie also sets a JavaScript-readable `sp_csrf` cookie **and returns the same value in the `X-SP-CSRF` response header**. The client echoes it in the `X-SP-CSRF` request header on refresh and revoke (the value changes on each rotation, so use the last response's). A missing or wrong header returns **403** (not 401, which clients read as "session ended"). Requests authenticated by a bearer token or a body field need no CSRF header. `SameSite=strict` is the default.
- **Same-site subdomains** (`app.example.com` calling `api.example.com`): the cookies are host-only by default, so either set `SP_JWT_REFRESH_COOKIE_DOMAIN=.example.com` or have the client use the value from the response header.
- **Cross-site (SPA on another origin):** set `SAME_SITE=none` (forces `Secure`), and configure CORS with `supports_credentials => true`, exact allowed origins (no `*`) and **`exposed_headers => ['X-SP-CSRF']`** (the configured header name). The SPA cannot read the API's cookie, so it must take the CSRF value from that header; without `exposed_headers` every cookie refresh returns 403. The client must send `credentials: 'include'`.
- **Host login routes:** wrap your own login/refresh JSON response with `TokenResponse::withRefreshCookie($response, $pair)`. It is a no-op in `body` mode, so the route can call it unconditionally; in `cookie` mode it also removes `refresh_token` from the body.
- **Encryption:** the package routes are not in the `web` group, so the cookie is plain. If you register them inside a group that uses `EncryptCookies`, exclude the refresh and CSRF cookie names.
