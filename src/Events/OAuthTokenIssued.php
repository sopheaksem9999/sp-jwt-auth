<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\Models\OAuthAccessToken;

final readonly class OAuthTokenIssued
{
    public function __construct(public OAuthAccessToken $accessToken)
    {
    }
}
