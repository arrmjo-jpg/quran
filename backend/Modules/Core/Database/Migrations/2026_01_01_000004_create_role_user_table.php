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
        Schema::create('role_user', function (Blueprint $table): void {
            PlatformBlueprint::defaults($table);
            $table->uuid('role_id');
            $table->uuid('user_id');

            $table->primary(['role_id', 'user_id']);

            $table->foreign('role_id', 'fk_role_user_role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('CASCADE');

            $table->foreign('user_id', 'fk_role_user_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
