<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Tests\TestCase;

final class JwksTest extends TestCase
{
    public function test_jwks_exposes_public_key_only(): void
    {
        $response = $this->getJson('/.well-known/sp-jwt-auth/jwks.json');

        $response->assertOk()
            ->assertJsonPath('keys.0.kid', 'test-active')
            ->assertJsonMissing(['private_key']);
    }
}
