<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services\Concerns;

use Closure;
use Illuminate\Support\Facades\Event;
use Sopheak\JwtAuth\Contracts\OtpChannelSender;
use Sopheak\JwtAuth\Contracts\OtpDeliveryAwareSender;
use Sopheak\JwtAuth\Events\OtpCodeSent;
use Sopheak\JwtAuth\Events\OtpDeliveryFailed;
use Sopheak\JwtAuth\Exceptions\OtpDeliveryFailedException;
use Sopheak\JwtAuth\DTO\OtpDispatch;

trait ResolvesOtpSender
{
    /**
     * Deliver a code through whichever sender contract the application bound.
     *
     * Does nothing when no sender is bound, preserving the pre-0.1.23 behaviour
     * of sending nothing and dispatching no delivery events.
     *
     * @param  Closure():void  $rollback  Undoes the persisted challenge when a
     *                                    result-aware sender reports failure.
     *
     * @throws OtpDeliveryFailedException When delivery is reported as failed.
     */
    private function deliverOtp(OtpDispatch $dispatch, Closure $rollback): void
    {
        $sender = match (true) {
            app()->bound(OtpDeliveryAwareSender::class) => app(OtpDeliveryAwareSender::class),
            app()->bound(OtpChannelSender::class) => app(OtpChannelSender::class),
            default => null,
        };

        if ($sender === null) {
            return;
        }

        // Legacy void contract: delivery outcome is unknowable, so OtpCodeSent
        // carries no result rather than asserting an unverified success.
        if (! $sender instanceof OtpDeliveryAwareSender) {
            $sender->send($dispatch);

            Event::dispatch(new OtpCodeSent($dispatch));

            return;
        }

        $result = $sender->deliver($dispatch);

        if (! $result->successful) {
            $rollback();

            Event::dispatch(new OtpDeliveryFailed($dispatch, $result));

            throw new OtpDeliveryFailedException($result->failureReason, $result->providerReference);
        }

        Event::dispatch(new OtpCodeSent($dispatch, $result));
    }
}
