<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\Models\ApiKey;

final readonly class ApiKeyRotated
{
    public function __construct(
        public string $oldApiKeyId,
        public ApiKey $newApiKey,
    ) {
    }
}
