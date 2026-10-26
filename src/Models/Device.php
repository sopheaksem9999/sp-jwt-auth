<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A registered device. Implements Authenticatable so the core token engine can issue,
 * rotate and revoke its tokens (user_type = this class, user_id = the key).
 */
final class Device extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected $table = 'sp_jwt_devices';

    protected $guarded = [];

    protected $hidden = ['secret_hash', 'hash_key_id'];

    protected $casts = [
        'metadata' => 'array',
        'attested_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        self::creating(static function (self $device): void {
            if (self::usesUuidKey() && empty($device->getKey())) {
                $device->setAttribute($device->getKeyName(), (string) Str::uuid());
            }
        });
    }

    public function getIncrementing(): bool
    {
        return ! self::usesUuidKey();
    }

    public function getKeyType(): string
    {
        return self::usesUuidKey() ? 'string' : 'int';
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    private static function usesUuidKey(): bool
    {
        return config('sp-jwt-auth.id_type', 'integer') === 'uuid';
    }
}
