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
        Schema::create('media_assets', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('uploader_id')->nullable()->index('idx_media_uploader_id');
            $table->string('disk', 50)->default('r2_private');
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('hash_sha256', 64)->nullable()->index('idx_media_hash');
            $table->string('collection', 50)->default('default')->index('idx_media_collection');
            $table->json('custom_properties')->nullable();
            PlatformBlueprint::softDeletes($table);
            $table->timestamps();

            $table->foreign('uploader_id', 'fk_media_uploader_id')
                ->references('id')
                ->on('users')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
