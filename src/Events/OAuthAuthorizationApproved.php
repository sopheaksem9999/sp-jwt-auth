<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\OAuthAuthorizationRequest;
use Sopheak\JwtAuth\Models\OAuthAuthorizationCode;

final readonly class OAuthAuthorizationApproved
{
    public function __construct(
        public OAuthAuthorizationRequest $request,
        public Authenticatable $user,
        public OAuthAuthorizationCode $code,
    ) {
    }
}
