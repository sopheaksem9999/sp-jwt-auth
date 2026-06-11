<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

final readonly class SessionRevoked
{
    public function __construct(public string $sessionId)
    {
    }
}
