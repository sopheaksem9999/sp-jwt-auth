# Changelog

All notable changes to `sopheak/sp-jwt-auth` will be documented in this file.

## [Unreleased]

### Security
- Upgrade the PHP runtime on every server that handles web requests to 8.5.12, 8.4.27, or 8.3.36 (the versions this package supports), or install your distro's or LTS vendor's patched build. These releases fix two critical remote code execution vulnerabilities in the PHP engine (CVE-2026-109618, CVE-2026-109619). PHP-FPM, Apache with mod_php, php-cgi, FrankenPHP, and LiteSpeed are affected; restart workers after upgrading. Neither CVE is a flaw in this package, so no package update is needed.
- `JwtGuard` now rejects an access token whose `user_type` differs from the model class returned by the guard's user provider. Previously a token issued to one model (e.g. `Admin` #5) could authenticate as another model's record with the same ID (`User` #5).
- `POST /oauth/revoke` and `/oauth/introspect` now require client authentication, and a client can only revoke or introspect its own tokens.
- `external_identities.encrypt_provider_tokens` (default `true`) is now honoured: provider tokens are encrypted at rest with Laravel's `Crypt`. Rows written earlier stay readable and are encrypted on their next write.
- API key `allowed_ips` now fails closed when the request IP is unknown.

### Fixed
- `sp-jwt-auth:prune` no longer deletes refresh tokens that are still valid. Pruning an old access token cascade-deleted its refresh token, which could log out a user who had been idle for more than `--expired-days`. Access tokens with a live refresh token are now kept.
- `sp-jwt-auth:prune --dry-run` no longer counts rows that are both expired and revoked twice.
- OAuth `expires_in` was always `0` under Carbon 3; it now reports the real remaining seconds.
- `JwtTokenService::activeSessionsForUser()` lists a session while its refresh token is valid (one entry per session) instead of hiding sessions idle longer than the access TTL.
- Refresh rotation keeps `device_id` / `device_name`, so `revokeDevice()` still finds refreshed sessions.
- `ExternalIdentityStore::store($identity)` without a user no longer unlinks an existing account.
- Marking a key `compromised` no longer breaks the JWKS endpoint; the key is left out and tokens signed with it are rejected.
- API key `allowed_ips` accepts CIDR ranges and IPv6 (`Symfony\IpUtils`).
- OAuth client errors (unknown grant, disallowed scope, malformed token) no longer return HTTP 500 (see Changed).
- `POST /auth/token/revoke` uses the configured `sp-jwt-auth.guard` instead of a hard-coded `api` and returns `{}` instead of `[]`.
- `OAuthAuthorizationDenied` is now dispatched, by the new `OAuthServerService::denyAuthorizationRequest()`.
- `access_tokens.ip_address` / `user_agent` are now recorded on issue and rotation (from the current request, or `TokenContext` metadata keys `ip_address` / `user_agent`).

### Changed
- OAuth routes return RFC 6749 error bodies and RFC 7662 introspection members. Set `SP_JWT_OAUTH_LEGACY_RESPONSES=true` to keep the old shapes while migrating clients.
- Config keys that were never read are now implemented or removed: `clock_skew_seconds` is JWT leeway; `keys.previous_kids` / `keys.compromised_kids` set key state; `api_keys.max_ttl_days` / `allow_never_expires` bound key lifetimes (keys without an expiry now get `max_ttl_days`; an explicit longer expiry throws `InvalidArgumentException`). Removed: `rotate_refresh_tokens`, `keys.rotation_grace_days`, `oauth_server.access_token_format`. The module `enabled` flags remain informational (see the configuration guide).
- `sp-jwt-auth:prune` deletes in batches (`--chunk`, default 1000).
- Documentation corrected where it disagreed with the code (OAuth responses, token prefix, refresh token format, env var names).

### Performance
- Signing keys and hash keys are memoized (key files are read once); `flush()` on both repositories. Under Octane a replaced key file or changed key config is only picked up after a worker restart or `flush()`.
- New `last_used_interval_minutes` (`SP_JWT_LAST_USED_INTERVAL_MINUTES`, default `0` = write on every request, as before). Set to `5` to cut `last_used_at` writes for JWTs, API keys and OAuth tokens; `ApiKeyUsed` still fires every time.
- First-factor OTP rate limiting locks per rate-limit key (sorted order) instead of one global lock; the shared SMS project quota stays atomic.
- OAuth authorization-code exchange no longer locks/scans expired codes; new index `sp_oauth_auth_codes_active_index` (on PostgreSQL create it first with `CREATE INDEX CONCURRENTLY`, the migration then skips).
- Refresh rotation updates the old refresh row once. Compatibility: `beforeTokenIssue` hooks now see the old refresh row as not yet revoked.
- Fewer queries: schema-column check cached, email verification resolves the user once, identity/consent upserts drop a pre-query, OTP failed attempts drop a `refresh()`, the OTP destination is hashed once per request.

### Added
- Optional device module (`sp-jwt-auth.devices.enabled`, default off): `POST /auth/devices` and `/auth/devices/token`, device-scoped token pairs (`user_type = Device`, key follows `id_type`), `DeviceService` (register, authenticate, `upgradeToUser`, `devicesForUser`, `revokeForUser`, `revoke`), `sp.jwt.device` / `sp.jwt.any` middleware, pluggable `AttestationVerifier` (fails closed when attestation is required and none is bound), `DeviceRegistered` / `DeviceUpgradedToUser` / `DeviceRevoked` events, `sp-jwt-auth:prune-devices`, and `TokenContext::device()`. New tables `sp_jwt_devices` and `sp_jwt_device_users` are always migrated and stay empty unless enabled.
- Optional httpOnly cookie transport for refresh tokens (`token_endpoints.refresh_transport` = `body` | `cookie` | `both`, default `body`), with cookie settings, double-submit CSRF (403 on mismatch; the value is also returned in the `X-SP-CSRF` response header so cross-site and subdomain SPAs can read it, which needs CORS `exposed_headers`), `TokenResponse::withRefreshCookie()`, and cookie support on `/auth/token/refresh`, `/auth/token/revoke` and the first-factor OTP verify route. `/auth/token/revoke` now also accepts a `refresh_token` body field or the cookie when there is no valid bearer token, and no longer sits behind `auth` middleware (it still answers 401 when nothing authenticates). New `JwtTokenService::revokeSessionByRefreshToken()`.
- `OAuthServerService::authenticateClient()`, `denyAuthorizationRequest()`, and an optional `$client` argument on `revokeToken()` / `introspect()`.
- `ExternalIdentityStore::unlink()`.
- Typed OAuth exceptions implementing `Contracts\OAuthErrorException`.

## [0.1.24] - 2026-09-23

### Added
- Independent first-factor OTP send-per-destination, send-per-IP, and verify-per-IP rate-limit policies, each with its own attempt count and decay window.
- Optional project-wide SMS quota shared by request and resend operations; email sends do not consume it. Broker and package HTTP routes enforce the same policy through Laravel's limiter cache.
- Atomic quota reservations using cache locks; failed OTP delivery releases its reservation so an immediate retry is possible.

### Fixed
- First-factor OTP cooldown responses now report the actual remaining `Retry-After` seconds.

### Compatibility
- Existing `request_per_destination`, `request_per_ip`, `verify_per_ip`, and `decay_minutes` settings remain the fallback when independent policies are not configured. The project SMS quota is disabled by default.

## [0.1.23] - 2026-08-30

### Added
- `OtpDeliveryAwareSender` contract — a result-aware counterpart to `OtpChannelSender` whose `deliver()` returns an `OtpDeliveryResult` carrying `successful`, `providerReference`, and `failureReason`. A sender may implement both; the brokers prefer `deliver()`.
- `OtpDeliveryResult` DTO with `success()` / `failure()` named constructors.
- `OtpDeliveryFailed` event, dispatched when a result-aware sender reports a failed delivery.
- `OtpDeliveryFailedException` (new `Sopheak\JwtAuth\Exceptions` namespace) implementing Symfony's `HttpExceptionInterface`, rendering as HTTP 502.
- `OtpCodeSent` now carries an optional `$delivery` result — `null` for legacy `void` senders, whose delivery outcome is unknowable.

### Fixed
- A failed OTP delivery no longer leaves an orphaned challenge row or an armed resend cooldown. `FirstFactorOtpBroker::request()` and `OtpChallengeBroker::createOtp()` now delete the code they just created, so the client may retry immediately and no `OtpCodeCreated`/`OtpCodeSent` event fires for an undelivered code. The parent `MfaChallenge` is left intact.
- The rollback covers senders that **throw** as well as those that report failure, on both the legacy `OtpChannelSender` and `OtpDeliveryAwareSender` contracts. Previously only an explicitly-returned `OtpDeliveryResult::failure()` triggered it, so a raising sender — the only failure mode a legacy `void` sender has — still stranded a row and locked the destination out for `resend_cooldown_seconds`. Reported against `0.1.23-beta.46`. The original exception propagates unchanged; `OtpDeliveryFailed` is dispatched with the exception's class name as `failureReason`.

### Compatibility
- Fully additive. `OtpChannelSender` is unchanged and existing implementations keep working with no code edit. No config keys added or changed, no migrations, no route changes.

## [0.1.21] - 2026-08-19

### Changed
- `sp-jwt-auth:boost` no longer installs skills/guidelines or edits `boost.json` — it now only merges the `sp-jwt-auth` MCP server into the client's `.mcp.json` and runs setup validation.
- New `sp-jwt-auth:agent` command (alias `sp-jwt-auth:agent-init`) installs the agent skill (`.agents/skills/sp-jwt-auth/SKILL.md`) and rules (`.agents/rules/sp-jwt-auth.md`) on demand, with `--force`, `--skill`, `--rules`, `--mcp`, and `--all` flags.
- Auto-add of skill/agent assets on package install removed: `boot.json` no longer lists `guidelines/sp-jwt-auth.md` or `skills/sp-jwt-auth/SKILL.md` in its `docs` array, so agentic installers no longer pull skill/agent files into client apps.

## [0.1.20] - 2026-08-11

### Added
- Per-destination fixed OTP test codes: `first_factor_otp.test_codes` (`SP_JWT_FFOTP_TEST_CODES`), comma-separated `destination:code` pairs matched against the normalized destination. A matching destination uses the fixed code, and delivery (sender + `OtpCodeSent`) is skipped — for both per-destination entries and the global `test_mode`/`test_code`.

### Changed
- Agent-facing context moved out of the shipped package: the four agent context docs are now local `.opencode/rules/*.md` (gitignored, loaded via `opencode.json`); the client installation guide moved from `docs/ai/client-install.md` to `docs/client-install.md` (references updated in `boot.json`, `guidelines/sp-jwt-auth.md`, `skills/sp-jwt-auth/SKILL.md`, `AGENTS.md`).
- Dependencies: `firebase/php-jwt` constraint tightened to `^7.0` (drops the vulnerable `<7.0.0` range from the Dependabot advisory); float updates pull patched `guzzlehttp/guzzle` 7.15.2 and `league/commonmark` 2.9.2, clearing all `composer audit` advisories.

## [0.1.19] - 2026-08-10

### Added
- `boot.json` package manifest so Laravel Boot and other agentic installers can discover and configure the package in client apps (install/setup/verify steps, optional modules, required env vars).
- `sp-jwt-auth:setup --skip-migrations` flag to publish configuration without copying package migrations.
- `sp-jwt-auth:validate --json` flag for machine-readable validation reports (`{"status":"ok"|"error","errors":[...],"warnings":[...]}`, exit code 0/1).
- `docs/ai/client-install.md` agent-facing client installation guide (publish, configure, migrate, verify, User model trait, optional modules).
- `sp-jwt-auth:boost` command wiring guidelines, the package skill, and the MCP server into client apps, and `sp-jwt-auth:mcp` MCP stdio server (read-only `validate`, `jwks`, `config` tools).
- `sp-jwt-auth.id_type` config (`SP_JWT_ID_TYPE`, `uuid|integer`, default `integer`): package `user_id` columns are created as `uuid` or `unsignedBigInteger` based on the client app's user primary-key type. Must be set before the first migrate.
- Non-UUID refresh-token ids are rejected with a generic 401 instead of a PostgreSQL `PDOException`.
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
- Config-driven default `FirstFactorUserResolver` (`first_factor_otp.user_model` + `destination_columns` + `requested_type_column`): resolve-or-create users without consumer code, with duplicate-key race re-resolution.
- Configurable response envelope (`response_envelope`: `raw` | `laravel` | custom class) for the OTP and token endpoints, including the authenticated user in enveloped verify responses via `user_resource`.
- `FirstFactorOtpBroker::resendByDestination()` and `POST /otp/resend-by-destination` re-issuing codes by destination + purpose with `requested_type` inheritance and shared cooldown.

### Changed
- Updated package metadata and documentation for MIT licensing and public Packagist installation.
- Added community contribution, conduct, support, issue, pull request, security, and Dependabot files.
- Replaced realistic-looking documentation secrets with placeholder values.
- Added README badges, a pre-1.0 stability note, and a copy-paste JWT login and refresh quick start.
- `AGENTS.md` and `docs/ai/commands.md` document the new Boot manifest, setup flags, and JSON validation output.
