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
        Schema::create('sponsors', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->string('name', 255);
            $table->string('tier', 50)->default('partner'); // 'headline', 'gold', 'silver', 'partner'
            $table->uuid('logo_media_id')->nullable();
            $table->string('website_url', 500)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('logo_media_id', 'fk_sponsors_logo_media_id')
                ->references('id')
                ->on('media_assets')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsors');
    }
};
