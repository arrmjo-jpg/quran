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
        Schema::create('results', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('application_id')->unique('uk_results_app_id');
            $table->decimal('final_score', 5, 2);
            $table->decimal('tajweed_score', 5, 2);
            $table->decimal('memorization_score', 5, 2);
            $table->decimal('voice_score', 5, 2);
            $table->unsignedInteger('rank')->nullable()->index('idx_results_rank');
            $table->string('status', 50)->default('pending'); // 'qualified', 'disqualified', 'waitlisted', 'eliminated'
            $table->boolean('manual_tie_break_flag')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('application_id', 'fk_results_app_id')
                ->references('id')
                ->on('applications')
                ->onDelete('RESTRICT');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
