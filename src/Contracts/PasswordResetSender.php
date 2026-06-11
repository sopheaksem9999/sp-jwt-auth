<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

use Sopheak\JwtAuth\DTO\PasswordResetDispatch;

interface PasswordResetSender
{
    public function send(PasswordResetDispatch $dispatch): void;
}
