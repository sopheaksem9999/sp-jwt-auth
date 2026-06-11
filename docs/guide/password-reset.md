---
title: "Password Reset Tokens"
description: "Create, verify, and consume forgot-password reset tokens."
---

# Password Reset Tokens

The password reset broker creates one-time reset tokens. The package does not update passwords. The consuming application owns password validation, hashing, and persistence.

## Create a Reset Token

```php
use Sopheak\JwtAuth\Services\PasswordResetBroker;

$dispatch = app(PasswordResetBroker::class)->createResetToken(
    user: $user,
    email: $user->email,
    metadata: ['ip' => $request->ip()],
);
```

The dispatch contains the plaintext token once. The token secret is stored only as an HMAC hash.

## Send a Reset Message

Bind `PasswordResetSender`:

```php
use Sopheak\JwtAuth\Contracts\PasswordResetSender;

$this->app->bind(PasswordResetSender::class, AppPasswordResetSender::class);
```

## Verify Without Consuming

```php
$result = app(PasswordResetBroker::class)->verifyResetToken($token);
```

Use this when showing a password reset form.

## Consume the Token

```php
$result = app(PasswordResetBroker::class)->consumeResetToken($token);

// App-owned logic:
// $user->forceFill(['password' => Hash::make($request->input('password'))])->save();
```

Consumed tokens cannot be reused. Failed checks increment attempts and lock after `password_reset.max_attempts`.

## Revoke User Reset Tokens

```php
app(PasswordResetBroker::class)->revokeResetTokens($user);
```
