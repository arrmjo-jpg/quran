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
        Schema::create('notification_logs', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('user_id')->index('idx_notif_logs_user_id');
            $table->string('channel', 20); // 'email', 'sms', 'push'
            $table->string('template_key', 100);
            $table->json('payload');
            $table->string('status', 20)->default('queued')->index('idx_notif_logs_status');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'fk_notif_logs_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
