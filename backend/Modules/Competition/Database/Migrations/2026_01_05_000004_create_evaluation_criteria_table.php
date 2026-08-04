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
        Schema::create('evaluation_criteria', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('template_id');
            $table->string('name', 100);
            $table->string('code', 50); // 'tajweed', 'memorization', 'voice', 'performance'
            $table->decimal('max_score', 5, 2);
            $table->decimal('weight_percent', 5, 2);
            $table->unsignedInteger('display_order')->default(0);

            $table->unique(['template_id', 'code'], 'uk_eval_criteria_template_code');

            $table->foreign('template_id', 'fk_eval_criteria_template_id')
                ->references('id')
                ->on('evaluation_templates')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
    }
};
