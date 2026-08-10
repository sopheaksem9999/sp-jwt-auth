<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Tests\TestCase;

final class JwtTokenRoutesDisabledTest extends TestCase
{
    public function test_token_endpoint_routes_are_disabled_by_default(): void
    {
        $this->postJson('/auth/token/refresh', ['refresh_token' => 'x.y'])
            ->assertNotFound();
    }
}
