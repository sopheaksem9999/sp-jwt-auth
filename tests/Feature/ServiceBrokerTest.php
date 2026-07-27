<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Models\EmailVerificationToken;
use Sopheak\JwtAuth\Models\PasswordResetToken;
use Sopheak\JwtAuth\Services\EmailVerificationBroker;
use Sopheak\JwtAuth\Services\MfaChallengeBroker;
use Sopheak\JwtAuth\Services\OtpChallengeBroker;
use Sopheak\JwtAuth\Services\PasswordResetBroker;
use Sopheak\JwtAuth\Tests\TestCase;

final class ServiceBrokerTest extends TestCase
{
    public function test_email_verification_broker_flow(): void
    {
        $user = $this->createUser();
        $broker = app(EmailVerificationBroker::class);

        // 1. Create verification token
        $dispatch = $broker->createVerificationToken($user, $user->email);
        self::assertSame($user->email, $dispatch->email);
        self::assertNotNull($dispatch->token);

        // 2. Verify email token
        $result = $broker->verifyEmailToken($dispatch->token);
        self::assertSame($user->email, $result->email);
        self::assertSame($user->getAuthIdentifier(), $result->user->getAuthIdentifier());

        // 3. Re-verify fails
        $this->expectException(AuthenticationException::class);
        $broker->verifyEmailToken($dispatch->token);
    }

    public function test_email_verification_resend_and_revoke(): void
    {
        $user = $this->createUser();
        $broker = app(EmailVerificationBroker::class);

        $dispatch = $broker->createVerificationToken($user, $user->email);
        $resent = $broker->resendVerificationToken($dispatch->tokenId);
        self::assertNotEquals($dispatch->token, $resent->token);

        $broker->revokeVerificationTokens($user);
        self::assertNotNull(EmailVerificationToken::query()->find($dispatch->tokenId)->verified_at);
    }

    public function test_password_reset_broker_flow(): void
    {
        $user = $this->createUser();
        $broker = app(PasswordResetBroker::class);

        // 1. Create reset token
        $dispatch = $broker->createResetToken($user, $user->email);
        self::assertSame($user->email, $dispatch->email);

        // 2. Verify without consuming
        $verifyResult = $broker->verifyResetToken($dispatch->token);
        self::assertSame($user->email, $verifyResult->email);

        // 3. Consume reset token
        $consumeResult = $broker->consumeResetToken($dispatch->token);
        self::assertSame($user->email, $consumeResult->email);

        // 4. Second consume fails
        $this->expectException(AuthenticationException::class);
        $broker->consumeResetToken($dispatch->token);
    }

    public function test_password_reset_revoke(): void
    {
        $user = $this->createUser();
        $broker = app(PasswordResetBroker::class);

        $dispatch = $broker->createResetToken($user, $user->email);
        $broker->revokeResetTokens($user);

        self::assertNotNull(PasswordResetToken::query()->find($dispatch->tokenId)->used_at);
    }

    public function test_mfa_challenge_broker_lifecycle(): void
    {
        $user = $this->createUser();
        $broker = app(MfaChallengeBroker::class);
        $context = TokenContext::make()->companyId(123);

        $challenge = $broker->create($user, $context);
        self::assertNotNull($challenge->id);

        $resolved = $broker->resolve($challenge->id);
        self::assertSame($challenge->id, $resolved->id);

        $completedContext = $broker->complete($challenge->id);
        self::assertSame(123, $completedContext->claims['company_id']);

        // Re-completing throws AuthenticationException
        $this->expectException(AuthenticationException::class);
        $broker->resolve($challenge->id);
    }

    public function test_otp_challenge_broker_flow(): void
    {
        $user = $this->createUser();
        $mfaBroker = app(MfaChallengeBroker::class);
        $otpBroker = app(OtpChallengeBroker::class);

        $challenge = $mfaBroker->create($user, TokenContext::make());
        $destination = OtpDestination::email($user->email);

        // 1. Create OTP
        $otpDispatch = $otpBroker->createOtp($challenge, $destination);
        self::assertNotEmpty($otpDispatch->plaintextCode);

        // 2. Verify OTP
        $context = $otpBroker->verifyOtp($challenge->id, $otpDispatch->plaintextCode);
        self::assertInstanceOf(TokenContext::class, $context);

        // 3. OTP revocation
        $otpDispatch2 = $otpBroker->createOtp($mfaBroker->create($user, TokenContext::make()), $destination);
        $otpBroker->revokeOtp($otpDispatch2->otpId);
    }
}
