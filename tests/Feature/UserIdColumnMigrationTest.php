<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Sopheak\JwtAuth\Tests\TestCase;

final class UserIdColumnMigrationTest extends TestCase
{
    public function test_user_id_columns_are_integer_by_default(): void
    {
        $this->assertSame('integer', Schema::getColumnType('sp_jwt_access_tokens', 'user_id'));
        $this->assertSame('integer', Schema::getColumnType('sp_jwt_refresh_tokens', 'user_id'));
        $this->assertSame('integer', Schema::getColumnType('sp_jwt_mfa_challenges', 'user_id'));
        $this->assertSame('integer', Schema::getColumnType('sp_oauth_consents', 'user_id'));
    }
}

