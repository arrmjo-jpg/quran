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
        Schema::create('participation_type_translations', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('participation_type_id');
            $table->string('locale', 10);
            $table->string('name', 255);

            $table->unique(['participation_type_id', 'locale'], 'uk_participation_type_trans_type_locale');

            $table->foreign('participation_type_id', 'fk_participation_type_trans_type_id')
                ->references('id')
                ->on('participation_types')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_type_translations');
    }
};
