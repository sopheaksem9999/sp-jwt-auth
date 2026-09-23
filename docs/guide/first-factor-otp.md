---
title: "First-Factor OTP"
description: "Passwordless OTP sign-in and sign-up with app-owned user creation."
---

# First-Factor OTP

The first-factor OTP module provides passwordless sign-in and sign-up through an OTP code sent to an email address or phone number. The package owns the challenge lifecycle, rate limits, hashing, and token issuance. The app owns delivery and user creation.

The module is config-gated and disabled by default. It is the one exception to the package's no-login/no-registration non-goal: the app still owns actual user creation through the `FirstFactorUserResolver` contract.

## Enable the Module

Set `first_factor_otp.enabled` or the `SP_JWT_FFOTP_ENABLED` env var to `true`:

```bash
SP_JWT_FFOTP_ENABLED=true
```

## Configuration

| Key | Env var | Default | Description |
| --- | --- | --- | --- |
| `first_factor_otp.enabled` | `SP_JWT_FFOTP_ENABLED` | `false` | Enable the module and register its routes. |
| `first_factor_otp.route_prefix` | `SP_JWT_FFOTP_ROUTE_PREFIX` | `otp` | URL prefix for the request/resend/verify routes. |
| `first_factor_otp.digits` | `SP_JWT_FFOTP_DIGITS` | `6` | Number of digits in generated codes. |
| `first_factor_otp.ttl_minutes` | `SP_JWT_FFOTP_TTL_MINUTES` | `5` | Code lifetime in minutes. |
| `first_factor_otp.max_attempts` | `SP_JWT_FFOTP_MAX_ATTEMPTS` | `5` | Wrong-code attempts before the code locks. |
| `first_factor_otp.resend_cooldown_seconds` | `SP_JWT_FFOTP_RESEND_COOLDOWN_SECONDS` | `60` | Minimum delay between request and resend for the same destination. |
| `first_factor_otp.default_scopes` | — | `['*']` | Scopes attached to issued token pairs. |
| `first_factor_otp.purposes` | — | `[]` | Allowed purposes; empty means all purposes are allowed. |
| `first_factor_otp.requested_types` | — | `[]` | Allowed requested account types; empty means all types are allowed. |
| `first_factor_otp.test_mode` | `SP_JWT_FFOTP_TEST_MODE` | `false` | Accept a fixed test code instead of the generated one. Dev/staging only. |
| `first_factor_otp.test_code` | `SP_JWT_FFOTP_TEST_CODE` | `null` | The fixed code accepted when `test_mode` is enabled. |
| `first_factor_otp.test_codes` | `SP_JWT_FFOTP_TEST_CODES` | `''` | Comma-separated `destination:code` pairs (e.g. `dev@mail.com:009988,+85511002233:123456`); a matching destination uses that fixed code. Matching is against the normalized destination (email lowercased, phone without whitespace). |
| `first_factor_otp.limits.request_per_destination` | `SP_JWT_FFOTP_LIMIT_REQUEST_DESTINATION` | `5` | Request/resend limit per destination within the decay window. |
| `first_factor_otp.limits.request_per_ip` | `SP_JWT_FFOTP_LIMIT_REQUEST_IP` | `20` | Request/resend limit per IP within the decay window. |
| `first_factor_otp.limits.verify_per_ip` | `SP_JWT_FFOTP_LIMIT_VERIFY_IP` | `30` | Legacy count per IP within the shared decay window; replace with a `max_attempts`/`decay_seconds` array for an independent window. |
| `first_factor_otp.limits.decay_minutes` | `SP_JWT_FFOTP_LIMIT_DECAY_MINUTES` | `60` | Rate-limit window length in minutes. |
| `first_factor_otp.limits.send_per_destination` | — | `null` | Optional `max_attempts` and `decay_seconds` for requests and resends to one destination. |
| `first_factor_otp.limits.send_per_ip` | — | `null` | Optional independent send limit per caller IP. |
| `first_factor_otp.limits.sms_per_project` | — | `null` | Optional project-wide SMS quota across all destinations and send operations. Email does not use this quota. |
| `first_factor_otp.message_template.sms` | `SP_JWT_FFOTP_SMS_TEMPLATE` | `Your {app} verification code is {code}. Valid for {ttl} minutes.` | SMS message template. |
| `first_factor_otp.message_template.email` | `SP_JWT_FFOTP_EMAIL_TEMPLATE` | `Your {app} verification code is {code}. Valid for {ttl} minutes.` | Email message template. |
| `first_factor_otp.user_model` | `SP_JWT_FFOTP_USER_MODEL` | `null` | User model for the built-in default resolver; setting it binds `DefaultFirstFactorUserResolver`. Unset keeps the module contract-only. |
| `first_factor_otp.destination_columns` | — | `['phone' => 'phone', 'email' => 'email']` | Column per channel the default resolver queries and fills. |
| `first_factor_otp.requested_type_column` | `SP_JWT_FFOTP_REQUESTED_TYPE_COLUMN` | `type` | Column the default resolver writes `requested_type` into. |
| `first_factor_otp.response_envelope` | `SP_JWT_FFOTP_RESPONSE_ENVELOPE` | `raw` | Response wrapping: `raw`, `laravel` (`{"data": ...}`), or a class implementing `Sopheak\JwtAuth\Contracts\ResponseEnvelope`. |
| `first_factor_otp.user_resource` | `SP_JWT_FFOTP_USER_RESOURCE` | `null` | Invokable class serializing the user in enveloped verify responses; defaults to the model `toArray()`. |

