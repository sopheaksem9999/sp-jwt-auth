---
title: Module enabled flags stay informational; prune must respect the refresh-token cascade
author: Sem
date: 2026-10-26
branch: develop
cost: { minutes: 25, tokens: 430431 }
id: l-5221b5eb
scope: repo
status: active
touches:
  - CHANGELOG.md
  - config/sp-jwt-auth.php
  - docs/core-concepts/configuration.md
  - docs/core-concepts/signing-keys-jwks.md
  - docs/features/oauth-server.md
  - docs/features/token-endpoints.md
  - docs/guide/configuration.md
  - docs/guide/oauth-server.md
  - docs/guide/signing-keys-jwks.md
  - docs/guide/token-endpoints.md
---

## Decided
- `mfa`, `mfa.otp`, `email_verification`, `password_reset`, `api_keys` and `external_identities` `enabled` flags are informational only (MCP/validate report them). Enforcing them would switch services off for every existing app that never set them (defaults are false). Revisit in 0.2.0.
- OAuth HTTP shaping lives in `src/Support/OAuthHttp.php`; the service keeps throwing exceptions (existing tests and callers rely on that). Typed exceptions implement `Contracts\OAuthErrorException`. `oauth_server.legacy_responses` keeps the old body shapes.
- Provider tokens: an Eloquent Attribute stores `{"enc": ciphertext}` JSON; legacy plaintext rows still read and re-encrypt on next write, so no migration.

## Tried and ruled out
- Per-destination OTP rate-policy locks (first perf-spec draft): breaks the shared SMS project quota, because `reserveSend()` checks several limits as one unit. The revised spec uses one lock per rate-limit key, sorted. Not implemented yet.
- Caching `Schema::hasColumn` on `FirstFactorOtpBroker` instance: it is a `final readonly` class resolved per request, so the cache never survives. Use a singleton helper.

## Watch out
- `sp_jwt_refresh_tokens.access_token_id` has `cascadeOnDelete()`, so pruning an old access token deletes its still-valid refresh token. Prune refresh tokens first and keep access tokens with a live refresh token.
- Carbon 3 `diffInSeconds()` is signed: use `now()->diffInSeconds($later, false)`.
- Tests that store provider tokens need `app.key` (set in `tests/TestCase.php`).
