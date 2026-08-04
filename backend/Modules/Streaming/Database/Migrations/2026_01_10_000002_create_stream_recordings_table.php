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
        Schema::create('stream_recordings', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('stream_id');
            $table->uuid('media_asset_id')->unique('uk_stream_recordings_media_asset_id');
            $table->unsignedInteger('duration_seconds');
            $table->timestamps();

            $table->foreign('stream_id', 'fk_stream_rec_stream_id')
                ->references('id')
                ->on('streams')
                ->onDelete('CASCADE');

            $table->foreign('media_asset_id', 'fk_stream_rec_media_asset_id')
                ->references('id')
                ->on('media_assets')
                ->onDelete('RESTRICT');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_recordings');
    }
};
