<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On PostgreSQL a plain CREATE INDEX blocks writes while it builds. On a large
 * sp_oauth_auth_codes table, create it first with CREATE INDEX CONCURRENTLY (outside a
 * transaction); this migration then skips.
 */
return new class extends Migration {
    private const INDEX = 'sp_oauth_auth_codes_active_index';

    public function up(): void
    {
        if (Schema::hasIndex('sp_oauth_auth_codes', self::INDEX)) {
            return;
        }

        Schema::table('sp_oauth_auth_codes', function (Blueprint $table): void {
            $table->index(['client_id', 'revoked_at', 'expires_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('sp_oauth_auth_codes', self::INDEX)) {
            Schema::table('sp_oauth_auth_codes', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }
    }
};
