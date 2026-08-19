<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contestant memberships — ADR-016 Q4.
 *
 * One row per period a contestant belonged to a circle, not one column on the
 * contestant. Q4 rejected `contestants.circle_id` because it holds only the
 * present and loses every transfer, which is the history D8 exists to protect.
 * A contestant who moves twice leaves three rows here.
 *
 * NO deleted_at, deliberately. A membership is a historical record rather than
 * a lifecycle entity: it ends by acquiring `left_at`, and "this never
 * happened" is a different claim from "this is over". The practical payoff is
 * that "active" stays one condition instead of two that can disagree — which
 * matters most for the unique index below, whose whole job is to define
 * "active" once.
 *
 * `created_at` and `updated_at` are record metadata and are not the same thing
 * as `joined_at` and `left_at`. A membership backdated to last September was
 * created today; conflating the two would make every backdated entry look like
 * a September record.
 *
 * ── G1: ONE ACTIVE MEMBERSHIP PER CONTESTANT ──────────────────────────────
 *
 * Enforced twice, as this module already does for circle names. The use case
 * produces the refusal an operator can act on; the index is the guarantee that
 * two simultaneous requests cannot both pass that check.
 *
 * Neither engine can express "unique among rows where left_at IS NULL" the
 * same way, so each gets its own mechanism for identical semantics:
 *
 *   MySQL 8  has no partial indexes. A generated column carries the
 *            contestant's id only while the membership is open and NULL once
 *            it closes, and a unique index on it constrains the open ones
 *            while ignoring the closed — using NULL-is-not-equal-to-NULL
 *            deliberately. This is the idiom seasons.active_flag established
 *            and circles.live_name reused.
 *
 *   SQLite   has partial indexes, which say it directly.
 *
 * Unlike the seasons migration, SQLite is not skipped: the suite runs there,
 * and a guarantee the tests cannot exercise is a guarantee nobody has checked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contestant_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->uuid('contestant_id');
            $table->uuid('circle_id');

            // The period itself. joined_at is required because a membership
            // with no beginning cannot be ordered against the transfers around
            // it; left_at is null exactly while the membership is open, which
            // is the condition the unique index below is built on.
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();

            // Required by the aggregate when a membership ends, nullable here
            // because it does not exist while the membership is open. A closed
            // membership with no reason is the row an administrator finds a
            // year later and cannot act on.
            $table->string('reason', 500)->nullable();

            $table->timestamps();

            // RESTRICT on both. A membership is the record that a contestant
            // attended a circle; hard-deleting either party would leave the
            // record asserting something about a row that no longer exists.
            // Neither will fire in ordinary use — contestants and circles are
            // both soft deleted, which is an UPDATE — so the refusals that
            // actually protect them live in the use cases. These stop a direct
            // SQL cleanup from quietly rewriting history.
            $table->foreign('contestant_id', 'fk_memberships_contestant_id')
                ->references('id')->on('contestants')
                ->restrictOnDelete();

            $table->foreign('circle_id', 'fk_memberships_circle_id')
                ->references('id')->on('circles')
                ->restrictOnDelete();

            $table->index('contestant_id', 'idx_memberships_contestant_id');
            $table->index('circle_id', 'idx_memberships_circle_id');

            // Answers "who is in this circle now" without scanning: the
            // supervisor screens of Epic 14 and DeleteCircleUseCase's refusal
            // both ask exactly this.
            $table->index(['circle_id', 'left_at'], 'idx_memberships_circle_active');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE contestant_memberships ADD COLUMN active_contestant_id CHAR(36) '
                .'AS (CASE WHEN left_at IS NULL THEN contestant_id ELSE NULL END) STORED'
            );

            DB::statement(
                'ALTER TABLE contestant_memberships '
                .'ADD UNIQUE KEY uk_memberships_one_active (active_contestant_id)'
            );

            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX uk_memberships_one_active '
            .'ON contestant_memberships (contestant_id) WHERE left_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('contestant_memberships');
    }
};
