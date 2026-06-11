<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\DTO\OtpDispatch;

final readonly class OtpCodeSent
{
    public function __construct(public OtpDispatch $dispatch)
    {
    }
}
