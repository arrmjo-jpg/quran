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
        Schema::create('exports', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('user_id');
            $table->string('type', 50); // 'contestants_csv', 'evaluations_pdf'
            $table->uuid('media_asset_id')->nullable();
            $table->string('status', 20)->default('pending')->index('idx_exports_status');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'fk_exports_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('CASCADE');

            $table->foreign('media_asset_id', 'fk_exports_media_asset_id')
                ->references('id')
                ->on('media_assets')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
