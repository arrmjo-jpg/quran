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
        Schema::create('judge_score_system_translations', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('judge_score_system_id');
            $table->string('locale', 10);
            $table->string('name', 255);

            $table->unique(['judge_score_system_id', 'locale'], 'uk_score_system_trans_system_locale');

            $table->foreign('judge_score_system_id', 'fk_score_system_trans_system_id')
                ->references('id')
                ->on('judge_score_systems')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_score_system_translations');
    }
};
