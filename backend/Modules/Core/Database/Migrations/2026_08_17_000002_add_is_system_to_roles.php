<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-015 PE-4: a protected role is protected by a column, never by a
 * hardcoded list of names.
 *
 * The system this design learned from computed `is_system` in a Resource
 * from a hardcoded array, so the panel displayed a protection badge on
 * eight roles while exactly one was actually protected. Here the badge
 * and the guard read the same column.
 *
 * The flag is set by the seeder and by nothing else — no endpoint may
 * promote a custom role to a system role, or PE-4 would be bypassable
 * through the very UI it protects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('is_system')->default(false)->after('guard_name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('is_system');
        });
    }
};
