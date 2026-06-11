<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\DTO\OAuthAuthorizationRequest;

final readonly class OAuthAuthorizationDenied
{
    public function __construct(public OAuthAuthorizationRequest $request)
    {
    }
}
