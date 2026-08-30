<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class OtpDeliveryFailedException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        public readonly ?string $failureReason = null,
        public readonly ?string $providerReference = null,
    ) {
        parent::__construct($failureReason ?? 'OTP delivery failed.');
    }

    public function getStatusCode(): int
    {
        return 502;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
