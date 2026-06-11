<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_jwt_external_identities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 80);
            $table->string('provider_user_id', 191);
            $table->string('user_type')->nullable();
            $table->string('user_id', 64)->nullable();
            $table->string('email')->nullable()->index('sp_jwt_external_identities_email_index');
            $table->boolean('email_verified')->default(false);
            $table->string('name')->nullable();
            $table->text('avatar')->nullable();
            $table->json('raw_profile')->nullable();
            $table->json('provider_tokens')->nullable();
            $table->timestamp('last_resolved_at')->nullable()->index('sp_jwt_external_identities_resolved_index');
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id'], 'sp_jwt_external_identities_provider_unique');
            $table->index(['user_type', 'user_id'], 'sp_jwt_external_identities_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_jwt_external_identities');
    }
};
