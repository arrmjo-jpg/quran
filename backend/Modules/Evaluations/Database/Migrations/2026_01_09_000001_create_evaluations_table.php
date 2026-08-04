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
        Schema::create('evaluations', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('application_id');
            $table->uuid('judge_id');
            $table->decimal('total_score', 5, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->string('status', 50)->default('draft')->index('idx_evaluations_status');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'judge_id'], 'uk_evaluations_app_judge');

            $table->foreign('application_id', 'fk_evaluations_app_id')
                ->references('id')
                ->on('applications')
                ->onDelete('RESTRICT');

            $table->foreign('judge_id', 'fk_evaluations_judge_id')
                ->references('id')
                ->on('judges')
                ->onDelete('RESTRICT');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
