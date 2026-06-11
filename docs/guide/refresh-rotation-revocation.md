---
title: "Refresh Rotation and Revocation"
description: "Rotate refresh tokens and revoke access tokens, sessions, or users."
---

# Refresh Rotation and Revocation

Refresh tokens are opaque `id.secret` values. Only the HMAC hash of the secret is stored in `sp_jwt_refresh_tokens`.

## Rotate a Refresh Token

```php
use Sopheak\JwtAuth\Services\JwtTokenService;

$pair = app(JwtTokenService::class)->rotateRefreshToken(
    $request->input('refresh_token'),
);
```

Rotation behavior:

- Validates the refresh token id and secret.
- Locks the refresh row in a transaction.
- Revokes the old refresh token.
- Revokes the previous access token.
- Issues a new access and refresh pair.
- Links the old refresh row to `replaced_by_id`.

## Reuse Detection

If a revoked refresh token is used again, `RefreshTokenReuseDetected` is dispatched.

The `reuse_detection` config controls the response:

- `revoke_session`: revoke all tokens in the session.
- `revoke_user`: revoke all tokens for the user.
- other values: do not cascade.

## Revoke One Access Token

```php
app(JwtTokenService::class)->revokeAccessToken($jti);
```

## Revoke One Session

```php
app(JwtTokenService::class)->revokeSession($sessionId);
```

## Revoke All User Tokens

```php
app(JwtTokenService::class)->revokeAllForUser($user);
```

Revocation updates persisted token rows. JWT validation still checks the database row, so a signed JWT can be made inactive before its `exp`.
