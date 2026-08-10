<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

final readonly class FirstFactorOtpExpired
{
    public function __construct(public string $otpId)
    {
    }
}
