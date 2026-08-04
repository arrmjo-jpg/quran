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
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            PlatformBlueprint::defaults($table);
            $table->uuid('permission_id');
            $table->uuid('role_id');

            $table->primary(['permission_id', 'role_id']);

            $table->foreign('permission_id', 'fk_rhp_permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('CASCADE');

            $table->foreign('role_id', 'fk_rhp_role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
    }
};
