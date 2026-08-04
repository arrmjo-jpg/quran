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
        Schema::create('faq_translations', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('faq_id');
            $table->string('locale', 10);
            $table->text('question');
            $table->text('answer');

            $table->unique(['faq_id', 'locale'], 'uk_faq_trans_faq_locale');

            $table->foreign('faq_id', 'fk_faq_trans_faq_id')
                ->references('id')
                ->on('faqs')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_translations');
    }
};
