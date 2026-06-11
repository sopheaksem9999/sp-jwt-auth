<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class MfaOtpCode extends Model
{
    protected $table = 'sp_jwt_mfa_otp_codes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'last_sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
