<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\RoleId;
use RuntimeException;

/**
 * Renames a custom role. A system role refuses the rename in the
 * aggregate (PE-4), not here.
 */
final readonly class RenameRoleUseCase
{
    public function __construct(
        private RoleRepositoryContract $roles,
    ) {}

    public function execute(string $roleId, string $newName, ?string $byUserId = null): Role
    {
        return DB::transaction(function () use ($roleId, $newName, $byUserId): Role {
            $role = $this->roles->findOrFail(new RoleId($roleId));
            $trimmed = trim($newName);

            if ($trimmed !== $role->getName() && $this->roles->existsWithName($trimmed)) {
                throw new RuntimeException("A role named '{$trimmed}' already exists.");
            }

            $role->rename($trimmed, $byUserId);

            $this->roles->save($role);

            foreach ($role->releaseEvents() as $event) {
                event($event);
            }

            return $role;
        });
    }
}
