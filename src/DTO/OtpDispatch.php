<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Sopheak\JwtAuth\Models\MfaOtpCode;

final readonly class OtpDispatch
{
    public function __construct(
        public string $otpId,
        public string $challengeId,
        public string $plaintextCode,
        public OtpDestination $destination,
        public MfaOtpCode $otp,
    ) {
    }
}
