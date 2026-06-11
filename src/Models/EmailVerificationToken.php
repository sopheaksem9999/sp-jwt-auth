<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class EmailVerificationToken extends Model
{
    protected $table = 'sp_jwt_email_verification_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
