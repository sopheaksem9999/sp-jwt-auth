<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\Models\Device;

final readonly class DeviceRevoked
{
    /** @param Authenticatable|null $user the user who revoked it, or null when the whole device was revoked */
    public function __construct(public Device $device, public ?Authenticatable $user = null)
    {
    }
}