Message templates support the placeholders `{app}`, `{code}`, and `{ttl}`.

## Resolve or Create Users

The package never touches your user table directly. Bind a `FirstFactorUserResolver` implementation in the application:

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\DTO\OtpDestination;

final class OtpUserResolver implements FirstFactorUserResolver
{
    public function resolve(OtpDestination $destination, string $purpose): ?Authenticatable
    {
        $column = $destination->channel === 'email' ? 'email' : 'phone';

        return App\Models\User::where($column, $destination->normalizedDestination)->first();
    }

    public function create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable
    {
        $attributes = $destination->channel === 'email'
            ? ['email' => $destination->normalizedDestination]
            : ['phone' => $destination->normalizedDestination];

        return App\Models\User::create($attributes);
    }
}

// AppServiceProvider::register()
$this->app->bind(FirstFactorUserResolver::class, OtpUserResolver::class);
```

Return `null` from `create()` to reject sign-up for a destination. After successful verification the broker marks `email_verified_at` or `phone_verified_at` on the user when the column exists.

### Default resolver (no consumer code)

Set `first_factor_otp.user_model` to use the built-in `DefaultFirstFactorUserResolver` — the package binds it automatically and your own binding (if any) still wins:

```php
'user_model' => App\Models\User::class,           // SP_JWT_FFOTP_USER_MODEL
'destination_columns' => ['phone' => 'phone', 'email' => 'email'],
'requested_type_column' => 'type',                // SP_JWT_FFOTP_REQUESTED_TYPE_COLUMN
```

It resolves users by the destination column (`email` lowercased, `phone` E.164) and creates missing users with `requested_type` written to the configured column. Duplicate-key races re-resolve the existing row; other NOT NULL user columns without defaults must be nullable or have DB defaults. Unmapped or missing columns resolve to `null`.

## Response Envelope

By default (`response_envelope: raw`) responses are unwrapped. Set `laravel` to wrap every payload in `{"data": ...}`, or configure a class implementing `Sopheak\JwtAuth\Contracts\ResponseEnvelope` for a fully custom shape. When the envelope is not `raw`, the `verify` response also includes the authenticated user serialized through `user_resource` (an invokable class receiving the user) or the model `toArray()` (respects `$hidden`).

## Deliver Codes

Bind `OtpChannelSender` in the application and format the message with `OtpMessageFormatter`:

```php
use Sopheak\JwtAuth\Contracts\OtpChannelSender;
use Sopheak\JwtAuth\DTO\OtpDispatch;
use Sopheak\JwtAuth\Support\OtpMessageFormatter;

