<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circles — ADR-016 D6, D7, D9.
 *
 * A circle belongs to a centre and stores no location of its own: no country,
 * no city, no address, no coordinates. Q3 settled that location is read
 * through the foreign key so a centre that moves is corrected in one row, and
 * the history that read-through would otherwise rewrite is preserved by Q4's
 * freeze of names onto applications rather than by copying an address here.
 *
 * That decision is why this table looks thin. A circle that meets somewhere
 * other than its centre has no way to say so today — Q3 records that as
 * undecided, and as a schema change rather than a workaround if it turns out
 * to be a real arrangement.
 *
 * NO is_active COLUMN, for the reason the centres table gives: soft deletion
 * already expresses "no longer running", and two mechanisms for one idea can
 * disagree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('name', 255);

            $table->uuid('center_id');

            // D9 — a supervisor is a User, not a third kind of identity.
            // Nullable because a circle may exist before anyone is appointed
            // to it, and because Q2 withholds the supervisor's permissions
            // until Epic 14: the column can be filled long before the role it
            // implies is allowed to see anything.
            $table->uuid('supervisor_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // RESTRICT mirrors centers.country_id. It will not fire in
            // practice — centres are soft deleted, which is an UPDATE — so the
            // refusal that actually protects a populated centre lives in
            // DeleteCenterUseCase. Stating it here is what stops a future hard
            // delete, or a direct SQL cleanup, from orphaning circles quietly.
            $table->foreign('center_id', 'fk_circles_center_id')
                ->references('id')->on('centers')
                ->restrictOnDelete();

            // SET NULL, matching every other user reference in the schema
            // (appeals.resolved_by_user_id, media_assets.uploader_id). A
            // circle outlives the person supervising it; losing the appointment
            // is correct, losing the circle is not.
            $table->foreign('supervisor_user_id', 'fk_circles_supervisor_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index('center_id', 'idx_circles_center_id');
            $table->index('supervisor_user_id', 'idx_circles_supervisor_user_id');

            // Unique within a centre, not within a city or a country. "Circle
            // 1" or "the women's circle" is an ordinary name for one circle in
            // every centre in the country; scoping wider would force operators
            // to invent suffixes that carry no meaning. Two circles with the
            // same name inside one centre is the genuine ambiguity, and the
            // only one refused.
            $table->unique(['center_id', 'name'], 'uk_circles_center_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circles');
    }
};
