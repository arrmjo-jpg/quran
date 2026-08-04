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
        Schema::create('video_variants', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('video_id');
            $table->uuid('media_asset_id')->unique('uk_video_variants_media_asset_id');
            $table->string('quality', 20); // '1080p', '720p', '480p', '360p'
            $table->unsignedInteger('bitrate_kbps');
            $table->timestamps();

            $table->unique(['video_id', 'quality'], 'uk_video_variants_video_quality');

            $table->foreign('video_id', 'fk_video_variants_video_id')
                ->references('id')
                ->on('videos')
                ->onDelete('CASCADE');

            $table->foreign('media_asset_id', 'fk_video_variants_media_asset_id')
                ->references('id')
                ->on('media_assets')
                ->onDelete('RESTRICT');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_variants');
    }
};
