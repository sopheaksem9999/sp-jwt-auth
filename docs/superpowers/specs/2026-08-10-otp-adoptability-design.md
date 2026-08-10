# OTP Module Adoptability — Design Spec

> **Date:** 2026-08-10
> **Status:** Approved (client review follow-up)
> **Follows:** `docs/superpowers/plans/2026-08-10-auth-modules-phased.md` (Phase 2 first-factor OTP, shipped)

## Problem

The first-factor OTP module works mechanically, but consumers (e.g. the CamboGara backend) cannot switch off their own equivalent implementation without:

1. Writing their own `FirstFactorUserResolver` (resolve-or-create is the headline requirement — there is no default or reference adapter).
2. Forking or parsing raw response shapes (no envelope option; the verify response has no user object, which mobile clients need immediately after verify).
3. Coupling resend to a prior `otp_id` (no re-request by destination + purpose; no `requested_type` inheritance for sign-ups that lost the first challenge).
4. A released tag — the published version is still `0.1.18` (actually `0.1.17` on main) and contains none of the above.

## Hard Constraint: No Breaking Change for OTP Consumers

Everything in this spec is **additive and default-off**:

- `response_envelope` defaults to `'raw'` → all existing `/otp/*` and `/auth/token/*` response bodies are byte-identical.
- The default resolver binds **only when** `user_model` is configured; unset config leaves the module contract-only (current behavior).
- `resendByDestination` is a new broker method + new route; `request()`, `verify()`, and `resend()` signatures and semantics are untouched.
- No migration changes; no changes to existing config keys; no changes to broker events or DTOs.
- The verify `user` object appears **only** when the envelope is not `'raw'`.

## 1. Default FirstFactorUserResolver (opt-in)

### Config (`first_factor_otp` section)

```php
'user_model' => env('SP_JWT_FFOTP_USER_MODEL'),                                  // null = no default resolver
'destination_columns' => ['phone' => 'phone', 'email' => 'email'],
'requested_type_column' => env('SP_JWT_FFOTP_REQUESTED_TYPE_COLUMN', 'type'),
```

### Component

`src/Services/DefaultFirstFactorUserResolver.php` — `final class DefaultFirstFactorUserResolver implements FirstFactorUserResolver`.

- Constructor: none (reads config at call time).
- `resolve(OtpDestination $destination, string $purpose): ?Authenticatable`
  - `$column = config('first_factor_otp.destination_columns.' . $destination->channel)` (`sms` → `phone` key, `email` → `email` key).
  - Column unmapped, config `user_model` unset, or model table lacks the column → `null`.
  - Else `$model::query()->where($column, $destination->normalizedDestination)->first()`.
- `create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable`
  - `$model = new $modelClass()`; set destination column value; set `requested_type_column` when `$requestedType !== null`; `save()`.
  - On duplicate-key `QueryException` (unique-constraint race): re-run `resolve()` and return the existing row (or `null` if still absent).
- Model class missing (config points at a non-existent class) → clear `RuntimeException` at resolve/create time.

### Provider wiring

`CoreSpJwtAuthServiceProvider::boot()`: when `config('first_factor_otp.user_model')` is set →

```php
$this->app->bind(FirstFactorUserResolver::class, DefaultFirstFactorUserResolver::class);
```

Consumer-app bindings win (app providers run after package providers; `instance()` bindings always take precedence), so this is a fallback, never an override.

### Tests

- `tests/Unit/DefaultFirstFactorUserResolverTest.php` (or Feature — resolver is DB-backed, so Feature with the test harness `users` table extended with `phone` + `type` columns in `tests/TestCase.php`).
- Cases: resolve by email; resolve by phone; null on unknown destination; null on unmapped/missing column; create sets destination + requested_type column; create with null requested_type leaves the column null; duplicate-race re-resolve returns existing row; non-existent model class → RuntimeException.
- BC check: no resolver binding when `user_model` unset (broker still requires an app binding — current behavior).

## 2. Response Envelope + User on Verify

### Config

```php
'response_envelope' => env('SP_JWT_FFOTP_RESPONSE_ENVELOPE', 'raw'),   // first_factor_otp
'user_resource' => env('SP_JWT_FFOTP_USER_RESOURCE'),                  // first_factor_otp, nullable class-string
'response_envelope' => env('SP_JWT_TOKEN_ENDPOINTS_RESPONSE_ENVELOPE', 'raw'), // token_endpoints
```

