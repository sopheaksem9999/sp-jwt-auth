<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\Models\OAuthClient;

final readonly class OAuthClientSecretRotated
{
    public function __construct(public OAuthClient $client)
    {
    }
}
