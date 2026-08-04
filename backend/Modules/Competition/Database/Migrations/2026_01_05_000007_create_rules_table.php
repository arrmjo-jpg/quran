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
        Schema::create('rules', function (Blueprint $table): void {
            PlatformBlueprint::uuidPrimary($table);
            $table->uuid('season_id');
            $table->string('rule_key', 100);
            $table->json('rule_value');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['season_id', 'rule_key'], 'uk_rules_season_key');

            $table->foreign('season_id', 'fk_rules_season_id')
                ->references('id')
                ->on('seasons')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rules');
    }
};
