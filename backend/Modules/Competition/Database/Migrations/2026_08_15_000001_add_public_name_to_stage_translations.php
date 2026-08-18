<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stage_translations only carried name/description, so a stage had no
 * public-facing label of its own — the same gap season_translations had
 * before the public-names migration. Nullable, because every existing
 * stage row predates the column; StageTranslation::isComplete() is what
 * actually requires it to be filled in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_translations', function (Blueprint $table): void {
            $table->string('public_name', 255)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('stage_translations', function (Blueprint $table): void {
            $table->dropColumn('public_name');
        });
    }
};
