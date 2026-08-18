<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\PlatformBlueprint;

/**
 * Global lookup catalog for judge scoring scales (e.g. out of 100, 50, 20, 10).
 * A stage picks one via season_stage_rules — never a hardcoded max score.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judge_score_systems', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->string('code', 50)->unique('uk_judge_score_systems_code');
            $table->decimal('max_score', 5, 2);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_score_systems');
    }
};
