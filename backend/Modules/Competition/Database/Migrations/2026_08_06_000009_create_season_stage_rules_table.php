<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\PlatformBlueprint;

/**
 * Per-stage judging rules: which score scale a stage is judged on, and the
 * qualification percentage required to advance from it. One row per stage.
 * qualification_percentage is nullable — a final stage may rank without
 * eliminating anyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_stage_rules', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('season_id');
            $table->uuid('stage_id');
            $table->uuid('judge_score_system_id');
            $table->decimal('qualification_percentage', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'stage_id'], 'uk_season_stage_rules_season_stage');

            $table->foreign('season_id', 'fk_season_stage_rules_season_id')
                ->references('id')
                ->on('seasons')
                ->onDelete('RESTRICT');

            $table->foreign('stage_id', 'fk_season_stage_rules_stage_id')
                ->references('id')
                ->on('stages')
                ->onDelete('RESTRICT');

            $table->foreign('judge_score_system_id', 'fk_season_stage_rules_score_system_id')
                ->references('id')
                ->on('judge_score_systems')
                ->onDelete('RESTRICT');
        });

        // CHECK constraints aren't part of Laravel's Schema Builder DSL —
        // raw SQL, MySQL-only (matches the pattern already used for the
        // seasons/stages datetime-column fix migration).
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE season_stage_rules ADD CONSTRAINT chk_season_stage_rules_qualification_pct CHECK (qualification_percentage IS NULL OR qualification_percentage BETWEEN 0 AND 100)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('season_stage_rules');
    }
};
