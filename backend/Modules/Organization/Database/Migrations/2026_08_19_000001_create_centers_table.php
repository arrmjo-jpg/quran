<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centres — ADR-016 D7, Q3.
 *
 * A centre owns its location in full. Circles belong to a centre and store
 * none of this, reading through the foreign key instead, so a centre that
 * moves is corrected in exactly one row.
 *
 * The history that read-through would otherwise destroy is preserved
 * elsewhere: an application freezes the centre's *name* at submission (Q4), so
 * a year-old record still shows what the centre was called then. Address, city
 * and coordinates are deliberately not frozen anywhere — they are not part of
 * what an application means.
 *
 * NO is_active COLUMN, on purpose. Soft deletion already expresses "no longer
 * operating", and carrying both would be two mechanisms for one idea that can
 * disagree — the sort of duplication that leaves a screen showing a centre as
 * active while it is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centers', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('name', 255);

            $table->uuid('country_id');
            $table->string('city', 255);
            $table->string('address', 500);

            // Optional because the board deferred mapping. Nullable rather
            // than defaulted to zero: 0,0 is a real place in the Gulf of
            // Guinea, and a default that looks like data is worse than a null
            // that admits it knows nothing.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // RESTRICT: a centre cannot be removed while its country exists in
            // a form that other rows depend on. Countries are reference data
            // and are deactivated rather than deleted, so this should never
            // fire — which is the point of stating it rather than leaving the
            // column unconstrained.
            $table->foreign('country_id', 'fk_centers_country_id')
                ->references('id')->on('countries')
                ->restrictOnDelete();

            $table->index('country_id', 'idx_centers_country_id');

            // Unique within a city, not within a country. A country holds
            // dozens of places reasonably called "the Central Centre" — one
            // per town — and scoping uniqueness to the country would force
            // operators to invent suffixes that carry no meaning. Scoping it
            // to the city forbids only the genuine ambiguity: two centres with
            // the same name in the same place.
            //
            // The city is a free-text column, so this leans on operators
            // spelling it consistently. That is a weaker guarantee than a
            // city_id would give, and is the reason to revisit it if the
            // location model ever gains real administrative divisions.
            $table->unique(['country_id', 'city', 'name'], 'uk_centers_country_city_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centers');
    }
};
