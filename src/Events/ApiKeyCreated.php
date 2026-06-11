<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\Models\ApiKey;

final readonly class ApiKeyCreated
{
    public function __construct(public ApiKey $apiKey)
    {
    }
}
