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
        Schema::create('countries', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->char('iso_code', 2)->unique('uk_countries_iso');
            $table->char('iso3_code', 3)->unique('uk_countries_iso3');
            $table->string('phone_code', 10);
            $table->string('flag_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
