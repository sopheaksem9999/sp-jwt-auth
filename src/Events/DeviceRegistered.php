<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\Models\Device;

final readonly class DeviceRegistered
{
    public function __construct(public Device $device)
    {
    }
}
