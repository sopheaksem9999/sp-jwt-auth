<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use InvalidArgumentException;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Sopheak\JwtAuth\DTO\ApiKeyContext;
use Sopheak\JwtAuth\DTO\ApiKeyPlaintextResult;
use Sopheak\JwtAuth\DTO\ApiKeyPrincipal;
use Sopheak\JwtAuth\DTO\EmailVerificationResult;
use Sopheak\JwtAuth\DTO\OAuthPrincipal;
use Sopheak\JwtAuth\DTO\OAuthTokenResponse;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\PasswordResetResult;
use Sopheak\JwtAuth\DTO\TokenSubject;
use Sopheak\JwtAuth\Models\ApiKey;
use Sopheak\JwtAuth\Tests\Fixtures\User;

final class DtoTest extends TestCase
{
    public function test_api_key_context_creation_and_methods(): void
    {
        $context = new ApiKeyContext(
            ownerType: 'user',
            ownerId: '10',
            name: 'Admin Key',
            scopes: ['read', 'write'],
            claims: ['role' => 'admin'],
            allowedIps: ['127.0.0.1'],
        );

        self::assertSame('user', $context->ownerType);
        self::assertSame('10', $context->ownerId);
        self::assertSame('Admin Key', $context->name);
        self::assertSame(['read', 'write'], $context->scopes);
        self::assertSame(['role' => 'admin'], $context->claims);
        self::assertSame(['127.0.0.1'], $context->allowedIps);

        $companyContext = ApiKeyContext::forCompany(
            companyId: 42,
            name: 'Company Key',
            scopes: ['read'],
        );
        self::assertSame('company', $companyContext->ownerType);
        self::assertSame('42', $companyContext->ownerId);
        self::assertSame(42, $companyContext->claims['company_id']);
    }

    public function test_api_key_plaintext_result_and_principal(): void
    {
        $model = new ApiKey();
        $result = new ApiKeyPlaintextResult(
            plaintextKey: 'sp_live_secret',
            apiKey: $model,
        );
        self::assertSame('sp_live_secret', $result->plaintextKey);
        self::assertSame($model, $result->apiKey);

        $principal = new ApiKeyPrincipal(
            apiKeyId: 'key_123',
            ownerType: 'User',
            ownerId: '1',
            scopes: ['read'],
            claims: [],
            expiresAt: null,
        );
        self::assertSame('key_123', $principal->apiKeyId);
        self::assertSame('User', $principal->ownerType);
        self::assertSame('1', $principal->ownerId);
        self::assertSame(['read'], $principal->scopes);
        self::assertTrue($principal->can('read'));
        self::assertFalse($principal->can('write'));
    }

    public function test_oauth_token_response_to_array(): void
    {
        $response = new OAuthTokenResponse(
            accessToken: 'acc_token',
            tokenType: 'Bearer',
            expiresIn: 3600,
            refreshToken: 'ref_token',
            scopes: ['read', 'write'],
        );

        $array = $response->toArray();

        self::assertSame('acc_token', $array['access_token']);
        self::assertSame('Bearer', $array['token_type']);
        self::assertSame(3600, $array['expires_in']);
        self::assertSame('ref_token', $array['refresh_token']);
        self::assertSame('read write', $array['scope']);
    }

    public function test_otp_destination_formatting_and_validation(): void
    {
        $emailDest = OtpDestination::email('USER@Example.com');
        self::assertSame('email', $emailDest->channel);
        self::assertSame('user@example.com', $emailDest->normalizedDestination);
        self::assertSame('u***@example.com', $emailDest->maskedDestination);

        $phoneDest = OtpDestination::phone('+1 234 567 8900');
        self::assertSame('sms', $phoneDest->channel);
        self::assertSame('+12345678900', $phoneDest->normalizedDestination);
    }

    public function test_token_subject_construction_and_formatting(): void
    {
        $subject = new TokenSubject(type: 'App\\Models\\User', id: '42');

        self::assertSame('App\\Models\\User', $subject->type);
        self::assertSame('42', $subject->id);
        self::assertSame(['type' => 'App\\Models\\User', 'id' => '42'], $subject->toArray());
    }

    public function test_token_subject_throws_exception_on_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TokenSubject(type: '', id: '42');
    }

    public function test_oauth_principal_and_can(): void
    {
        $principal = new OAuthPrincipal(
            clientId: 'client_123',
            userType: 'User',
            userId: '99',
            grantType: 'authorization_code',
            scopes: ['profile', 'email'],
            claims: [],
            tokenId: 'tok_1',
            expiresAt: Carbon::now()->addHour(),
        );

        self::assertSame('client_123', $principal->clientId);
        self::assertTrue($principal->can('profile'));
        self::assertFalse($principal->can('admin'));
    }

    public function test_verification_and_reset_dtos(): void
    {
        $user = new User();
        $user->email = 'test@example.com';

        $emailResult = new EmailVerificationResult(user: $user, email: 'test@example.com');
        self::assertSame($user, $emailResult->user);
        self::assertSame('test@example.com', $emailResult->email);

        $pwResult = new PasswordResetResult(user: $user, email: 'test@example.com');
        self::assertSame($user, $pwResult->user);
        self::assertSame('test@example.com', $pwResult->email);
    }
}
