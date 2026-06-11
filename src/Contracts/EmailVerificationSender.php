<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

use Sopheak\JwtAuth\DTO\EmailVerificationDispatch;

interface EmailVerificationSender
{
    public function send(EmailVerificationDispatch $dispatch): void;
}
