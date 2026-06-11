<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Sopheak\JwtAuth\Models\OAuthClient;

final readonly class OAuthClientSecret
{
    public function __construct(
        public OAuthClient $client,
        public ?string $plaintextSecret,
    ) {
    }
}
