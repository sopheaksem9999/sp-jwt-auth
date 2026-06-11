<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\DTO\TokenPair;
use Sopheak\JwtAuth\Models\JwtRefreshToken;

final readonly class TokenRefreshed
{
    public function __construct(
        public JwtRefreshToken $previousRefreshToken,
        public TokenPair $pair,
    ) {
    }
}
