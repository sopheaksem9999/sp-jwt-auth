<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sopheak\JwtAuth\Models\ApiKey;
use Sopheak\JwtAuth\Models\Device;
use Sopheak\JwtAuth\Models\EmailVerificationToken;
use Sopheak\JwtAuth\Models\ExternalIdentity;
use Sopheak\JwtAuth\Models\FirstFactorOtpCode;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Models\MfaOtpCode;
use Sopheak\JwtAuth\Models\OAuthAccessToken;
use Sopheak\JwtAuth\Models\OAuthAuthorizationCode;
use Sopheak\JwtAuth\Models\OAuthClient;
use Sopheak\JwtAuth\Models\OAuthRefreshToken;
use Sopheak\JwtAuth\Models\PasswordResetToken;

final class ModelSecurityHiddenAttributesTest extends TestCase
{
    public function test_security_models_hide_hashes_and_tokens_from_array_and_json(): void
    {
        $models = [
            ApiKey::class => ['secret_hash', 'hash_key_id'],
            JwtRefreshToken::class => ['secret_hash', 'hash_key_id'],
            OAuthClient::class => ['secret_hash', 'hash_key_id'],
            OAuthAccessToken::class => ['secret_hash', 'hash_key_id'],
            OAuthRefreshToken::class => ['secret_hash', 'hash_key_id'],
            OAuthAuthorizationCode::class => ['code_hash', 'hash_key_id'],
            FirstFactorOtpCode::class => ['code_hash', 'destination_hash', 'hash_key_id'],
            MfaOtpCode::class => ['code_hash', 'hash_key_id'],
            PasswordResetToken::class => ['token_hash', 'hash_key_id'],
            EmailVerificationToken::class => ['token_hash', 'email_hash', 'hash_key_id'],
            ExternalIdentity::class => ['provider_tokens'],
            Device::class => ['secret_hash', 'hash_key_id'],
        ];

        foreach ($models as $class => $hiddenFields) {
            $instance = new $class();
            $hidden = $instance->getHidden();

            foreach ($hiddenFields as $field) {
                self::assertContains(
                    $field,
                    $hidden,
                    sprintf('Model [%s] must hide attribute [%s].', $class, $field)
                );
            }
        }
    }
}
