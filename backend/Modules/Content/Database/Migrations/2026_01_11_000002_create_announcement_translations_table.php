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
        Schema::create('announcement_translations', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('announcement_id');
            $table->string('locale', 10);
            $table->string('title', 255);
            $table->longText('body');

            $table->unique(['announcement_id', 'locale'], 'uk_announcement_trans_ann_locale');

            $table->foreign('announcement_id', 'fk_announcement_trans_ann_id')
                ->references('id')
                ->on('announcements')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_translations');
    }
};
