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
        Schema::create('streams', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('season_id');
            $table->uuid('stage_id')->nullable();
            $table->string('title', 255);
            $table->string('stream_key', 100)->unique('uk_streams_stream_key');
            $table->string('rtmp_url', 500);
            $table->string('hls_url', 500)->nullable();
            $table->string('status', 50)->default('idle')->index('idx_streams_status');
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->timestamps();

            $table->foreign('season_id', 'fk_streams_season_id')
                ->references('id')
                ->on('seasons')
                ->onDelete('RESTRICT');

            $table->foreign('stage_id', 'fk_streams_stage_id')
                ->references('id')
                ->on('stages')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streams');
    }
};
