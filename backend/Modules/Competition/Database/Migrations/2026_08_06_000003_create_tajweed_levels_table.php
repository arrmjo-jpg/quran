<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\PlatformBlueprint;

/**
 * Global lookup catalog for the tajweed/qira'a level a season is run under.
 * Distinct from evaluation_criteria.code = 'tajweed' (a scoring category on
 * a judge's evaluation) — this classifies the season itself, not a score.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tajweed_levels', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->string('code', 50)->unique('uk_tajweed_levels_code');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tajweed_levels');
    }
};
