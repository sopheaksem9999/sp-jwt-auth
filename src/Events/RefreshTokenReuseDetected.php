<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\Models\JwtRefreshToken;

final readonly class RefreshTokenReuseDetected
{
    public function __construct(public JwtRefreshToken $refreshToken)
    {
    }
}
