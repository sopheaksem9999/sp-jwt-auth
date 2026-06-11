<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\DTO\EmailVerificationResult;

final readonly class EmailVerified
{
    public function __construct(public EmailVerificationResult $result)
    {
    }
}
