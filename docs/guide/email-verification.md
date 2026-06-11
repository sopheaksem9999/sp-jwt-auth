---
title: "Email Verification"
description: "Create and verify hashed account email verification tokens."
---

# Email Verification

The email verification broker creates one-time verification tokens. The application owns templates, notification classes, response shape, and when verification is required.

## Create a Verification Token

```php
use Sopheak\JwtAuth\Services\EmailVerificationBroker;

$dispatch = app(EmailVerificationBroker::class)->createVerificationToken(
    user: $user,
    email: $user->email,
    metadata: ['source' => 'registration'],
);
```

The dispatch contains the plaintext token once. The database stores only token and email hashes.

## Send a Verification Message

Bind `EmailVerificationSender`:

```php
use Sopheak\JwtAuth\Contracts\EmailVerificationSender;

$this->app->bind(EmailVerificationSender::class, AppVerificationSender::class);
```

The app can use Laravel Notifications, Mailables, queues, and custom Blade templates.

## Verify the Token

```php
$result = app(EmailVerificationBroker::class)->verifyEmailToken(
    $request->input('token'),
);
```

The result returns the resolved user and email. The token is single-use and expires according to `email_verification.ttl_minutes`.

## Revoke Outstanding Tokens

```php
app(EmailVerificationBroker::class)->revokeVerificationTokens($user);
```
