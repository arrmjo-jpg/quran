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
        Schema::create('media_conversions', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('media_asset_id');
            $table->string('conversion_name', 50);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->foreign('media_asset_id', 'fk_media_conv_asset_id')
                ->references('id')
                ->on('media_assets')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_conversions');
    }
};
