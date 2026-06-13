<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sopheak\JwtAuth\Support\JwtKeyEnvironment;

final class JwtKeyEnvironmentTest extends TestCase
{
    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = sys_get_temp_dir() . '/sp-jwt-auth-env-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->envPath)) {
            unlink($this->envPath);
        }

        parent::tearDown();
    }

    public function test_it_writes_key_paths_and_creates_refresh_hash_key(): void
    {
        $createdRefreshHash = JwtKeyEnvironment::write(
            envPath: $this->envPath,
            kid: '2026-06-primary',
            privateKeyPath: 'storage/jwt-private-2026-06-primary.key',
            publicKeyPath: 'storage/jwt-public-2026-06-primary.key',
            refreshHashKey: '781578bb741cc355a3315f7bc9fa20877570b8f04aa7f4f2afd016c8ae854453',
        );

        self::assertTrue($createdRefreshHash);
        self::assertSame(<<<'ENV'
SP_JWT_ACTIVE_KID=2026-06-primary
SP_JWT_PRIVATE_KEY_PATH=storage/jwt-private-2026-06-primary.key
SP_JWT_PUBLIC_KEY_PATH=storage/jwt-public-2026-06-primary.key
SP_JWT_REFRESH_HASH_KEY=781578bb741cc355a3315f7bc9fa20877570b8f04aa7f4f2afd016c8ae854453

ENV, file_get_contents($this->envPath));
    }

    public function test_it_preserves_existing_refresh_hash_key_when_key_paths_change(): void
    {
        file_put_contents($this->envPath, <<<'ENV'
SP_JWT_ACTIVE_KID=old
SP_JWT_PRIVATE_KEY_PATH=storage/old-private.key
SP_JWT_PUBLIC_KEY_PATH=storage/old-public.key
SP_JWT_REFRESH_HASH_KEY=existing-secret

ENV);

        $createdRefreshHash = JwtKeyEnvironment::write(
            envPath: $this->envPath,
            kid: '2026-07-primary',
            privateKeyPath: 'storage/jwt-private-2026-07-primary.key',
            publicKeyPath: 'storage/jwt-public-2026-07-primary.key',
            refreshHashKey: 'new-secret-that-should-not-be-written',
        );

        self::assertFalse($createdRefreshHash);
        self::assertSame(<<<'ENV'
SP_JWT_ACTIVE_KID=2026-07-primary
SP_JWT_PRIVATE_KEY_PATH=storage/jwt-private-2026-07-primary.key
SP_JWT_PUBLIC_KEY_PATH=storage/jwt-public-2026-07-primary.key
SP_JWT_REFRESH_HASH_KEY=existing-secret

ENV, file_get_contents($this->envPath));
    }
}
