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
        Schema::create('indexing_logs', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->string('index_name', 100);
            $table->string('action', 50); // 'index', 'update', 'delete', 'flush'
            $table->uuid('entity_id')->nullable();
            $table->string('status', 20)->default('success');
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexing_logs');
    }
};
