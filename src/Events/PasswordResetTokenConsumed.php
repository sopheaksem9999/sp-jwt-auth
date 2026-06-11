<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\DTO\PasswordResetResult;

final readonly class PasswordResetTokenConsumed
{
    public function __construct(public PasswordResetResult $result)
    {
    }
}