final class SmsOtpSender implements OtpChannelSender
{
    public function send(OtpDispatch $dispatch): void
    {
        $message = OtpMessageFormatter::format(
            config('sp-jwt-auth.first_factor_otp.message_template.sms'),
            [
                'app' => config('app.name'),
                'code' => $dispatch->plaintextCode,
                'ttl' => config('sp-jwt-auth.first_factor_otp.ttl_minutes'),
            ],
        );

        // send $message to $dispatch->destination->normalizedDestination
    }
}

// AppServiceProvider::register()
$this->app->bind(OtpChannelSender::class, SmsOtpSender::class);
```

The sender receives an `OtpDispatch` with the plaintext code. Only the HMAC hash of the code is stored.

### Report Delivery Outcome (optional)

`OtpChannelSender::send()` returns `void`, so a gateway failure cannot stop the broker from issuing a challenge for a code that was never sent. Implement `OtpDeliveryAwareSender` instead — or in addition — to report the outcome:

```php
use Sopheak\JwtAuth\Contracts\OtpChannelSender;
use Sopheak\JwtAuth\Contracts\OtpDeliveryAwareSender;
use Sopheak\JwtAuth\DTO\OtpDeliveryResult;
use Sopheak\JwtAuth\DTO\OtpDispatch;

final class SmsOtpSender implements OtpChannelSender, OtpDeliveryAwareSender
{
    public function deliver(OtpDispatch $dispatch): OtpDeliveryResult
    {
        $response = $this->gateway->send(/* ... */);

        return $response->ok()
            ? OtpDeliveryResult::success($response->json('request_id'))
            : OtpDeliveryResult::failure('http_' . $response->status());
    }

