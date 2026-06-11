---
title: "Email Verification During Registration"
description: "Verify email before issuing tokens during user registration."
---

# Email Verification During Registration

This flow creates a user, sends a verification email, and only issues a token pair after the email is verified.

## 1. Register and Send Verification

```php
use Sopheak\JwtAuth\Services\EmailVerificationBroker;

$user = User::query()->create([
    'name' => $request->input('name'),
    'email' => $request->input('email'),
    'password' => Hash::make($request->input('password')),
]);

$dispatch = app(EmailVerificationBroker::class)->createVerificationToken(
    user: $user,
    email: $user->email,
    metadata: ['source' => 'registration'],
);

// The package auto-sends via EmailVerificationSender if bound.
// The plaintext token is in $dispatch->plaintextToken.

return response()->json([
    'message' => 'Verification email sent.',
    'token_id' => $dispatch->tokenId,
], 201);
```

## 2. Verify Email

```php
use Sopheak\JwtAuth\Services\EmailVerificationBroker;

$result = app(EmailVerificationBroker::class)->verifyEmailToken(
    token: $request->input('token'),
);

// Token is consumed. Now issue a token pair.
$pair = app(JwtTokenService::class)->issueTokenPair(
    $result->user,
    TokenContext::make()->scopes(['profile.read']),
);

return response()->json([
    'token' => TokenResponse::passportCompatible($pair),
]);
```

## 3. Bind the Sender

```php
use Sopheak\JwtAuth\Contracts\EmailVerificationSender;
use Sopheak\JwtAuth\DTO\EmailVerificationDispatch;

$this->app->bind(EmailVerificationSender::class, function (): EmailVerificationSender {
    return new class implements EmailVerificationSender
    {
        public function send(EmailVerificationDispatch $dispatch): void
        {
            $dispatch->user->notulate(new VerifyEmailNotification(
                $dispatch->plaintextToken,
            ));
        }
    };
});
```

## Resend Verification

```php
$dispatch = app(EmailVerificationBroker::class)->resendVerificationToken(
    tokenId: $request->input('token_id'),
);
```

This revokes the old token and creates a new one.
