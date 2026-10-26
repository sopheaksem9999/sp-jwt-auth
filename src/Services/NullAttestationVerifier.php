<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Sopheak\JwtAuth\Contracts\AttestationVerifier;
use Sopheak\JwtAuth\DTO\DeviceRegistration;
use Sopheak\JwtAuth\Models\Device;

/**
 * Default verifier when the app binds none. It never verifies anything, so it can
 * never make a required attestation pass; DeviceService skips it when attestation
 * is optional.
 */
final class NullAttestationVerifier implements AttestationVerifier
{
    public function verify(DeviceRegistration|Device $subject, array $attestation): bool
    {
        return false;
    }
}