    public function send(OtpDispatch $dispatch): void
    {
        $this->deliver($dispatch);
    }
}
```

Binding is unchanged: a class implementing both contracts still binds to `OtpChannelSender::class`, and the broker prefers `deliver()`. Binding `OtpDeliveryAwareSender::class` explicitly also works and takes precedence.

When `deliver()` returns a failure the broker **deletes the challenge it just created**, dispatches `OtpDeliveryFailed`, and throws `OtpDeliveryFailedException` — which renders as HTTP **502** with `{"message": "<failureReason>"}`. No challenge is issued for an undelivered code, and because the row is gone the resend cooldown is not armed, so the client may retry immediately.

The same rollback applies when a sender **throws** instead of reporting a failure — on either contract, including a legacy `OtpChannelSender` whose `send()` raises. The original exception propagates unchanged; the broker only ensures no challenge survives it. This matters because a surviving row would stamp `last_sent_at` and lock the destination out for the full `resend_cooldown_seconds` over an SMS that never left.

`providerReference` is not included in the response body. Read it from the events instead:

| Event | When | Carries |
|---|---|---|
| `OtpCodeSent` | Delivery succeeded, or a legacy `void` sender ran | `$delivery` — the `OtpDeliveryResult`, or `null` for a legacy sender (outcome unknown) |
| `OtpDeliveryFailed` | `deliver()` reported failure, **or** either sender threw | `$result` with `failureReason` and `providerReference`. When the sender threw, `failureReason` is the exception's class name — messages are excluded because they routinely carry gateway URLs and credentials, and the exception itself still reaches your handler. |
| `OtpCodeCreated` | A challenge was issued | Not dispatched when delivery failed |

Existing `OtpChannelSender` implementations keep working unchanged; `$delivery` is `null` for them because a `void` sender cannot confirm delivery.

## HTTP Endpoints

Routes register under `first_factor_otp.route_prefix` (default `otp`) only when the module is enabled:

| Method | Path | Auth | Body | Response |
| --- | --- | --- | --- | --- |
| `POST` | `/otp/request` | none | `destination`, `channel` (`email`/`sms`), `purpose`, optional `requested_type` | `202` — `{otp_id, destination_masked, expires_in}` |
| `POST` | `/otp/resend` | none | `otp_id`, `destination`, `channel` | `202` — `{otp_id, destination_masked, expires_in}` |
| `POST` | `/otp/resend-by-destination` | none | `destination`, `channel`, `purpose` | `202` — `{otp_id, destination_masked, expires_in}` |
| `POST` | `/otp/verify` | none | `otp_id`, `code`, optional `destination` + `channel` | `200` — `{access_token, refresh_token, token_type, expires_in}` |

`/otp/resend-by-destination` re-issues the latest active challenge for the destination and purpose, inheriting its `requested_type` and sharing the same cooldown and rate limits — it responds `422` when no active challenge exists.

The plaintext code is never returned by an endpoint. `request`, `resend`, and `resend-by-destination` share send limits and the per-destination cooldown; `verify` uses its own per-IP limit. Limits respond with `429` and a `Retry-After` header showing the remaining seconds. Client errors such as an unknown purpose (when a purpose allowlist is configured) or a destination mismatch map to `422`.

### Independent rate-limit policies

Set policies in the published `config/sp-jwt-auth.php` to use different windows. Each policy is optional. When omitted, `request_per_destination`, `request_per_ip`, `verify_per_ip`, and `decay_minutes` retain their legacy behavior. The project SMS quota is disabled until configured.

```php
'limits' => [
    'request_per_destination' => 5, // legacy fallback
    'request_per_ip' => 20,          // legacy fallback
    'verify_per_ip' => ['max_attempts' => 30, 'decay_seconds' => 300],
    'decay_minutes' => 60,           // legacy fallback
    'send_per_destination' => ['max_attempts' => 30, 'decay_seconds' => 3600],
    'send_per_ip' => ['max_attempts' => 30, 'decay_seconds' => 300],
    'sms_per_project' => ['max_attempts' => 30, 'decay_seconds' => 3600],
],
'resend_cooldown_seconds' => 60,
```

The broker enforces these policies for package routes and application-owned endpoints. Pass the caller IP when invoking it from your own route:

```php
$dispatch = $broker->request($destination, 'login', ip: $request->ip());
$dispatch = $broker->resend($otpId, $destination, ip: $request->ip());
$dispatch = $broker->resendByDestination($destination, 'login', ip: $request->ip());
$verification = $broker->verify($otpId, $code, ip: $request->ip());
```

The broker uses the bound Laravel request IP when no IP is passed. For workers without an HTTP request, pass the caller IP explicitly if per-IP policy is required. Counters and the atomic SMS reservation use Laravel's `cache.limiter` store, or `cache.default` when no limiter store is configured. Configure that store as a shared, lock-capable cache such as Redis for multi-worker or multi-instance deployments. Failed delivery releases its reservation and removes the challenge, allowing an immediate retry.

## Security Notes

- Codes and destinations are stored only as HMAC hashes via `SecretHasher`; destinations are masked.
- Requesting a new code invalidates prior pending challenges for the same destination.
- Verification is atomic and single-use: a database transaction with a row lock rejects expired or already verified codes, increments the attempt counter on wrong codes, and locks the code after `max_attempts`.
- `test_mode`/`test_code` and `test_codes` accept fixed codes — enable them only in dev/staging, never in production. Delivery (sender + `OtpCodeSent`) is skipped for fixed codes, including per-destination matches.

## Events

- `FirstFactorOtpVerified` — issued after a successful verification and token pair issuance.
- `FirstFactorOtpFailed` — wrong or malformed code submitted.
- `FirstFactorOtpLocked` — code locked after max attempts.
- `FirstFactorOtpExpired` — expired code rejected.
- Reuses `OtpCodeCreated`, `OtpCodeSent`, and `OtpCodeResent` from the account security module.
