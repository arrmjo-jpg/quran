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
        Schema::create('country_translations', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('country_id');
            $table->string('locale', 10);
            $table->string('name', 255);

            $table->unique(['country_id', 'locale'], 'uk_country_translations_country_locale');

            $table->foreign('country_id', 'fk_country_trans_country_id')
                ->references('id')
                ->on('countries')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_translations');
    }
};
