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
        Schema::create('stages', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('season_id');
            $table->uuid('evaluation_template_id')->nullable();
            $table->unsignedInteger('stage_number');
            $table->string('type', 50); // 'preliminary', 'semi_final', 'final'
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->string('status', 50)->default('pending');
            $table->timestamps();

            $table->unique(['season_id', 'stage_number'], 'uk_stages_season_number');

            $table->foreign('season_id', 'fk_stages_season_id')
                ->references('id')
                ->on('seasons')
                ->onDelete('RESTRICT');

            $table->foreign('evaluation_template_id', 'fk_stages_eval_template_id')
                ->references('id')
                ->on('evaluation_templates')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
