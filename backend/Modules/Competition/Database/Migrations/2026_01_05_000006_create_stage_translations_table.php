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
        Schema::create('stage_translations', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('stage_id');
            $table->string('locale', 10);
            $table->string('name', 255);
            $table->text('description')->nullable();

            $table->unique(['stage_id', 'locale'], 'uk_stage_trans_stage_locale');

            $table->foreign('stage_id', 'fk_stage_trans_stage_id')
                ->references('id')
                ->on('stages')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_translations');
    }
};
