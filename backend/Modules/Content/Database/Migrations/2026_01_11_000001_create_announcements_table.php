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
        Schema::create('announcements', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->string('slug', 100)->unique('uk_announcements_slug');
            $table->string('target_surface', 50)->default('all'); // 'all', 'contestants', 'public'
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            PlatformBlueprint::softDeletes($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
