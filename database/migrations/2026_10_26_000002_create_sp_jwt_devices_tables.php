<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Sopheak\JwtAuth\Support\UserIdColumn;

// Always created (like the first-factor OTP tables) and left empty unless
// sp-jwt-auth.devices.enabled is true.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_jwt_devices', function (Blueprint $table): void {
            // The key follows id_type because device tokens store it in sp_jwt_*_tokens.user_id.
            config('sp-jwt-auth.id_type', 'integer') === 'uuid'
                ? $table->uuid('id')->primary()
                : $table->id();
            $table->uuid('public_id')->unique('sp_jwt_devices_public_unique');
            $table->string('platform', 30)->index('sp_jwt_devices_platform_index');
            $table->string('name')->nullable();
            $table->string('client_version', 50)->nullable();
            $table->string('secret_hash', 128);
            $table->string('hash_key_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('attested_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index('sp_jwt_devices_last_seen_index');
            $table->timestamp('revoked_at')->nullable()->index('sp_jwt_devices_revoked_index');
            $table->timestamps();
        });

        Schema::create('sp_jwt_device_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            UserIdColumn::apply($table, 'device_id');
            $table->string('user_type');
            UserIdColumn::apply($table);
            $table->timestamp('last_authenticated_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'user_type', 'user_id'], 'sp_jwt_device_users_unique');
            $table->index(['user_type', 'user_id'], 'sp_jwt_device_users_user_index');
            $table->foreign('device_id')->references('id')->on('sp_jwt_devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_jwt_device_users');
        Schema::dropIfExists('sp_jwt_devices');
    }
};
