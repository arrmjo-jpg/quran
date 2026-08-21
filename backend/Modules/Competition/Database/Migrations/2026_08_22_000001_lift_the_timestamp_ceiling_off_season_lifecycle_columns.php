<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Y2038, reintroduced one day after it was fixed.
 *
 * `2026_08_05_000001_alter_seasons_and_stages_datetime_columns` lifted MySQL's
 * TIMESTAMP ceiling (2038-01-19 03:14:07 UTC) off `registration_start`,
 * `registration_end`, `start_date` and `end_date`, because CreateSeasonRequest
 * validates `year` up to 2100 and a competition season is exactly the kind of
 * far-future-dated row this platform creates.
 *
 * The next day, `2026_08_06_000012_alter_seasons_for_lifecycle_v2` added
 * `frozen_at` and `archived_at` as `$table->timestamp(...)` — putting the
 * ceiling straight back on two columns of the same table. Verified against the
 * live schema before writing this: the first four are DATETIME, these two are
 * TIMESTAMP.
 *
 * THIS IS A SCHEMA DEFECT, NOT A TEST FIX. Both columns are stamped with
 * `now()` in production, so nothing breaks before 2038 and no data is at risk
 * today — but a season archived in 2040 is a row MySQL refuses, and
 * RestoreSeasonTest already writes `archived_at => '2040-04-01'`. The bound is
 * real; only the deadline is distant.
 *
 * It stayed invisible for two weeks for one reason: the suite runs on SQLite,
 * whose type affinity has no such ceiling. The engine was hiding the defect,
 * which is the whole argument this epic is built on.
 *
 * Raw SQL rather than Blueprint::change(), and MySQL-gated — both for the
 * reasons the 2026_08_05 migration gives: doctrine/dbal is not installed, and
 * SQLite neither has the ceiling nor supports MODIFY COLUMN.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // NULL-able, unlike the four columns the earlier migration handled:
        // a season that has never been frozen or archived has neither stamp.
        DB::statement('ALTER TABLE seasons MODIFY frozen_at DATETIME NULL');
        DB::statement('ALTER TABLE seasons MODIFY archived_at DATETIME NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE seasons MODIFY frozen_at TIMESTAMP NULL');
        DB::statement('ALTER TABLE seasons MODIFY archived_at TIMESTAMP NULL');
    }
};
