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
        Schema::create('video_thumbnails', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('video_id');
            $table->uuid('media_asset_id')->unique('uk_video_thumbnails_media_asset_id');
            $table->unsignedInteger('time_offset_seconds');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('video_id', 'fk_video_thumbnails_video_id')
                ->references('id')
                ->on('videos')
                ->onDelete('CASCADE');

            $table->foreign('media_asset_id', 'fk_video_thumbnails_media_asset_id')
                ->references('id')
                ->on('media_assets')
                ->onDelete('RESTRICT');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_thumbnails');
    }
};
