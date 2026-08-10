<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Tests\TestCase;

final class FirstFactorOtpRoutesDisabledTest extends TestCase
{
    public function test_otp_routes_are_disabled_when_toggle_is_off(): void
    {
        $this->postJson('/otp/request', ['destination' => 'a@b.com', 'channel' => 'email', 'purpose' => 'login'])
            ->assertNotFound();
    }
}
