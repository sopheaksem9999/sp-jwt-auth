<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

final readonly class TokenRevoked
{
    public function __construct(public string $tokenId)
    {
    }
}
