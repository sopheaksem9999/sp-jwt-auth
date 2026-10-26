<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\OAuthAuthorizationRequest;

final readonly class OAuthAuthorizationDenied
{
    public function __construct(public OAuthAuthorizationRequest $request, public ?Authenticatable $user = null)
    {
    }
}
