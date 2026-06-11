# Architecture

`sopheak/sp-jwt-auth` is a Laravel package for first-party API authentication. It integrates with Laravel's auth manager through a custom `sp-jwt` guard while leaving the application's `web` guard and product-specific login flows untouched.

## Core Flow

1. The application validates credentials and resolves an `Authenticatable` user.
2. The application builds a `TokenContext` with scopes, claims, subject, device, and session metadata.
3. `JwtTokenService::issueTokenPair()` persists an access token row, signs a JWT with a configured `kid`, persists a hashed refresh token row, and returns the plaintext token pair.
4. API routes protected by `auth:api` use `JwtGuard` to validate the bearer JWT through `JwtTokenService`.
5. The guard resolves the user through Laravel's configured user provider and attaches the persisted token record to the user with `HasJwtTokens`.
6. Refresh calls use `JwtTokenService::rotateRefreshToken()` to revoke the old token family member, issue a new pair, and link the rotation chain with `replaced_by_id`.

## Storage

- `sp_jwt_access_tokens`: persisted JWT `jti`, user morph pair, session id, scopes, claims, key id, expiry, and revocation state.
- `sp_jwt_refresh_tokens`: opaque refresh token id, hashed secret, hash key id, session id, copied scopes/claims, replacement pointer, expiry, and revocation state.

User ownership uses `user_type` and `user_id` rather than foreign keys so the package works with normal Laravel Eloquent providers and custom authenticatable models.

## Security Boundaries

- JWT signing keys are package-configured and must not use `APP_KEY`.
- Refresh token secrets are returned once and stored only as HMAC hashes.
- Access token validation checks signature, `kid`, issuer, configured audience, expiry, persisted `jti`, DB expiry, and revocation state.
- Refresh token rotation runs inside a DB transaction and detects reuse.
- JWKS exposes public key material only.

## Account Security Storage

- `sp_jwt_mfa_challenges`: pending MFA challenges and serialized token context.
- `sp_jwt_mfa_otp_codes`: hashed OTP codes, masked destinations, attempts, expiry, and verification state.
- `sp_jwt_email_verification_tokens`: hashed email verification tokens and email hashes.
- `sp_jwt_password_reset_tokens`: hashed password reset tokens, attempts, expiry, and consumed state.

Delivery remains app-owned through sender contracts.

## API Key Storage

- `sp_jwt_api_keys`: public id lookup, hashed secret, owner context, scopes, claims, allowed IPs, expiry, revocation, and last-use tracking.

API keys authenticate to `ApiKeyPrincipal`, not a Laravel user.

## External Identity Storage

- `sp_jwt_external_identities`: provider identity link, optional local user link, normalized profile data, and optional provider tokens.

The package normalizes and stores identities. The app decides account linking and user creation.

## OAuth Storage

OAuth server mode uses separate tables from first-party JWT:

- `sp_oauth_clients`
- `sp_oauth_consents`
- `sp_oauth_auth_codes`
- `sp_oauth_access_tokens`
- `sp_oauth_refresh_tokens`

OAuth resource APIs use `sp.oauth` middleware and authenticate to `OAuthPrincipal`. Client-credentials tokens have client context and no user id.

## Optional Modules

MFA/OTP, email verification, password reset, API keys, external identity, and OAuth server support are separate modules. They must not be required by the Core JWT install path.
