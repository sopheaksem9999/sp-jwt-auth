<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

use Sopheak\JwtAuth\DTO\OtpDeliveryResult;
use Sopheak\JwtAuth\DTO\OtpDispatch;

/**
 * Result-aware counterpart to {@see OtpChannelSender}.
 *
 * Declares a distinct method name because PHP forbids widening the `void`
 * return of `OtpChannelSender::send()`, even from a subinterface. A sender may
 * implement both contracts; the brokers prefer `deliver()` when it is present.
 */
interface OtpDeliveryAwareSender
{
    public function deliver(OtpDispatch $dispatch): OtpDeliveryResult;
}