Modes: `'raw'` (BC) | `'laravel'` (`['data' => $payload]`) | class-string implementing the envelope contract.

### Components

- `src/Contracts/ResponseEnvelope.php` — `interface ResponseEnvelope { public function wrap(array $payload): array; }`
- `src/Support/ResponseEnvelope.php` — `final class ResponseEnvelope` with `public static function wrap(array $payload, string $mode): array`:
  - `'raw'` → `$payload`
  - `'laravel'` → `['data' => $payload]`
  - class-string → `app($mode)->wrap($payload)` (container-resolved; invalid class → clear `RuntimeException`)

### Route changes

- `/otp/request`, `/otp/resend` (202 payloads), `/otp/verify` (200 token pair), `/auth/token/refresh`, `/auth/token/revoke` (empty array) — each wraps its payload via its module's `response_envelope` (read live at request time).
- `/otp/verify`: when envelope ≠ `'raw'`, payload gains `'user'`:
  - `user_resource` set → `app($resource)->__invoke($user)` (must return array; non-array → `RuntimeException`)
  - else → `$user->toArray()` (respects the model's `$hidden`).
- `'raw'` mode: byte-identical to today (verified by the existing route tests + explicit BC tests asserting the exact current shapes).

### Tests

- Envelope unit tests (raw identity, laravel wrap, custom class wrap, invalid class).
- Route tests per module flipping config: request/resend wrapped in `data`; verify wrapped with `user` present; user serialized via resource vs toArray; token refresh/revoke wrapped.
- BC regression: with defaults, all existing route tests pass unchanged.

## 3. resendByDestination

### Broker

```php
public function resendByDestination(OtpDestination $destination, string $purpose): OtpDispatch
```

- `$otp = $this->latestActive($destination, $purpose);` — none → `InvalidArgumentException('No active challenge for destination and purpose.')`
- else `return $this->resend($otp->id, $destination);` — reuses the challenge's `requested_type`, same destination check, same cooldown (no bypass).

### Route

`POST /otp/resend-by-destination` — body `destination` (required), `channel` (required in:email,sms), `purpose` (required) — 202 with the standard `{otp_id, destination_masked, expires_in}` shape (envelope-wrapped when configured), `throttle:sp-jwt-ffotp-request`, `InvalidArgumentException` → 422.

### Tests

- Broker: inherits requested_type (challenge created with requested_type, resendByDestination issues a new challenge with the same requested_type); shares cooldown (429/TooManyRequestsHttpException without last_sent_at rewind); missing challenge → InvalidArgumentException; other-purpose challenge not found.
- HTTP: 202 happy path; 422 unknown purpose; 404/disabled behavior unchanged; envelope wrapping when configured.

## 4. Release — 0.1.19 (one release folding all WIP)

1. Commit the 35 uncommitted WIP items in logical commits:
   - `feat: boost + mcp commands` (untracked Console/Support classes, provider registration, guidelines/, skills/, tests)
   - `feat: configurable user-id column type` (UserIdColumn + migration changes + tests)
   - `fix: cast user ids to string in brokers` (EmailVerificationBroker, PasswordResetBroker, OAuthServerService)
   - `feat: setup/validate command updates` (SetupCommand, ValidateCommand, SetupValidator, docs/ai/commands.md)
   - `chore: remove .trae, refresh AGENTS.md and opencode config` (AGENTS.md, .gitignore, opencode.json)
   - `chore: bump package version to 0.1.18` (composer.json, VERSION, CHANGELOG `[0.1.18]`)
2. Implement sections 1–3 on develop with TDD; update `docs/ai/client-install.md` (one-time config for the default resolver + envelope) and the guide pages (`docs/guide/first-factor-otp.md`, mirror in `docs/features/`).
3. Final release commit: bump `composer.json` `version`, `VERSION`, and `CHANGELOG.md` (add `[0.1.19]`) in lockstep; run `composer quality`.
4. Merge `develop` → `main` locally; re-run `composer quality` on the merge result.
5. Tag `0.1.19` on `main`; push `develop`, `main`, and the tag. Packagist syncs automatically if the webhook is configured (else the owner confirms on packagist.org).

## Out of Scope

- Changing `request()`, `verify()`, `resend()` signatures or semantics.
- Changing existing `/otp/*` or `/auth/token/*` request contracts (only additive: new route, new optional response wrapping).
- MFA-broker changes, OAuth changes, migration changes.
- A Passport-style envelope mode (deferred; `'laravel'` + custom class cover the stated need).
