<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Carbon\CarbonInterface;

final readonly class OAuthIntrospectionPayload
{
    public function __construct(
        public bool $active,
        public ?string $clientId = null,
        public ?string $userType = null,
        public ?string $userId = null,
        public ?string $grantType = null,
        public array $scopes = [],
        public array $claims = [],
        public ?string $tokenId = null,
        public ?CarbonInterface $expiresAt = null,
    ) {
    }
}
