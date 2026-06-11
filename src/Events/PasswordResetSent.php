<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\DTO\PasswordResetDispatch;

final readonly class PasswordResetSent
{
    public function __construct(public PasswordResetDispatch $dispatch)
    {
    }
}
