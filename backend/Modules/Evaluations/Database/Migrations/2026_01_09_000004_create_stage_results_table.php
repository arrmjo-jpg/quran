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
        Schema::create('stage_results', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('stage_id')->unique('uk_stage_results_stage_id');
            $table->string('status', 50)->default('draft'); // 'draft', 'published'
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('stage_id', 'fk_stage_results_stage_id')
                ->references('id')
                ->on('stages')
                ->onDelete('RESTRICT');

            $table->foreign('published_by_user_id', 'fk_stage_results_publisher')
                ->references('id')
                ->on('users')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_results');
    }
};
