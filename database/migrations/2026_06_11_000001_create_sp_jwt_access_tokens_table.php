<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_jwt_access_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->string('user_id', 64);
            $table->uuid('session_id')->index('sp_jwt_access_tokens_session_index');
            $table->string('device_id')->nullable()->index('sp_jwt_access_tokens_device_index');
            $table->string('device_name')->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('scopes');
            $table->json('claims');
            $table->string('issuer');
            $table->string('audience')->nullable();
            $table->string('key_id', 100)->nullable()->index('sp_jwt_access_tokens_key_index');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable()->index('sp_jwt_access_tokens_last_used_index');
            $table->timestamp('revoked_at')->nullable()->index('sp_jwt_access_tokens_revoked_index');
            $table->timestamp('expires_at')->index('sp_jwt_access_tokens_expiry_index');
            $table->timestamps();

            $table->index(['user_type', 'user_id'], 'sp_jwt_access_tokens_user_index');
            $table->index(['subject_type', 'subject_id'], 'sp_jwt_access_tokens_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_jwt_access_tokens');
    }
};
