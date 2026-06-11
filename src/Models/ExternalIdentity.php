<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class ExternalIdentity extends Model
{
    protected $table = 'sp_jwt_external_identities';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'email_verified' => 'boolean',
        'raw_profile' => 'array',
        'provider_tokens' => 'array',
        'last_resolved_at' => 'datetime',
    ];
}
