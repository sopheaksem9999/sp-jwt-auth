<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\OtpDestination;

interface FirstFactorUserResolver
{
    /**
     * Return the existing user for the destination, or null if none exists.
     */
    public function resolve(OtpDestination $destination, string $purpose): ?Authenticatable;

    /**
     * Create a user for the destination. Return null to reject sign-up.
     *
     * @param string|null $requestedType Account type requested by the caller (e.g. "driver", "merchant").
     */
    public function create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable;
}
