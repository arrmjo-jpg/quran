<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('applications', 'video_media_id')) {
                $table->uuid('video_media_id')->nullable()->after('video_id');

                $table->foreign('video_media_id', 'fk_applications_video_media_id')
                    ->references('id')
                    ->on('media_assets')
                    ->onDelete('SET NULL');
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            if (Schema::hasColumn('applications', 'video_media_id')) {
                $table->dropForeign('fk_applications_video_media_id');
                $table->dropColumn('video_media_id');
            }
        });
    }
};
