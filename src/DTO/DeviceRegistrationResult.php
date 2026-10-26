<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Sopheak\JwtAuth\Models\Device;

final readonly class DeviceRegistrationResult
{
    /** @param string $credential `{public_id}.{secret}`, shown once; only its hash is stored. */
    public function __construct(
        public Device $device,
        public string $credential,
        public TokenPair $tokens,
    ) {
    }
}
