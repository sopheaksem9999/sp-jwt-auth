<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\DTO\PasswordResetDispatch;

final readonly class PasswordResetTokenCreated
{
    public function __construct(public PasswordResetDispatch $dispatch)
    {
    }
}
