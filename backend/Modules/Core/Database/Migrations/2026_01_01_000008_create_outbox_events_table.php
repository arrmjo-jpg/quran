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
        Schema::create('outbox_events', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->string('aggregate_type', 100);
            $table->uuid('aggregate_id');
            $table->string('event_type', 100);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'created_at'], 'idx_outbox_status_created');
            $table->index(['aggregate_type', 'aggregate_id'], 'idx_outbox_aggregate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
