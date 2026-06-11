<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

final readonly class ApiKeyRevoked
{
    public function __construct(public string $apiKeyId)
    {
    }
}
