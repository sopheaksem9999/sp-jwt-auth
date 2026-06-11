<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Traits;

use Sopheak\JwtAuth\Models\JwtAccessToken;

trait HasJwtTokens
{
    protected ?JwtAccessToken $spJwtAccessToken = null;

    public function withAccessToken(JwtAccessToken $token): static
    {
        $this->spJwtAccessToken = $token;

        return $this;
    }

    public function token(): ?JwtAccessToken
    {
        return $this->spJwtAccessToken;
    }

    public function tokenCan(string $scope): bool
    {
        return $this->token()?->can($scope) ?? false;
    }
}
