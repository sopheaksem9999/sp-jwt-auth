<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Carbon\CarbonInterface;

final readonly class ApiKeyPrincipal
{
    public function __construct(
        public string $apiKeyId,
        public string $ownerType,
        public string $ownerId,
        public array $scopes,
        public array $claims,
        public ?CarbonInterface $expiresAt,
    ) {
    }

    public function can(string $scope): bool
    {
        return in_array('*', $this->scopes, true) || in_array($scope, $this->scopes, true);
    }
}
