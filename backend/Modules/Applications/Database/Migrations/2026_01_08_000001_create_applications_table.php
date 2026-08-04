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
        Schema::create('applications', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('contestant_id');
            $table->uuid('season_id');
            $table->uuid('stage_id');
            $table->uuid('video_id')->nullable();
            $table->string('application_number', 50)->unique('uk_applications_number');
            $table->string('status', 50)->default('draft')->index('idx_applications_status');
            $table->timestamp('submitted_at')->nullable();
            PlatformBlueprint::softDeletes($table);
            $table->timestamps();

            $table->unique(['contestant_id', 'season_id', 'stage_id'], 'uk_applications_contestant_season_stage');

            $table->foreign('contestant_id', 'fk_applications_contestant_id')
                ->references('id')
                ->on('contestants')
                ->onDelete('RESTRICT');

            $table->foreign('season_id', 'fk_applications_season_id')
                ->references('id')
                ->on('seasons')
                ->onDelete('RESTRICT');

            $table->foreign('stage_id', 'fk_applications_stage_id')
                ->references('id')
                ->on('stages')
                ->onDelete('RESTRICT');

            $table->foreign('video_id', 'fk_applications_video_id')
                ->references('id')
                ->on('videos')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
