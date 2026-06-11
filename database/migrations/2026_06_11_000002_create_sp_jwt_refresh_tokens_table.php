<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_jwt_refresh_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('access_token_id');
            $table->string('user_type');
            $table->string('user_id', 64);
            $table->uuid('session_id')->index('sp_jwt_refresh_tokens_session_index');
            $table->string('secret_hash', 128);
            $table->string('hash_key_id', 100)->nullable()->index('sp_jwt_refresh_tokens_hash_key_index');
            $table->json('scopes');
            $table->json('claims');
            $table->uuid('replaced_by_id')->nullable()->index('sp_jwt_refresh_tokens_replaced_index');
            $table->timestamp('revoked_at')->nullable()->index('sp_jwt_refresh_tokens_revoked_index');
            $table->timestamp('expires_at')->index('sp_jwt_refresh_tokens_expiry_index');
            $table->timestamps();

            $table->index('access_token_id', 'sp_jwt_refresh_tokens_access_index');
            $table->index(['user_type', 'user_id'], 'sp_jwt_refresh_tokens_user_index');
            $table->foreign('access_token_id')
                ->references('id')
                ->on('sp_jwt_access_tokens')
                ->cascadeOnDelete();
            $table->foreign('replaced_by_id')
                ->references('id')
                ->on('sp_jwt_refresh_tokens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_jwt_refresh_tokens');
    }
};
