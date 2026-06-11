<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class OAuthConsent extends Model
{
    protected $table = 'sp_oauth_consents';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
        'revoked_at' => 'datetime',
    ];
}
