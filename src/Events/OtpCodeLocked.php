<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\Models\MfaOtpCode;

final readonly class OtpCodeLocked
{
    public function __construct(public MfaOtpCode $otp)
    {
    }
}
