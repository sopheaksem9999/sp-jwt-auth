<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_jwt_first_factor_otp_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('channel', 30)->index('sp_jwt_ffotp_channel_index');
            $table->string('destination_hash', 128)->index('sp_jwt_ffotp_destination_index');
            $table->string('destination_masked');
            $table->string('purpose', 50)->index('sp_jwt_ffotp_purpose_index');
            $table->string('requested_type', 50)->nullable();
            $table->string('code_hash', 128);
            $table->string('hash_key_id', 100);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('last_sent_at');
            $table->timestamp('expires_at')->index('sp_jwt_ffotp_expiry_index');
            $table->timestamp('verified_at')->nullable()->index('sp_jwt_ffotp_verified_index');
            $table->timestamps();
            $table->index(['destination_hash', 'purpose', 'expires_at'], 'sp_jwt_ffotp_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_jwt_first_factor_otp_codes');
    }
};
