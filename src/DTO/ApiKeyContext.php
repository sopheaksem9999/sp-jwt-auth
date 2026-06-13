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

    public static function forCompany(
        int|string $companyId,
        string $name,
        array $scopes = [],
        array $claims = [],
        ?CarbonInterface $expiresAt = null,
        ?array $allowedIps = null,
        ?string $createdByType = null,
        ?string $createdById = null,
    ): self {
        return new self(
            ownerType: 'company',
            ownerId: (string) $companyId,
            name: $name,
            scopes: $scopes,
            claims: array_merge($claims, ['company_id' => $companyId]),
            expiresAt: $expiresAt,
            allowedIps: $allowedIps,
            createdByType: $createdByType,
            createdById: $createdById,
        );
    }
}
