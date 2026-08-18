<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\PlatformBlueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->string('slug', 100)->unique('uk_seasons_slug');
            $table->unsignedSmallInteger('year');
            // dateTime(), not timestamp(): MySQL TIMESTAMP caps at
            // 2038-01-19 (the Y2038 problem) — CreateSeasonRequest
            // validates "year" up to 2100.
            $table->dateTime('registration_start');
            $table->dateTime('registration_end');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->string('status', 50)->default('draft')->index('idx_seasons_status');
            $table->boolean('is_active')->default(false);
            PlatformBlueprint::softDeletes($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
