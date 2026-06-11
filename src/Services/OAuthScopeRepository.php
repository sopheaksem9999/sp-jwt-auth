<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use InvalidArgumentException;
use Sopheak\JwtAuth\Models\OAuthClient;

final class OAuthScopeRepository
{
    public function parse(?string $scope): array
    {
        if ($scope === null || trim($scope) === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', trim($scope)), static fn (string $item): bool => $item !== ''));
    }

    public function validateForClient(OAuthClient $client, array $scopes): array
    {
        foreach ($scopes as $scope) {
            if (! is_string($scope) || $scope === '' || ! $client->allowsScope($scope)) {
                throw new InvalidArgumentException('OAuth scope is not allowed for this client.');
            }
        }

        return array_values(array_unique($scopes));
    }
}
