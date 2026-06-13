<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\DTO\TokenPair;
use Sopheak\JwtAuth\Services\JwtTokenService;

final class JwtTokenTestHelper
{
    public static function createToken(
        Authenticatable $user,
        array $scopes = [],
        array $claims = [],
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $deviceId = null,
        ?string $deviceName = null,
        ?string $sessionId = null,
    ): TokenPair {
        $context = new TokenContext(
            scopes: $scopes,
            claims: $claims,
            subjectType: $subjectType,
            subjectId: $subjectId,
            deviceId: $deviceId,
            deviceName: $deviceName,
            sessionId: $sessionId,
        );

        return app(JwtTokenService::class)->issueTokenPair($user, $context);
    }
}
