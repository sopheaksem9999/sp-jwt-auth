<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_oauth_clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('owner_type')->nullable();
            $table->string('owner_id', 64)->nullable();
            $table->string('name');
            $table->boolean('confidential')->default(true);
            $table->boolean('first_party')->default(false);
            $table->json('redirect_uris');
            $table->json('allowed_grants');
            $table->json('allowed_scopes');
            $table->string('secret_hash', 128)->nullable();
            $table->string('hash_key_id', 100)->nullable()->index('sp_oauth_clients_hash_key_index');
            $table->string('secret_preview', 30)->nullable();
            $table->timestamp('revoked_at')->nullable()->index('sp_oauth_clients_revoked_index');
            $table->timestamps();
            $table->index(['owner_type', 'owner_id'], 'sp_oauth_clients_owner_index');
        });

        Schema::create('sp_oauth_consents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('user_type');
            $table->string('user_id', 64);
            $table->json('scopes');
            $table->timestamp('revoked_at')->nullable()->index('sp_oauth_consents_revoked_index');
            $table->timestamps();
            $table->unique(['client_id', 'user_type', 'user_id'], 'sp_oauth_consents_subject_unique');
        });

        Schema::create('sp_oauth_auth_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->index('sp_oauth_auth_codes_client_index');
            $table->string('user_type');
            $table->string('user_id', 64);
            $table->string('code_hash', 128);
            $table->string('hash_key_id', 100)->nullable()->index('sp_oauth_auth_codes_hash_key_index');
            $table->string('redirect_uri', 2048);
            $table->json('scopes');
            $table->json('claims');
            $table->string('code_challenge')->nullable();
            $table->string('code_challenge_method', 20)->nullable();
            $table->timestamp('revoked_at')->nullable()->index('sp_oauth_auth_codes_revoked_index');
            $table->timestamp('expires_at')->index('sp_oauth_auth_codes_expiry_index');
            $table->timestamps();
        });

        Schema::create('sp_oauth_access_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->index('sp_oauth_access_tokens_client_index');
            $table->string('user_type')->nullable();
            $table->string('user_id', 64)->nullable();
            $table->string('grant_type', 80);
            $table->string('public_id', 80)->unique('sp_oauth_access_tokens_public_unique');
            $table->string('secret_hash', 128);
            $table->string('hash_key_id', 100)->nullable()->index('sp_oauth_access_tokens_hash_key_index');
            $table->json('scopes');
            $table->json('claims');
            $table->timestamp('last_used_at')->nullable()->index('sp_oauth_access_tokens_last_used_index');
            $table->timestamp('revoked_at')->nullable()->index('sp_oauth_access_tokens_revoked_index');
            $table->timestamp('expires_at')->index('sp_oauth_access_tokens_expiry_index');
            $table->timestamps();
            $table->index(['user_type', 'user_id'], 'sp_oauth_access_tokens_user_index');
        });

        Schema::create('sp_oauth_refresh_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('access_token_id')->index('sp_oauth_refresh_tokens_access_index');
            $table->uuid('client_id')->index('sp_oauth_refresh_tokens_client_index');
            $table->string('user_type')->nullable();
            $table->string('user_id', 64)->nullable();
            $table->string('public_id', 80)->unique('sp_oauth_refresh_tokens_public_unique');
            $table->string('secret_hash', 128);
            $table->string('hash_key_id', 100)->nullable()->index('sp_oauth_refresh_tokens_hash_key_index');
            $table->json('scopes');
            $table->json('claims');
            $table->timestamp('revoked_at')->nullable()->index('sp_oauth_refresh_tokens_revoked_index');
            $table->timestamp('expires_at')->index('sp_oauth_refresh_tokens_expiry_index');
            $table->timestamps();
            $table->index(['user_type', 'user_id'], 'sp_oauth_refresh_tokens_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_oauth_refresh_tokens');
        Schema::dropIfExists('sp_oauth_access_tokens');
        Schema::dropIfExists('sp_oauth_auth_codes');
        Schema::dropIfExists('sp_oauth_consents');
        Schema::dropIfExists('sp_oauth_clients');
    }
};
