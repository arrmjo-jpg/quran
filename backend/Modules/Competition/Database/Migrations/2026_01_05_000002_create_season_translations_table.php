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
        Schema::create('season_translations', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('season_id');
            $table->string('locale', 10);
            $table->string('title', 255);
            $table->text('description')->nullable();

            $table->unique(['season_id', 'locale'], 'uk_season_trans_season_locale');

            $table->foreign('season_id', 'fk_season_trans_season_id')
                ->references('id')
                ->on('seasons')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_translations');
    }
};
