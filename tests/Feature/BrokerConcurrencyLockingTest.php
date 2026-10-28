<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Sopheak\JwtAuth\Models\PasswordResetToken;
use Sopheak\JwtAuth\Services\EmailVerificationBroker;
use Sopheak\JwtAuth\Services\PasswordResetBroker;
use Sopheak\JwtAuth\Tests\TestCase;

final class BrokerConcurrencyLockingTest extends TestCase
{
    public function test_password_reset_consumption_is_atomic(): void
    {
        $user = $this->createUser(['email' => 'test@example.com']);
        $broker = app(PasswordResetBroker::class);
        $dispatch = $broker->createResetToken($user, 'test@example.com');

        $result = $broker->consumeResetToken($dispatch->token);
        self::assertSame('test@example.com', $result->email);

        $this->expectException(AuthenticationException::class);
        $broker->consumeResetToken($dispatch->token);
    }

    public function test_email_verification_is_atomic(): void
    {
        $user = $this->createUser(['email' => 'verify@example.com']);
        $broker = app(EmailVerificationBroker::class);
        $dispatch = $broker->createVerificationToken($user, 'verify@example.com');

        $result = $broker->verifyEmailToken($dispatch->token);
        self::assertSame('verify@example.com', $result->email);

        $this->expectException(AuthenticationException::class);
        $broker->verifyEmailToken($dispatch->token);
    }

    public function test_password_reset_invalid_secret_increments_attempts_and_persists(): void
    {
        $user = $this->createUser(['email' => 'attempt@example.com']);
        $broker = app(PasswordResetBroker::class);
        $dispatch = $broker->createResetToken($user, 'attempt@example.com');
        [$id, $secret] = explode('.', $dispatch->token, 2);

        try {
            $broker->consumeResetToken($id . '.wrongsecret');
            self::fail('Expected AuthenticationException');
        } catch (AuthenticationException) {
            // Expected
        }

        $record = PasswordResetToken::query()->findOrFail($id);
        self::assertSame(1, $record->attempts);
    }
}
