<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Carbon\CarbonImmutable;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;

final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public CarbonImmutable $accessTokenExpiresAt,
        public CarbonImmutable $refreshTokenExpiresAt,
        public JwtAccessToken $accessTokenRecord,
        public JwtRefreshToken $refreshTokenRecord,
    ) {
    }

    public function expiresIn(): int
    {
        return (int) max(0, now()->diffInSeconds($this->accessTokenExpiresAt, false));
    }
}
