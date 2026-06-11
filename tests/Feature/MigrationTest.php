<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Sopheak\JwtAuth\Tests\TestCase;

final class MigrationTest extends TestCase
{
    public function test_core_token_tables_are_created(): void
    {
        self::assertTrue(Schema::hasTable('sp_jwt_access_tokens'));
        self::assertTrue(Schema::hasTable('sp_jwt_refresh_tokens'));
        self::assertTrue(Schema::hasColumns('sp_jwt_access_tokens', [
            'id',
            'user_type',
            'user_id',
            'session_id',
            'scopes',
            'claims',
            'issuer',
            'audience',
            'last_used_at',
            'revoked_at',
            'expires_at',
        ]));
        self::assertTrue(Schema::hasColumns('sp_jwt_refresh_tokens', [
            'id',
            'access_token_id',
            'user_type',
            'user_id',
            'session_id',
            'secret_hash',
            'hash_key_id',
            'scopes',
            'claims',
            'replaced_by_id',
            'revoked_at',
            'expires_at',
        ]));
    }
}
