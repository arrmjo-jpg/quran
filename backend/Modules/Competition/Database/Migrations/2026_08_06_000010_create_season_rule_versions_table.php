<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\PlatformBlueprint;

/**
 * Immutable historical snapshot of a season's fully-resolved rules, taken
 * at draft -> registration_open (version 1) and potentially again on a
 * future admin-approved amendment (version 2+, not built yet — see
 * season architecture spec). Never updated or deleted; the operational
 * source of truth is always the live relational tables, not this JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_rule_versions', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('season_id');
            $table->unsignedInteger('version');
            $table->json('snapshot_json');
            $table->timestamp('created_at')->useCurrent();
            $table->uuid('created_by_user_id')->nullable();

            $table->unique(['season_id', 'version'], 'uk_season_rule_versions_season_version');

            $table->foreign('season_id', 'fk_season_rule_versions_season_id')
                ->references('id')
                ->on('seasons')
                ->onDelete('RESTRICT');

            $table->foreign('created_by_user_id', 'fk_season_rule_versions_creator')
                ->references('id')
                ->on('users')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_rule_versions');
    }
};
