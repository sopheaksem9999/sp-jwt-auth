<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Sopheak\JwtAuth\DTO\TokenSubject;

final class JwtAccessToken extends Model
{
    protected $table = 'sp_jwt_access_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
        'claims' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function can(string $scope): bool
    {
        return in_array('*', $this->scopes ?? [], true) || in_array($scope, $this->scopes ?? [], true);
    }

    public function claim(string $key, mixed $default = null): mixed
    {
        return ($this->claims ?? [])[$key] ?? $default;
    }

    public function subject(): ?TokenSubject
    {
        return $this->subject_type !== null && $this->subject_id !== null
            ? new TokenSubject((string) $this->subject_type, (string) $this->subject_id)
            : null;
    }
}
