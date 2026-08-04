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
        Schema::create('audit_logs', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('correlation_id')->nullable();
            $table->unsignedInteger('execution_duration_ms');
            $table->uuid('actor_id')->nullable();
            $table->string('actor_type', 50)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->string('route_name', 150)->nullable();
            $table->unsignedSmallInteger('response_status');
            $table->string('device_id', 100)->nullable();
            $table->unsignedInteger('request_size')->nullable();
            $table->unsignedInteger('response_size')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('correlation_id', 'idx_audit_logs_correlation_id');
            $table->index(['actor_id', 'created_at'], 'idx_audit_logs_actor_created');
            $table->index('created_at', 'idx_audit_logs_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
