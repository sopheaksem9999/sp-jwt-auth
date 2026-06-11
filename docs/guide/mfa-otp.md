---
title: "MFA and OTP"
description: "Create MFA challenges and hashed email/phone OTP codes."
---

# MFA and OTP

The account security module provides MFA challenge storage and OTP code handling. The package owns challenge/token infrastructure. The app owns MFA policy, method selection, and delivery providers.

## Create an MFA Challenge

```php
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\MfaChallengeBroker;

$challenge = app(MfaChallengeBroker::class)->create(
    $user,
    TokenContext::make()->scopes(['profile.read']),
);
```

The challenge stores the token context that should be resumed after successful MFA.

## Create an OTP Code

```php
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Services\OtpChallengeBroker;

$dispatch = app(OtpChallengeBroker::class)->createOtp(
    $challenge,
    new OtpDestination(
        channel: 'email',
        normalizedDestination: 'user@example.com',
        maskedDestination: 'u***@example.com',
    ),
);
```

Plaintext OTP codes are available only on the dispatch object. The database stores HMAC hashes.

## Deliver OTP Codes

Bind `OtpChannelSender` in the application:

```php
use Sopheak\JwtAuth\Contracts\OtpChannelSender;

$this->app->bind(OtpChannelSender::class, AppOtpSender::class);
```

The app can send via email, SMS, voice, WhatsApp, or another channel.

## Verify OTP Codes

```php
$context = app(OtpChallengeBroker::class)->verifyOtp(
    challengeId: $challenge->id,
    code: $request->input('otp'),
);
```

Verification:

- Rejects expired challenges.
- Rejects expired OTP rows.
- Increments failed attempts.
- Locks after max attempts.
- Marks the challenge completed.
- Returns the original `TokenContext`.
