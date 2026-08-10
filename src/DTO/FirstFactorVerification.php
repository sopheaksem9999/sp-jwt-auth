<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class FirstFactorVerification
{
    public function __construct(
        public Authenticatable $user,
        public TokenPair $pair,
    ) {
    }
}
