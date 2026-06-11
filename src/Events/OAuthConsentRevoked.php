<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\Models\OAuthClient;

final readonly class OAuthConsentRevoked
{
    public function __construct(
        public Authenticatable $user,
        public OAuthClient $client,
    ) {
    }
}
