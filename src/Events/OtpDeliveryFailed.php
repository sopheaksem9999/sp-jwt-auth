<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\DTO\OtpDeliveryResult;
use Sopheak\JwtAuth\DTO\OtpDispatch;

final readonly class OtpDeliveryFailed
{
    public function __construct(
        public OtpDispatch $dispatch,
        public OtpDeliveryResult $result,
    ) {
    }
}
