<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\DTO\EmailVerificationDispatch;

final readonly class EmailVerificationTokenCreated
{
    public function __construct(public EmailVerificationDispatch $dispatch)
    {
    }
}
