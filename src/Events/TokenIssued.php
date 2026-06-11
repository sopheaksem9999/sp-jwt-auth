<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\TokenPair;

final readonly class TokenIssued
{
    public function __construct(
        public Authenticatable $user,
        public TokenPair $pair,
    ) {
    }
}
