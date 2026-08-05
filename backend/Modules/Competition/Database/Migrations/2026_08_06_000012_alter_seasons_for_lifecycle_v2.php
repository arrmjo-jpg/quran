<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Season Architecture v2 — see the approved design spec (season is a
 * permanent historical record, never deleted; archived is a terminal
 * status reached via a real state machine, not a deleted_at column).
 *
 * - Drops deleted_at: a Season is never soft-deleted, only archived.
 * - Adds age range, participation/tajweed lookups, archive metadata,
 *   and a freeze timestamp.
 * - Adds a generated column + unique index so "at most one active
 *   season" is enforced by MySQL itself, not just application code —
 *   MySQL 8 has no partial/filtered unique index, so a STORED generated
 *   column that is NULL unless is_active=1 (and NULL values don't
 *   collide in a unique index) is the strongest guarantee available.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isMysql = Schema::getConnection()->getDriverName() === 'mysql';

        Schema::table('seasons', function (Blueprint $table): void {
            $table->unsignedTinyInteger('min_age')->nullable()->after('end_date');
            $table->unsignedTinyInteger('max_age')->nullable()->after('min_age');
            $table->uuid('participation_type_id')->nullable()->after('max_age');
            $table->uuid('tajweed_level_id')->nullable()->after('participation_type_id');
            $table->timestamp('frozen_at')->nullable()->after('is_active');
            $table->timestamp('archived_at')->nullable()->after('frozen_at');
            $table->uuid('archived_by_user_id')->nullable()->after('archived_at');
            $table->text('archive_reason')->nullable()->after('archived_by_user_id');

            $table->foreign('participation_type_id', 'fk_seasons_participation_type_id')
                ->references('id')
                ->on('participation_types')
                ->onDelete('RESTRICT');

            $table->foreign('tajweed_level_id', 'fk_seasons_tajweed_level_id')
                ->references('id')
                ->on('tajweed_levels')
                ->onDelete('RESTRICT');

            $table->foreign('archived_by_user_id', 'fk_seasons_archived_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('SET NULL');

            $table->index('year', 'idx_seasons_year');
        });

        if ($isMysql) {
            // Soft deletes are gone: a season is a permanent record.
            DB::statement('ALTER TABLE seasons DROP INDEX idx_seasons_deleted_at');
            DB::statement('ALTER TABLE seasons DROP COLUMN deleted_at');

            // Generated column: NULL unless is_active=1, so a unique
            // index on it allows unlimited inactive rows but exactly
            // one active row.
            DB::statement('ALTER TABLE seasons ADD COLUMN active_flag TINYINT AS (CASE WHEN is_active = 1 THEN 1 ELSE NULL END) STORED');
            DB::statement('ALTER TABLE seasons ADD UNIQUE KEY uk_seasons_single_active (active_flag)');

            DB::statement('ALTER TABLE seasons ADD CONSTRAINT chk_seasons_age_range CHECK (min_age IS NULL OR max_age IS NULL OR min_age <= max_age)');
            DB::statement('ALTER TABLE seasons ADD CONSTRAINT chk_seasons_registration_order CHECK (registration_end > registration_start)');
            DB::statement('ALTER TABLE seasons ADD CONSTRAINT chk_seasons_start_after_registration CHECK (start_date >= registration_end)');
            DB::statement('ALTER TABLE seasons ADD CONSTRAINT chk_seasons_end_after_start CHECK (end_date > start_date)');
        }
        // SQLite test DB: no deleted_at drop (softDeletes column stays
        // present but unused — the Season model no longer applies the
        // trait), no generated active_flag column, no CHECK constraints.
        // These are pure DB-enforcement layers; the application/domain
        // layer (SeasonStateMachine, activateSeason() atomicity) enforces
        // the same rules and is what every test exercises.
    }

    public function down(): void
    {
        $isMysql = Schema::getConnection()->getDriverName() === 'mysql';

        if ($isMysql) {
            DB::statement('ALTER TABLE seasons DROP CONSTRAINT chk_seasons_end_after_start');
            DB::statement('ALTER TABLE seasons DROP CONSTRAINT chk_seasons_start_after_registration');
            DB::statement('ALTER TABLE seasons DROP CONSTRAINT chk_seasons_registration_order');
            DB::statement('ALTER TABLE seasons DROP CONSTRAINT chk_seasons_age_range');
            DB::statement('ALTER TABLE seasons DROP INDEX uk_seasons_single_active');
            DB::statement('ALTER TABLE seasons DROP COLUMN active_flag');
            DB::statement('ALTER TABLE seasons ADD COLUMN deleted_at TIMESTAMP NULL');
            DB::statement('ALTER TABLE seasons ADD INDEX idx_seasons_deleted_at (deleted_at)');
        }
        // SQLite: deleted_at/active_flag were never touched in up(), so
        // there's nothing to reverse here.

        Schema::table('seasons', function (Blueprint $table): void {
            $table->dropIndex('idx_seasons_year');
            $table->dropForeign('fk_seasons_archived_by_user_id');
            $table->dropForeign('fk_seasons_tajweed_level_id');
            $table->dropForeign('fk_seasons_participation_type_id');
            $table->dropColumn([
                'min_age',
                'max_age',
                'participation_type_id',
                'tajweed_level_id',
                'frozen_at',
                'archived_at',
                'archived_by_user_id',
                'archive_reason',
            ]);
        });
    }
};
