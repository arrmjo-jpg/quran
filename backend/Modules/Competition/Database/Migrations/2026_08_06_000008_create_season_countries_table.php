<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Participation restriction: a contestant may only register for a season
 * if their country appears here. Pure pivot table — no surrogate id needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_countries', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->uuid('season_id');
            $table->uuid('country_id');

            $table->primary(['season_id', 'country_id']);

            $table->foreign('season_id', 'fk_season_countries_season_id')
                ->references('id')
                ->on('seasons')
                ->onDelete('RESTRICT');

            $table->foreign('country_id', 'fk_season_countries_country_id')
                ->references('id')
                ->on('countries')
                ->onDelete('RESTRICT');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_countries');
    }
};
