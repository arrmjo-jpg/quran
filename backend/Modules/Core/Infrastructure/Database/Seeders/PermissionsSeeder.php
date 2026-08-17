<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

/**
 * Copies the permission catalogue into the `permissions` table.
 *
 * One direction only: PermissionCatalog is the source of truth and this
 * seeder never reads back from the table to amend it (ADR-015 §4.3).
 *
 * Idempotent: re-running inserts what is missing and leaves existing
 * rows alone, so it is safe on every deploy. Deliberately does NOT
 * delete rows that are absent from the catalogue — a permission removed
 * from the file may still be referenced by `role_has_permissions`, and
 * silently cascading that away during a routine seed would revoke
 * capability from live roles without a trace. Removing a permission is a
 * deliberate migration, not a side effect of seeding; the consistency
 * test reports orphans so they cannot go unnoticed.
 */
final class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $existing = DB::table('permissions')
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        $missing = array_diff(PermissionCatalog::all(), $existing);

        if ($missing === []) {
            return;
        }

        $now = now();

        DB::table('permissions')->insert(array_map(
            static fn (string $name): array => [
                'id' => (string) Str::uuid(),
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            array_values($missing)
        ));
    }
}
