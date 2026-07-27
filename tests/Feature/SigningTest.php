<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use RuntimeException;
use Sopheak\JwtAuth\Signing\ConfigSigningKeyRepository;
use Sopheak\JwtAuth\Signing\JwksFormatter;
use Sopheak\JwtAuth\Signing\SigningKey;
use Sopheak\JwtAuth\Tests\TestCase;

final class SigningTest extends TestCase
{
    public function test_active_key_retrieval(): void
    {
        $repo = new ConfigSigningKeyRepository();
        $key = $repo->active();

        self::assertSame('test-active', $key->kid);
        self::assertTrue($key->canSign());
        self::assertTrue($key->canVerify());
    }

    public function test_active_key_throws_exception_when_kid_is_empty(): void
    {
        config()->set('sp-jwt-auth.keys.active_kid', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active JWT key id is configured.');

        (new ConfigSigningKeyRepository())->active();
    }

    public function test_for_verification_throws_exception_when_key_unconfigured(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JWT key [missing-kid] is not configured.');

        (new ConfigSigningKeyRepository())->forVerification('missing-kid');
    }

    public function test_throws_exception_when_key_is_compromised(): void
    {
        config()->set('sp-jwt-auth.keys.items.compromised-kid', [
            'state' => 'compromised',
            'public_key' => self::publicKey(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JWT key [compromised-kid] is compromised.');

        (new ConfigSigningKeyRepository())->forVerification('compromised-kid');
    }

    public function test_public_keys_filtering(): void
    {
        config()->set('sp-jwt-auth.keys.items.prev-key', [
            'state' => 'previous',
            'public_key' => self::publicKey(),
        ]);

        $repo = new ConfigSigningKeyRepository();

        $allKeys = $repo->publicKeys(activeOnly: false);
        self::assertCount(2, $allKeys);

        $activeOnly = $repo->publicKeys(activeOnly: true);
        self::assertCount(1, $activeOnly);
        self::assertSame('test-active', $activeOnly[0]->kid);
    }

    public function test_jwks_formatter_formats_valid_rsa_key(): void
    {
        $repo = new ConfigSigningKeyRepository();
        $formatter = new JwksFormatter();

        $jwks = $formatter->format($repo->publicKeys());

        self::assertArrayHasKey('keys', $jwks);
        self::assertCount(1, $jwks['keys']);
        self::assertSame('test-active', $jwks['keys'][0]['kid']);
        self::assertSame('RSA', $jwks['keys'][0]['kty']);
        self::assertSame('RS256', $jwks['keys'][0]['alg']);
    }

    public function test_jwks_formatter_throws_exception_for_invalid_public_key(): void
    {
        $key = new SigningKey(
            kid: 'invalid',
            algorithm: 'RS256',
            privateKey: null,
            publicKey: 'INVALID KEY MATERIAL',
            state: 'active',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JWT public key [invalid] is invalid.');

        (new JwksFormatter())->format([$key]);
    }
}
