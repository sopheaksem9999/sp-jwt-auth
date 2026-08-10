<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\Models\FirstFactorOtpCode;

final readonly class FirstFactorOtpVerified
{
    public function __construct(public FirstFactorOtpCode $otp)
    {
    }
}
