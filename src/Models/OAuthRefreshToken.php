<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class OAuthRefreshToken extends Model
{
    protected $table = 'sp_oauth_refresh_tokens';

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
