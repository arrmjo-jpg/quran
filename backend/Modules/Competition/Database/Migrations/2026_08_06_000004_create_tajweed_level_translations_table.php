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
        Schema::create('tajweed_level_translations', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('tajweed_level_id');
            $table->string('locale', 10);
            $table->string('name', 255);

            $table->unique(['tajweed_level_id', 'locale'], 'uk_tajweed_level_trans_level_locale');

            $table->foreign('tajweed_level_id', 'fk_tajweed_level_trans_level_id')
                ->references('id')
                ->on('tajweed_levels')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tajweed_level_translations');
    }
};
