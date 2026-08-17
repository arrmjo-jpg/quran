<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\RoleId;

/**
 * Seeds the six actors ADR-001 named and ADR-015 §7.3 accepted.
 *
 * ROLES ARE DATA, NOT CODE. This file exists so a fresh install has
 * something to work with; every role below can be renamed or deleted
 * from the panel afterwards, except super_admin.
 *
 * WHY ONLY super_admin IS is_system: PE-4 protects exactly one role, the
 * one whose loss would lock the organisation out of its own platform.
 * Marking all six would make five roles unmanageable for no benefit and
 * would repeat, in a different shape, the mistake this design was written
 * against — a panel showing a protection badge on roles nothing actually
 * protects.
 *
 * PERMISSIONS ARE NOT ATTACHED HERE. The capability matrix lands with the
 * Role-Permission epic; this seeder creates the roles themselves. A role
 * with no grants is inert, which is the correct state while authorization
 * is still the EnsureUserIsAdmin surface check.
 *
 * Idempotent: a role that already exists is left exactly as it is, so
 * re-seeding never overwrites an operator's edits.
 */
final class RolesSeeder extends Seeder
{
    /** @var array<string, bool> role name => is_system */
    private const SYSTEM_ROLES = [
        'super_admin' => true,
        'competition_manager' => false,
        'judge' => false,
        'evaluator' => false,
        'data_entry' => false,
        'moderator' => false,
    ];

    public function __construct(
        private readonly RoleRepositoryContract $roles,
    ) {}

    public function run(): void
    {
        foreach (self::SYSTEM_ROLES as $name => $isSystem) {
            if ($this->roles->existsWithName($name)) {
                continue;
            }

            $this->roles->save(new Role(
                id: RoleId::generate(),
                name: $name,
                isSystem: $isSystem,
            ));
        }
    }
}
