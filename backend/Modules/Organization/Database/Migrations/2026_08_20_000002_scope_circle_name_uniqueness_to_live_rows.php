<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Frees a closed circle's name for reuse.
 *
 * `uk_circles_center_name` was declared as UNIQUE(center_id, name) with no
 * reference to deleted_at, while CircleModel uses SoftDeletes and every
 * application-level lookup is scoped to live rows. The two layers therefore
 * disagreed: CreateCircleUseCase asked "is this name taken?", saw only live
 * circles, answered no — and the insert was then refused by the database with
 * a duplicate-key error. Reopening a circle under the name of a closed one
 * returned a 500, rather than either succeeding or being refused politely.
 *
 * Reproduced on both engines before this was written: SQLite via the suite,
 * MySQL via raw inserts against the development database.
 *
 * WHY REUSE IS THE RIGHT BEHAVIOUR, rather than teaching the use case to look
 * at trashed rows and refuse: the module already decided that a closed circle
 * does not constrain the live world — CircleRepository::countInCenter() leans
 * on the SoftDeletes scope precisely so a closed circle cannot hold its centre
 * open, and CircleApiTest pins that. A name is the same kind of claim. It is
 * also the kinder failure: refusing a name because of a circle the operator
 * cannot see on the screen that refused them is the problem DeleteCenterUseCase
 * went out of its way to avoid when it put the count in its message.
 *
 * WHY NOT SIMPLY ADD deleted_at TO THE KEY: in MySQL a unique index does not
 * constrain rows where any indexed column is NULL, so UNIQUE(center_id, name,
 * deleted_at) would permit any number of LIVE circles sharing a name — every
 * one of them has deleted_at NULL. That is the opposite of the intent.
 *
 * The two engines get the same semantics through different mechanisms, because
 * each supports a different one:
 *
 *   MySQL 8  has no partial indexes, so a generated column carries the name
 *            only while the row is live and NULL once it is not. The unique
 *            index then constrains live rows and ignores closed ones, using
 *            the same NULL-is-not-equal-to-NULL property deliberately. This
 *            is the idiom seasons.active_flag already established here.
 *
 *   SQLite   has partial indexes, which say it directly.
 *
 * Unlike the seasons migration, SQLite is NOT skipped. There the DB constraint
 * was a second line of defence behind a rule the domain enforced anyway; here
 * the old index is itself the defect, so leaving it in place on SQLite would
 * leave the bug live in the environment the whole suite runs against.
 *
 * The application-level CIRCLE_NAME_TAKEN check stays. It produces the 409 an
 * operator can act on; this index is the backstop against two requests racing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE circles DROP INDEX uk_circles_center_name');

            DB::statement(
                'ALTER TABLE circles ADD COLUMN live_name VARCHAR(255) '
                .'AS (CASE WHEN deleted_at IS NULL THEN name ELSE NULL END) STORED'
            );

            DB::statement(
                'ALTER TABLE circles ADD UNIQUE KEY uk_circles_center_live_name (center_id, live_name)'
            );

            return;
        }

        // SQLite, and anything else with partial index support.
        DB::statement('DROP INDEX IF EXISTS uk_circles_center_name');

        DB::statement(
            'CREATE UNIQUE INDEX uk_circles_center_live_name ON circles (center_id, name) '
            .'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE circles DROP INDEX uk_circles_center_live_name');
            DB::statement('ALTER TABLE circles DROP COLUMN live_name');
            DB::statement('ALTER TABLE circles ADD UNIQUE KEY uk_circles_center_name (center_id, name)');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS uk_circles_center_live_name');
        DB::statement('CREATE UNIQUE INDEX uk_circles_center_name ON circles (center_id, name)');
    }
};
