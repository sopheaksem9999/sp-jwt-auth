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

<<<<<<< HEAD
    public function companyId(): int|string|null
    {
        $value = $this->claim('company_id');

        return is_int($value) || is_string($value) ? $value : null;
    }

    public function companyIds(): array
    {
        $value = $this->claim('company_ids', []);

        return is_array($value) ? array_values($value) : [];
    }

    public function tenantId(): int|string|null
    {
        $value = $this->claim('tenant_id');

        return is_int($value) || is_string($value) ? $value : null;
    }

    public function tenantIds(): array
    {
        $value = $this->claim('tenant_ids', []);

        return is_array($value) ? array_values($value) : [];
    }

    public function isImpersonated(): bool
    {
        return (bool) $this->claim('impersonated', false);
    }

=======
>>>>>>> 11e06a7 (feat: add complete Laravel JWT auth package with OAuth support)
    public function subject(): ?TokenSubject
    {
        return $this->subject_type !== null && $this->subject_id !== null
            ? new TokenSubject((string) $this->subject_type, (string) $this->subject_id)
            : null;
    }
}
