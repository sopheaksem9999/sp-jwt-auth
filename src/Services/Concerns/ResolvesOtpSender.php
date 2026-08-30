<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services\Concerns;

use Closure;
use Illuminate\Support\Facades\Event;
use Sopheak\JwtAuth\Contracts\OtpChannelSender;
use Sopheak\JwtAuth\Contracts\OtpDeliveryAwareSender;
use Sopheak\JwtAuth\DTO\OtpDeliveryResult;
use Sopheak\JwtAuth\DTO\OtpDispatch;
use Sopheak\JwtAuth\Events\OtpCodeSent;
use Sopheak\JwtAuth\Events\OtpDeliveryFailed;
use Sopheak\JwtAuth\Exceptions\OtpDeliveryFailedException;
use Throwable;

trait ResolvesOtpSender
{
    /**
     * Deliver a code through whichever sender contract the application bound.
     *
     * Does nothing when no sender is bound, preserving the pre-0.1.23 behaviour
     * of sending nothing and dispatching no delivery events.
     *
     * A delivery that fails — whether reported as an unsuccessful result or
     * raised as an exception, on either contract — rolls the challenge back so
     * no row is left behind to consume the resend cooldown.
     *
     * @param  Closure():void  $rollback  Undoes the persisted challenge.
     *
     * @throws OtpDeliveryFailedException When a result-aware sender reports failure.
     * @throws Throwable Rethrown unchanged when a sender raises instead of reporting.
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

        $resultAware = $sender instanceof OtpDeliveryAwareSender;

        try {
            if ($resultAware) {
                $result = $sender->deliver($dispatch);
            } else {
                $sender->send($dispatch);

                $result = null;
            }
        } catch (Throwable $throwable) {
            // A raising sender reports no result, so record the exception class
            // rather than its message: messages routinely carry gateway URLs and
            // credentials, and this event is designed to be logged.
            $this->failOtpDelivery($dispatch, $rollback, OtpDeliveryResult::failure($throwable::class));

            throw $throwable;
        }

        // Legacy void contract: delivery outcome is unknowable, so OtpCodeSent
        // carries no result rather than asserting an unverified success.
        if (! $result instanceof OtpDeliveryResult) {
            Event::dispatch(new OtpCodeSent($dispatch));

            return;
        }

        if (! $result->successful) {
            $this->failOtpDelivery($dispatch, $rollback, $result);

            throw new OtpDeliveryFailedException($result->failureReason, $result->providerReference);
        }

        Event::dispatch(new OtpCodeSent($dispatch, $result));
    }

    /**
     * @param  Closure():void  $rollback
     */
    private function failOtpDelivery(OtpDispatch $dispatch, Closure $rollback, OtpDeliveryResult $result): void
    {
        $rollback();

        Event::dispatch(new OtpDeliveryFailed($dispatch, $result));
    }
}
