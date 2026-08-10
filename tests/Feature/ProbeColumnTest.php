<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

final class ProbeColumnTest extends TestCase
{
    public function test_probe(): void
    {
        foreach (Schema::getColumns('sp_jwt_access_tokens') as $col) {
            if ($col['name'] === 'user_id') {
                fwrite(STDERR, "\nCOL: " . json_encode($col) . "\n");
            }
        }

        fwrite(STDERR, "getColumnType: " . Schema::getColumnType('sp_jwt_access_tokens', 'user_id') . "\n");
        $this->assertTrue(true);
    }
}
