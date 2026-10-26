<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\TokenPair;
use Sopheak\JwtAuth\Models\Device;

final readonly class DeviceUpgradedToUser
{
    public function __construct(public Device $device, public Authenticatable $user, public TokenPair $pair)
    {
    }
}
