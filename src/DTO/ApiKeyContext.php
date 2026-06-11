<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Carbon\CarbonInterface;

final readonly class ApiKeyContext
{
    public function __construct(
        public string $ownerType,
        public string $ownerId,
        public string $name,
        public array $scopes = [],
        public array $claims = [],
        public ?CarbonInterface $expiresAt = null,
        public ?array $allowedIps = null,
        public ?string $createdByType = null,
        public ?string $createdById = null,
    ) {
    }
}
