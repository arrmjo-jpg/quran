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
        Schema::create('languages', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->string('code', 10)->unique('uk_languages_code');
            $table->string('name', 100);
            $table->string('native_name', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('rtl')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
