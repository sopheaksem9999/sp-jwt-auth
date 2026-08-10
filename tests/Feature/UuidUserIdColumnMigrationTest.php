<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Override;
use Illuminate\Support\Facades\Schema;
use Sopheak\JwtAuth\Tests\TestCase;

final class UuidUserIdColumnMigrationTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.id_type', 'uuid');
    }

    public function test_user_id_columns_are_uuid_when_configured(): void
    {
        $this->assertSame('varchar', Schema::getColumnType('sp_jwt_access_tokens', 'user_id'));
        $this->assertSame('varchar', Schema::getColumnType('sp_jwt_refresh_tokens', 'user_id'));
        $this->assertSame('varchar', Schema::getColumnType('sp_jwt_external_identities', 'user_id'));
    }
}
