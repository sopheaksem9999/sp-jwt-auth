<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

final readonly class OAuthClientData
{
    public function __construct(
        public string $name,
        public array $redirectUris = [],
        public array $allowedGrants = ['authorization_code', 'refresh_token'],
        public array $allowedScopes = [],
        public bool $confidential = true,
        public bool $firstParty = false,
        public ?string $ownerType = null,
        public ?string $ownerId = null,
    ) {
    }
}
