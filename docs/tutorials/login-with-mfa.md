---
title: "Login with MFA"
description: "End-to-end login flow with MFA challenge and OTP verification."
---

# Login with MFA

This flow splits token issuance into two steps: the app validates credentials and creates an MFA challenge, then the user verifies an OTP code to complete the login and receive tokens.

## 1. Initiate Login

```php
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\MfaChallengeBroker;
use Sopheak\JwtAuth\Services\OtpChallengeBroker;

$user = User::where('email', $request->input('email'))->first();

if (! $user || ! Hash::check($request->input('password'), $user->password)) {
    throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
}

// 1. Create an MFA challenge that holds the token context
$challenge = app(MfaChallengeBroker::class)->create(
    $user,
    TokenContext::make()
        ->subject('tenant', (string) $user->tenant_id)
        ->scopes(['profile.read'])
        ->claims(['tenant_id' => $user->tenant_id]),
);

// 2. Create an OTP code for the challenge
$otp = app(OtpChallengeBroker::class)->createOtp(
    $challenge,
    new OtpDestination(
        channel: 'email',
        normalizedDestination: $user->email,
        maskedDestination: substr($user->email, 0, 1) . '***@' . substr(strstr($user->email, '@'), 1),
    ),
);

// 3. App-owned delivery — send the plaintext OTP code
// Mail::to($user)->send(new OtpMail($otp->plaintextCode));

return response()->json([
    'message' => 'OTP sent.',
    'challenge_id' => $challenge->id,
]);
```

## 2. Verify OTP and Issue Tokens

```php
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Services\OtpChallengeBroker;
use Sopheak\JwtAuth\Support\TokenResponse;

$context = app(OtpChallengeBroker::class)->verifyOtp(
    challengeId: $request->input('challenge_id'),
    code: $request->input('otp'),
);

// $context is the TokenContext from step 1
$user = User::findOrFail($context->subjectValue?->id ?? throw new \Exception('User not found'));

$pair = app(JwtTokenService::class)->issueTokenPair($user, $context);

return response()->json([
    'token' => TokenResponse::passportCompatible($pair),
]);
```

## Key Points

- The package stores the token context in the MFA challenge. You do not need to rebuild scopes, claims, or subject after OTP verification.
- OTP codes expire after `mfa.otp.ttl_minutes` (default 5 minutes).
- Failed attempts increment; the challenge locks after `mfa.otp.max_attempts`.
- Bind `OtpChannelSender` to deliver via email, SMS, or your preferred channel.
