<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\TokenContext;

interface TokenContextValidator
{
    public function validate(Authenticatable $user, TokenContext $context): void;
}
