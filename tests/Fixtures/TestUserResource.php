<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

final class TestUserResource
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Authenticatable $user): array
    {
        return ['id' => $user->getAuthIdentifier(), 'resource' => true];
    }
}
