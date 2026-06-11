# Changelog

All notable changes to `sopheak/sp-jwt-auth` will be documented in this file.

## [Unreleased]

### Added
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
<<<<<<< HEAD

### Changed
- Updated package metadata and documentation for MIT licensing and public Packagist installation.
- Added community contribution, conduct, support, issue, pull request, security, and Dependabot files.
- Replaced realistic-looking documentation secrets with placeholder values.
- Added README badges, a pre-1.0 stability note, and a copy-paste JWT login and refresh quick start.
=======
>>>>>>> 11e06a7 (feat: add complete Laravel JWT auth package with OAuth support)
