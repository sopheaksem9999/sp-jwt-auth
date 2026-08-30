<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

final readonly class OtpDeliveryResult
{
    public function __construct(
        public bool $successful,
        public ?string $providerReference = null,
        public ?string $failureReason = null,
    ) {
    }

    public static function success(?string $providerReference = null): self
    {
        return new self(true, $providerReference);
    }

    public static function failure(?string $failureReason = null, ?string $providerReference = null): self
    {
        return new self(false, $providerReference, $failureReason);
    }
}
