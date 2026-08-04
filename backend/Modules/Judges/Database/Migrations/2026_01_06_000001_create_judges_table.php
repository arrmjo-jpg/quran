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
        Schema::create('judges', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('user_id')->unique('uk_judges_user_id');
            $table->string('full_name', 255);
            $table->string('title', 100)->nullable();
            $table->string('specialization', 100);
            $table->text('bio')->nullable();
            $table->uuid('photo_media_id')->nullable();
            $table->boolean('is_active')->default(true);
            PlatformBlueprint::softDeletes($table);
            $table->timestamps();

            $table->foreign('user_id', 'fk_judges_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('RESTRICT');

            $table->foreign('photo_media_id', 'fk_judges_photo_media_id')
                ->references('id')
                ->on('media_assets')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judges');
    }
};
