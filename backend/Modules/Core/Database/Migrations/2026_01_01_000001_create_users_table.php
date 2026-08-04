<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\PlatformBlueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->string('email')->unique('uk_users_email');
            $table->string('name');
            $table->string('type', 50)->index('idx_users_type'); // 'user' or 'admin'
            $table->string('password_hash')->nullable();
            $table->string('preferred_locale', 10)->default('ar');
            $table->boolean('is_active')->default(true);
            $table->boolean('mfa_enabled')->default(false);
            $table->string('mfa_secret')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            PlatformBlueprint::softDeletes($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
