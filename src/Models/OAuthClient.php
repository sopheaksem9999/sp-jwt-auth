<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class OAuthClient extends Model
{
    protected $table = 'sp_oauth_clients';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'confidential' => 'boolean',
        'first_party' => 'boolean',
        'redirect_uris' => 'array',
        'allowed_grants' => 'array',
        'allowed_scopes' => 'array',
        'revoked_at' => 'datetime',
    ];

    public function allowsRedirectUri(string $redirectUri): bool
    {
        return in_array($redirectUri, $this->redirect_uris ?? [], true);
    }

    public function allowsGrant(string $grant): bool
    {
        return in_array($grant, $this->allowed_grants ?? [], true);
    }

    public function allowsScope(string $scope): bool
    {
        return in_array($scope, $this->allowed_scopes ?? [], true);
    }
}
