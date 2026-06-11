<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Sopheak\JwtAuth\Models\OAuthClient;

final readonly class OAuthAuthorizationRequest
{
    public function __construct(
        public OAuthClient $client,
        public string $redirectUri,
        public array $scopes = [],
        public ?string $state = null,
        public ?string $codeChallenge = null,
        public ?string $codeChallengeMethod = null,
    ) {
    }
}
