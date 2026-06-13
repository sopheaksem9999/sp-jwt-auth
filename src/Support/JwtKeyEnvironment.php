<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

final class JwtKeyEnvironment
{
    public static function write(
        string $envPath,
        string $kid,
        string $privateKeyPath,
        string $publicKeyPath,
        ?string $refreshHashKey = null,
    ): bool {
        EnvFile::put($envPath, 'SP_JWT_ACTIVE_KID', $kid);
        EnvFile::put($envPath, 'SP_JWT_PRIVATE_KEY_PATH', $privateKeyPath);
        EnvFile::put($envPath, 'SP_JWT_PUBLIC_KEY_PATH', $publicKeyPath);

        return EnvFile::put(
            $envPath,
            'SP_JWT_REFRESH_HASH_KEY',
            $refreshHashKey ?? bin2hex(random_bytes(32)),
            overwrite: false,
        );
    }
}
