<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_jwt_mfa_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->string('user_id', 64);
            $table->uuid('session_id')->index('sp_jwt_mfa_challenges_session_index');
            $table->json('context');
            $table->json('methods');
            $table->timestamp('completed_at')->nullable()->index('sp_jwt_mfa_challenges_completed_index');
            $table->timestamp('expires_at')->index('sp_jwt_mfa_challenges_expiry_index');
            $table->timestamps();
            $table->index(['user_type', 'user_id'], 'sp_jwt_mfa_challenges_user_index');
        });

        Schema::create('sp_jwt_mfa_otp_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('challenge_id')->index('sp_jwt_mfa_otp_codes_challenge_index');
            $table->string('channel', 30)->index('sp_jwt_mfa_otp_codes_channel_index');
            $table->string('destination_hash', 128)->index('sp_jwt_mfa_otp_codes_destination_index');
            $table->string('destination_masked');
            $table->string('code_hash', 128);
            $table->string('hash_key_id', 100)->nullable()->index('sp_jwt_mfa_otp_codes_hash_key_index');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->unsignedSmallInteger('resend_count')->default(0);
            $table->timestamp('last_sent_at')->nullable()->index('sp_jwt_mfa_otp_codes_last_sent_index');
            $table->timestamp('verified_at')->nullable()->index('sp_jwt_mfa_otp_codes_verified_index');
            $table->timestamp('expires_at')->index('sp_jwt_mfa_otp_codes_expiry_index');
            $table->timestamps();
            $table->foreign('challenge_id')->references('id')->on('sp_jwt_mfa_challenges')->cascadeOnDelete();
        });

        Schema::create('sp_jwt_email_verification_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->string('user_id', 64);
            $table->string('email_hash', 128)->index('sp_jwt_email_verification_tokens_email_index');
            $table->string('email_masked');
            $table->string('token_hash', 128);
            $table->string('hash_key_id', 100)->nullable()->index('sp_jwt_email_verification_tokens_hash_key_index');
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable()->index('sp_jwt_email_verification_tokens_sent_index');
            $table->timestamp('verified_at')->nullable()->index('sp_jwt_email_verification_tokens_verified_index');
            $table->timestamp('expires_at')->index('sp_jwt_email_verification_tokens_expiry_index');
            $table->timestamps();
            $table->index(['user_type', 'user_id'], 'sp_jwt_email_verification_tokens_user_index');
        });

        Schema::create('sp_jwt_password_reset_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->string('user_id', 64);
            $table->string('email_hash', 128)->index('sp_jwt_password_reset_tokens_email_index');
            $table->string('email_masked');
            $table->string('token_hash', 128);
            $table->string('hash_key_id', 100)->nullable()->index('sp_jwt_password_reset_tokens_hash_key_index');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable()->index('sp_jwt_password_reset_tokens_sent_index');
            $table->timestamp('used_at')->nullable()->index('sp_jwt_password_reset_tokens_used_index');
            $table->timestamp('expires_at')->index('sp_jwt_password_reset_tokens_expiry_index');
            $table->timestamps();
            $table->index(['user_type', 'user_id'], 'sp_jwt_password_reset_tokens_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_jwt_password_reset_tokens');
        Schema::dropIfExists('sp_jwt_email_verification_tokens');
        Schema::dropIfExists('sp_jwt_mfa_otp_codes');
        Schema::dropIfExists('sp_jwt_mfa_challenges');
    }
};
