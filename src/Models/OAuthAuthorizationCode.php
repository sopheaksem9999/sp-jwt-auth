<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class OAuthAuthorizationCode extends Model
{
    protected $table = 'sp_oauth_auth_codes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
        'claims' => 'array',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
