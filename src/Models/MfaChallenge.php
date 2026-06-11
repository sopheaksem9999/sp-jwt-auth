<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class MfaChallenge extends Model
{
    protected $table = 'sp_jwt_mfa_challenges';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'methods' => 'array',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
