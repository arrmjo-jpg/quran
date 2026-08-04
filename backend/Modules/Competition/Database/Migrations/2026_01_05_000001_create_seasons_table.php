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
        Schema::create('seasons', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->string('slug', 100)->unique('uk_seasons_slug');
            $table->unsignedSmallInteger('year');
            $table->timestamp('registration_start');
            $table->timestamp('registration_end');
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->string('status', 50)->default('draft')->index('idx_seasons_status');
            $table->boolean('is_active')->default(false);
            PlatformBlueprint::softDeletes($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
