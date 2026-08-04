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
        Schema::create('judge_assignments', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('judge_id');
            $table->uuid('stage_id');
            $table->string('role', 50)->default('panel_member'); // 'head_judge', 'panel_member'
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['judge_id', 'stage_id'], 'uk_judge_assignments_judge_stage');

            $table->foreign('judge_id', 'fk_judge_assign_judge_id')
                ->references('id')
                ->on('judges')
                ->onDelete('RESTRICT');

            $table->foreign('stage_id', 'fk_judge_assign_stage_id')
                ->references('id')
                ->on('stages')
                ->onDelete('RESTRICT');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_assignments');
    }
};
