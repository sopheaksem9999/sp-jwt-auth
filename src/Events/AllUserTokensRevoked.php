<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class AllUserTokensRevoked
{
    public function __construct(public Authenticatable $user)
    {
    }
}
