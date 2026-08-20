<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The placement snapshot — ADR-016 D8, Q4.
 *
 * An application freezes where its contestant studied when they submitted it:
 * both ids and both names. The ids alone were explicitly judged insufficient,
 * and the reason is Q3: because a circle reads its location through its centre
 * rather than copying it, an id resolves to whatever that centre is called
 * *now*. A year-old application rendered from ids would display a name that
 * did not exist when it was submitted, and the report would quietly change
 * every time a centre was renamed.
 *
 * WHAT IS NOT FROZEN, and this is deliberate: address, city and coordinates.
 * They are no part of what an application means. Freezing them would make this
 * table a slowly-corrupting copy of the centres table, which is the duplication
 * Q3 exists to avoid.
 *
 * NULLABLE, AND THE RULE LIVES IN THE USE CASE. Q4 chose option (c) of three:
 * the database permits null so rows written before this epic — and any future
 * migration — are not trapped, while SubmitApplicationUseCase refuses to
 * create an application for a contestant with no active membership. A NOT NULL
 * column would have made every existing row a migration problem; a rule with
 * no enforcement would have been decoration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->uuid('center_id')->nullable()->after('stage_id');
            $table->uuid('circle_id')->nullable()->after('center_id');

            // Frozen copies, not caches. Nothing refreshes these, and nothing
            // should: the moment one is rewritten to match the live row, the
            // history D8 exists to keep is gone.
            $table->string('center_name', 255)->nullable()->after('circle_id');
            $table->string('circle_name', 255)->nullable()->after('center_name');

            // RESTRICT, matching contestant_id, season_id and stage_id on this
            // table. Centres and circles are soft deleted, so this never fires
            // in ordinary use — it exists so that a hard delete or a direct SQL
            // cleanup cannot strip an application of the placement it recorded.
            // SET NULL would have been the wrong instinct here: it protects the
            // schema by destroying exactly the evidence this column holds.
            $table->foreign('center_id', 'fk_applications_center_id')
                ->references('id')->on('centers')
                ->restrictOnDelete();

            $table->foreign('circle_id', 'fk_applications_circle_id')
                ->references('id')->on('circles')
                ->restrictOnDelete();

            // "Which applications came from this centre" is the question this
            // snapshot was added to answer, so it is worth an index rather than
            // a scan once a season's applications run to thousands.
            $table->index('center_id', 'idx_applications_center_id');
            $table->index('circle_id', 'idx_applications_circle_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropForeign('fk_applications_center_id');
            $table->dropForeign('fk_applications_circle_id');
            $table->dropIndex('idx_applications_center_id');
            $table->dropIndex('idx_applications_circle_id');
            $table->dropColumn(['center_id', 'circle_id', 'center_name', 'circle_name']);
        });
    }
};
