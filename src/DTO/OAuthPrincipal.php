<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Carbon\CarbonInterface;

final readonly class OAuthPrincipal
{
    public function __construct(
        public string $clientId,
        public ?string $userType,
        public ?string $userId,
        public string $grantType,
        public array $scopes,
        public array $claims,
        public string $tokenId,
        public CarbonInterface $expiresAt,
    ) {
    }

    public function can(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
