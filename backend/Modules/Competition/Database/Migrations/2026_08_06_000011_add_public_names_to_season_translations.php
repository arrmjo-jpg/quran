<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PART 2 of the original i18n architecture decision requires Title,
 * Description, Public Name, and Public Short Name as mandatory
 * per-locale fields — season_translations only had title/description.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_translations', function (Blueprint $table): void {
            $table->string('public_name', 255)->nullable()->after('title');
            $table->string('public_short_name', 100)->nullable()->after('public_name');
        });
    }

    public function down(): void
    {
        Schema::table('season_translations', function (Blueprint $table): void {
            $table->dropColumn(['public_name', 'public_short_name']);
        });
    }
};
