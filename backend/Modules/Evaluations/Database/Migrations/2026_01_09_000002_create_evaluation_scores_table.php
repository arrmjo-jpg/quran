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
        Schema::create('evaluation_scores', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('evaluation_id');
            $table->uuid('criterion_id');
            $table->decimal('score', 5, 2);
            $table->text('notes')->nullable();

            $table->unique(['evaluation_id', 'criterion_id'], 'uk_eval_scores_eval_criterion');

            $table->foreign('evaluation_id', 'fk_eval_scores_eval_id')
                ->references('id')
                ->on('evaluations')
                ->onDelete('CASCADE');

            $table->foreign('criterion_id', 'fk_eval_scores_criterion_id')
                ->references('id')
                ->on('evaluation_criteria')
                ->onDelete('RESTRICT');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_scores');
    }
};
