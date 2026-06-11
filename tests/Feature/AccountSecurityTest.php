<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Models\MfaOtpCode;
use Sopheak\JwtAuth\Services\EmailVerificationBroker;
use Sopheak\JwtAuth\Services\MfaChallengeBroker;
use Sopheak\JwtAuth\Services\OtpChallengeBroker;
use Sopheak\JwtAuth\Services\PasswordResetBroker;
use Sopheak\JwtAuth\Tests\TestCase;

final class AccountSecurityTest extends TestCase
{
    public function test_mfa_otp_stores_hash_only_and_verifies_context(): void
    {
        $user = $this->createUser();
        $challenge = app(MfaChallengeBroker::class)->create(
            $user,
            TokenContext::make()->subject('tenant', '42')->claims(['tenant_id' => 42]),
        );

        $dispatch = app(OtpChallengeBroker::class)->createOtp(
            $challenge,
            OtpDestination::email('user@example.com'),
        );

        self::assertNotSame('', $dispatch->plaintextCode);
        self::assertDatabaseMissing('sp_jwt_mfa_otp_codes', ['code_hash' => $dispatch->plaintextCode]);
        self::assertSame('u***@example.com', MfaOtpCode::query()->find($dispatch->otpId)->destination_masked);

        $context = app(OtpChallengeBroker::class)->verifyOtp($challenge->id, $dispatch->plaintextCode);

        self::assertSame('tenant', $context->subjectType);
        self::assertSame(42, $context->claims['tenant_id']);
    }

    public function test_otp_locks_after_max_attempts(): void
    {
        $challenge = app(MfaChallengeBroker::class)->create($this->createUser(), TokenContext::make());
        $dispatch = app(OtpChallengeBroker::class)->createOtp($challenge, OtpDestination::email('user@example.com'));

        for ($i = 0; $i < 5; $i++) {
            try {
                app(OtpChallengeBroker::class)->verifyOtp($challenge->id, '000000');
            } catch (AuthenticationException) {
            }
        }

        $this->expectException(AuthenticationException::class);

        app(OtpChallengeBroker::class)->verifyOtp($challenge->id, $dispatch->plaintextCode);
    }

    public function test_email_verification_token_is_hashed_and_single_use(): void
    {
        $dispatch = app(EmailVerificationBroker::class)->createVerificationToken(
            $this->createUser(),
            'user@example.com',
        );

        self::assertDatabaseMissing('sp_jwt_email_verification_tokens', ['token_hash' => $dispatch->plaintextToken]);

        $result = app(EmailVerificationBroker::class)->verifyEmailToken($dispatch->token);

        self::assertSame('user@example.com', $result->email);

        $this->expectException(AuthenticationException::class);
        app(EmailVerificationBroker::class)->verifyEmailToken($dispatch->token);
    }

    public function test_password_reset_token_is_hashed_and_single_use(): void
    {
        $dispatch = app(PasswordResetBroker::class)->createResetToken(
            $this->createUser(),
            'user@example.com',
        );

        self::assertDatabaseMissing('sp_jwt_password_reset_tokens', ['token_hash' => $dispatch->plaintextToken]);

        $result = app(PasswordResetBroker::class)->consumeResetToken($dispatch->token);

        self::assertSame('user@example.com', $result->email);

        $this->expectException(AuthenticationException::class);
        app(PasswordResetBroker::class)->consumeResetToken($dispatch->token);
    }
}
