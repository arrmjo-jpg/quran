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
        Schema::create('static_page_translations', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('static_page_id');
            $table->string('locale', 10);
            $table->string('title', 255);
            $table->longText('content');

            $table->unique(['static_page_id', 'locale'], 'uk_static_page_trans_page_locale');

            $table->foreign('static_page_id', 'fk_static_page_trans_page_id')
                ->references('id')
                ->on('static_pages')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_page_translations');
    }
};
