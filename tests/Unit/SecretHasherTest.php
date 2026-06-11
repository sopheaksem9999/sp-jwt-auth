<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use Sopheak\JwtAuth\Security\HashKeyRepository;
use Sopheak\JwtAuth\Security\SecretHasher;
use Sopheak\JwtAuth\Tests\TestCase;

final class SecretHasherTest extends TestCase
{
    public function test_hashes_and_verifies_secret_with_active_key(): void
    {
        $hasher = new SecretHasher(new HashKeyRepository());

        $result = $hasher->hash('refresh-secret');

        self::assertSame('test-hash', $result['hash_key_id']);
        self::assertTrue($hasher->verify('refresh-secret', $result['hash'], $result['hash_key_id']));
        self::assertFalse($hasher->verify('wrong-secret', $result['hash'], $result['hash_key_id']));
    }
}
