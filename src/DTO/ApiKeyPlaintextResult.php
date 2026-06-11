<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Sopheak\JwtAuth\Models\ApiKey;

final readonly class ApiKeyPlaintextResult
{
    public function __construct(
        public string $plaintextKey,
        public ApiKey $apiKey,
    ) {
    }
}
