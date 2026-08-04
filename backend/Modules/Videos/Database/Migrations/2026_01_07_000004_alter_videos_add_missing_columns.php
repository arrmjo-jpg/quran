<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciles videos table with the evolved Video entity / VideoModel.
 *
 * Original migration columns that are NOT NULL but should be nullable
 * (they are populated after FFmpeg transcoding):
 *   - media_asset_id   (legacy, now optional)
 *   - duration_seconds (populated after processing)
 *   - resolution       (populated after processing)
 *   - format           (populated after processing)
 *
 * New columns added per VideoModel:
 *   - application_id, raw_media_asset_id,
 *     hls_master_playlist_path, thumbnail_path, variants
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table): void {
            // ── Make legacy NOT NULL columns nullable ─────────────────────
            $table->uuid('media_asset_id')->nullable()->change();
            $table->unsignedInteger('duration_seconds')->nullable()->change();
            $table->string('resolution', 20)->nullable()->change();
            $table->string('format', 20)->nullable()->change();

            // ── Add new columns required by VideoModel ────────────────────
            if (! Schema::hasColumn('videos', 'application_id')) {
                $table->uuid('application_id')
                    ->nullable()
                    ->index('idx_videos_application_id')
                    ->after('id');
            }

            if (! Schema::hasColumn('videos', 'raw_media_asset_id')) {
                $table->uuid('raw_media_asset_id')
                    ->nullable()
                    ->after('application_id');
            }

            if (! Schema::hasColumn('videos', 'hls_master_playlist_path')) {
                $table->string('hls_master_playlist_path', 500)
                    ->nullable()
                    ->after('status');
            }

            if (! Schema::hasColumn('videos', 'thumbnail_path')) {
                $table->string('thumbnail_path', 500)
                    ->nullable()
                    ->after('hls_master_playlist_path');
            }

            if (! Schema::hasColumn('videos', 'variants')) {
                $table->json('variants')
                    ->nullable()
                    ->after('thumbnail_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table): void {
            $table->dropColumnIfExists('application_id');
            $table->dropColumnIfExists('raw_media_asset_id');
            $table->dropColumnIfExists('hls_master_playlist_path');
            $table->dropColumnIfExists('thumbnail_path');
            $table->dropColumnIfExists('variants');
        });
    }
};
