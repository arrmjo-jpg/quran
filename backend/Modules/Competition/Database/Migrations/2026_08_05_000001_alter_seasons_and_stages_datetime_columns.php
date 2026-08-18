<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL's TIMESTAMP type caps at 2038-01-19 03:14:07 UTC (the Y2038
 * problem) — but CreateSeasonRequest validates "year" up to 2100, and
 * competition seasons/stages are exactly the kind of far-future-dated
 * rows this app needs to create. Switching to DATETIME (no such limit)
 * on every affected column in both tables.
 *
 * Raw SQL rather than Blueprint::change() because doctrine/dbal isn't
 * installed. Gated to MySQL only: SQLite's type affinity doesn't have
 * this ceiling at all and doesn't support MODIFY COLUMN syntax, so the
 * test suite (sqlite :memory:) has nothing to fix here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE seasons MODIFY registration_start DATETIME NOT NULL');
        DB::statement('ALTER TABLE seasons MODIFY registration_end DATETIME NOT NULL');
        DB::statement('ALTER TABLE seasons MODIFY start_date DATETIME NOT NULL');
        DB::statement('ALTER TABLE seasons MODIFY end_date DATETIME NOT NULL');

        DB::statement('ALTER TABLE stages MODIFY start_date DATETIME NOT NULL');
        DB::statement('ALTER TABLE stages MODIFY end_date DATETIME NOT NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE seasons MODIFY registration_start TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE seasons MODIFY registration_end TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE seasons MODIFY start_date TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE seasons MODIFY end_date TIMESTAMP NOT NULL');

        DB::statement('ALTER TABLE stages MODIFY start_date TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE stages MODIFY end_date TIMESTAMP NOT NULL');
    }
};
