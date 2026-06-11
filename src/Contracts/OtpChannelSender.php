<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

use Sopheak\JwtAuth\DTO\OtpDispatch;

interface OtpChannelSender
{
    public function send(OtpDispatch $dispatch): void;
}
