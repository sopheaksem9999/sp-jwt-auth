<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class ApiKey extends Model
{
    protected $table = 'sp_jwt_api_keys';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
        'claims' => 'array',
        'allowed_ips' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
