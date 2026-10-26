---
title: Device keys follow id_type; CSRF value must also be a response header
author: Sem
date: 2026-10-26
branch: develop
cost: { minutes: 10, tokens: 117421 }
id: l-2a404922
scope: repo
status: active
touches:
  - CHANGELOG.md
  - config/sp-jwt-auth.php
  - database/migrations/2026_10_26_000002_create_sp_jwt_devices_tables.php
  - docs/client-install.md
  - docs/features/devices.md
  - docs/guide/devices.md
  - routes/devices.php
  - src/Console/PruneDevicesCommand.php
  - src/Contracts/AttestationVerifier.php
  - src/CoreSpJwtAuthServiceProvider.php
---

## Decided
- Device primary key follows `sp-jwt-auth.id_type` (integer or uuid) because device tokens store it in `sp_jwt_*_tokens.user_id`, which `UserIdColumn` types that way. Clients only ever see the separate `public_id` UUID, never the internal key.
- Device tokens are not special-cased in the guard: `JwtGuard` rejects any token whose `user_type` differs from the provider model, which keeps device tokens off user routes and also closes the Admin#5/User#5 mix-up.
- Required attestation with no verifier bound fails closed (`NullAttestationVerifier` is skipped when optional, throws when required).
- Cookie-transport CSRF value is returned in the `X-SP-CSRF` response header as well as the `sp_csrf` cookie: a script on another site or a host-only subdomain cookie cannot read the API's cookie. Cross-site hosts must list the header in CORS `exposed_headers`; the package cannot set that.

## Tried and ruled out
- Per-session revoke events when revoking a whole device: bulk updates in chunks of 500 instead, so no `SessionRevoked` per session.

## Watch out
- Laravel test client: `withToken()` persists across requests in one test (use `flushHeaders()`); `postJson()` drops cookies unless `withCredentials()` is set.
- `$this->count()` collides with a final PHPUnit method; name test helpers differently.
