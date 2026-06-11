<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_jwt_api_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('owner_type');
            $table->string('owner_id', 64);
            $table->string('created_by_type')->nullable();
            $table->string('created_by_id', 64)->nullable();
            $table->string('name');
            $table->string('prefix', 30)->index('sp_jwt_api_keys_prefix_index');
            $table->string('environment', 30);
            $table->string('public_id', 64)->unique('sp_jwt_api_keys_public_id_unique');
            $table->string('secret_hash', 128);
            $table->string('hash_key_id', 100)->nullable()->index('sp_jwt_api_keys_hash_key_index');
            $table->string('key_preview', 30);
            $table->json('scopes');
            $table->json('claims');
            $table->json('allowed_ips')->nullable();
            $table->timestamp('last_used_at')->nullable()->index('sp_jwt_api_keys_last_used_index');
            $table->timestamp('revoked_at')->nullable()->index('sp_jwt_api_keys_revoked_index');
            $table->timestamp('expires_at')->nullable()->index('sp_jwt_api_keys_expiry_index');
            $table->timestamps();
            $table->index(['owner_type', 'owner_id'], 'sp_jwt_api_keys_owner_index');
            $table->index(['created_by_type', 'created_by_id'], 'sp_jwt_api_keys_creator_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_jwt_api_keys');
    }
};
