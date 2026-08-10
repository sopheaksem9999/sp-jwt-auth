# Changelog

All notable changes to `sopheak/sp-jwt-auth` will be documented in this file.
All notable changes to `sopheak/sp-jwt-auth` will be documented in this file.

## [0.1.18] - 2026-08-10

### Added
- `boot.json` package manifest so Laravel Boot and other agentic installers can discover and configure the package in client apps (install/setup/verify steps, optional modules, required env vars).
- `sp-jwt-auth:setup --skip-migrations` flag to publish configuration without copying package migrations.
- `sp-jwt-auth:validate --json` flag for machine-readable validation reports (`{"status":"ok"|"error","errors":[...],"warnings":[...]}`, exit code 0/1).
- `docs/ai/client-install.md` agent-facing client installation guide (publish, configure, migrate, verify, User model trait, optional modules).

### Changed
- `AGENTS.md` and `docs/ai/commands.md` document the new Boot manifest, setup flags, and JSON validation output.

## [Unreleased]

### Added
- `sp-jwt-auth.id_type` config (`SP_JWT_ID_TYPE`, `uuid|integer`, default `integer`): package `user_id` columns are created as `uuid` or `unsignedBigInteger` based on the client app's user primary-key type. Must be set before the first migrate.
- Core JWT package scaffold for Laravel 12 and 13.
- `sp-jwt` guard registration for first-party bearer token authentication.
- JWT access token issuing and validation with persisted `jti` records.
- Opaque refresh tokens with HMAC-hashed secrets, rotation, reuse detection, and revocation.
- Scope middleware and Passport-compatible `$user->token()` / `$user->tokenCan()` helpers.
- Signing key repository and JWKS output for public key discovery.
- Core install, key, JWKS, and prune Artisan commands.
- Account security brokers for MFA challenges, hashed OTP codes, email verification tokens, and password reset tokens.
- Sender contracts for app-owned OTP, email verification, and password reset delivery.
- Scoped API key issuing, validation, revocation, rotation, and resource middleware.
- External identity DTO/store and provider contract for Socialite/OIDC-style login flows.
- Optional OAuth server storage and services with clients, consents, authorization-code + PKCE, refresh tokens, client credentials, revocation, introspection, and resource middleware.
- Lifecycle events for account security, API keys, external identity, and OAuth server audit hooks.
- First-factor OTP sign-in/sign-up flow (`FirstFactorOtpBroker`): digest-only codes, prior-challenge invalidation, shared request/resend cooldown, atomic single-use verify, resolve-or-create via `FirstFactorUserResolver`, destination verified-at marking, and token pair issuance via `JwtTokenService`.
- Config-gated HTTP endpoints (`POST /otp/request`, `/otp/resend`, `/otp/verify`) with per-destination + per-IP rate limits and `Retry-After`.
- `OtpMessageFormatter` for config-driven byte-exact message templates.
- `FirstFactorOtpVerified/Failed/Locked/Expired` events.
- Config-gated JWT token endpoints (`POST /auth/token/refresh`, `POST /auth/token/revoke`) operating on the JWT-native token tables.
### Changed
- Updated package metadata and documentation for MIT licensing and public Packagist installation.
- Added community contribution, conduct, support, issue, pull request, security, and Dependabot files.
- Replaced realistic-looking documentation secrets with placeholder values.
- Added README badges, a pre-1.0 stability note, and a copy-paste JWT login and refresh quick start.
