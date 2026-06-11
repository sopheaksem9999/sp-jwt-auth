<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

final readonly class ExternalIdentity
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public ?string $email = null,
        public bool $emailVerified = false,
        public ?string $name = null,
        public ?string $avatar = null,
        public array $rawProfile = [],
        public array $providerTokens = [],
    ) {
    }
}
