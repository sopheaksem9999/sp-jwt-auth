<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

final readonly class OAuthClientRevoked
{
    public function __construct(public string $clientId)
    {
    }
}
