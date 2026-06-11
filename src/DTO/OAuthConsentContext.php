<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

final readonly class OAuthConsentContext
{
    public function __construct(
        public array $scopes = [],
        public array $claims = [],
        public bool $remember = false,
    ) {
    }
}
