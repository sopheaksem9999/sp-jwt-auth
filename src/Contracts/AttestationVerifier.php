<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

use Sopheak\JwtAuth\DTO\DeviceRegistration;
use Sopheak\JwtAuth\Models\Device;

/**
 * Verifies platform attestation (Apple App Attest, Google Play Integrity, ...).
 * Bind your own implementation in the container; the default accepts nothing.
 */
interface AttestationVerifier
{
    /** @param array<string, mixed> $attestation */
    public function verify(DeviceRegistration|Device $subject, array $attestation): bool;
}
