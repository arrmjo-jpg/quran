<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\PlatformBlueprint;

/**
 * Global lookup catalog for contestant participation types (male/female/mixed).
 * A lookup table rather than an enum so new types can be added without a
 * schema change — seasons reference a row by id, never a hardcoded string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participation_types', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->string('code', 50)->unique('uk_participation_types_code');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_types');
    }
};
