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
        Schema::create('videos', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('media_asset_id')->unique('uk_videos_media_asset_id');
            $table->unsignedInteger('duration_seconds');
            $table->string('resolution', 20); // e.g. '1080p'
            $table->string('format', 20); // e.g. 'mp4'
            $table->string('status', 50)->default('processing')->index('idx_videos_status');
            PlatformBlueprint::softDeletes($table);
            $table->timestamps();

            $table->foreign('media_asset_id', 'fk_videos_media_asset_id')
                ->references('id')
                ->on('media_assets')
                ->onDelete('RESTRICT');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
