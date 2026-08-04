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
        Schema::create('appeals', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('application_id');
            $table->uuid('contestant_id');
            $table->text('reason');
            $table->string('status', 50)->default('pending')->index('idx_appeals_status');
            $table->text('admin_response')->nullable();
            $table->uuid('resolved_by_user_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('application_id', 'fk_appeals_app_id')
                ->references('id')
                ->on('applications')
                ->onDelete('RESTRICT');

            $table->foreign('contestant_id', 'fk_appeals_contestant_id')
                ->references('id')
                ->on('contestants')
                ->onDelete('RESTRICT');

            $table->foreign('resolved_by_user_id', 'fk_appeals_resolver')
                ->references('id')
                ->on('users')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appeals');
    }
};
