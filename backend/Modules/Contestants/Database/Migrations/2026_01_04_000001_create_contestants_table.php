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
        Schema::create('contestants', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('user_id')->unique('uk_contestants_user_id');
            $table->uuid('country_id')->index('idx_contestants_country_id');
            $table->string('full_name', 255);
            $table->date('date_of_birth');
            $table->string('gender', 10); // 'male' or 'female'
            $table->string('national_id', 100)->nullable();
            $table->string('phone_number', 50);
            $table->uuid('photo_media_id')->nullable();
            PlatformBlueprint::softDeletes($table);
            $table->timestamps();

            $table->foreign('user_id', 'fk_contestants_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('RESTRICT');

            $table->foreign('country_id', 'fk_contestants_country_id')
                ->references('id')
                ->on('countries')
                ->onDelete('RESTRICT');

            $table->foreign('photo_media_id', 'fk_contestants_photo_media_id')
                ->references('id')
                ->on('media_assets')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contestants');
    }
};
