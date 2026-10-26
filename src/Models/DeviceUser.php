<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

/** Links a device to a user that authenticated on it. */
final class DeviceUser extends Model
{
    protected $table = 'sp_jwt_device_users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'last_authenticated_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}
