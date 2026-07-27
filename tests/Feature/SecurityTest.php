<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use RuntimeException;
use Sopheak\JwtAuth\Security\HashKey;
use Sopheak\JwtAuth\Security\HashKeyRepository;
use Sopheak\JwtAuth\Security\SecretHasher;
use Sopheak\JwtAuth\Tests\TestCase;

final class SecurityTest extends TestCase
{
    public function test_hash_key_throws_exception_on_empty_secret(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hash key [test] is empty.');

        new HashKey('test', '', 'active');
    }

    public function test_hash_key_repository_finds_active_key(): void
    {
        $repo = new HashKeyRepository();
        $key = $repo->active();

        self::assertSame('test-hash', $key->id);
        self::assertSame('0123456789abcdef0123456789abcdef', $key->key);
        self::assertSame('active', $key->state);
    }

    public function test_hash_key_repository_throws_exception_on_unconfigured_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hash key [missing-id] is not configured.');

        $repo = new HashKeyRepository();
        $repo->find('missing-id');
    }

    public function test_secret_hasher_hashes_and_verifies(): void
    {
        $hasher = app(SecretHasher::class);
        $hashed = $hasher->hash('my-super-secret');

        self::assertArrayHasKey('hash', $hashed);
        self::assertArrayHasKey('hash_key_id', $hashed);
        self::assertSame('test-hash', $hashed['hash_key_id']);

        self::assertTrue($hasher->verify('my-super-secret', $hashed['hash'], $hashed['hash_key_id']));
        self::assertFalse($hasher->verify('wrong-secret', $hashed['hash'], $hashed['hash_key_id']));
    }

    public function test_secret_hasher_verify_with_null_key_id_uses_active(): void
    {
        $hasher = app(SecretHasher::class);
        $hashed = $hasher->hash('my-secret-2');

        self::assertTrue($hasher->verify('my-secret-2', $hashed['hash'], null));
    }
}
