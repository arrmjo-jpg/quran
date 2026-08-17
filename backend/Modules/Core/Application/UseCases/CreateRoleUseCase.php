<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Exceptions\UnknownPermissionException;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\PermissionName;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;
use RuntimeException;

/**
 * Creates a custom role — ADR-015 §7.2.
 *
 * `is_system` is deliberately not a parameter. System roles are created
 * by the seeder alone; if this use case could set the flag, PE-4 would be
 * bypassable by creating a protected role from the panel.
 */
final readonly class CreateRoleUseCase
{
    public function __construct(
        private RoleRepositoryContract $roles,
    ) {}

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function execute(string $name, array $permissionNames = [], ?string $byUserId = null): Role
    {
        return DB::transaction(function () use ($name, $permissionNames, $byUserId): Role {
            $trimmed = trim($name);

            if ($this->roles->existsWithName($trimmed)) {
                throw new RuntimeException("A role named '{$trimmed}' already exists.");
            }

            $permissions = self::assertKnown($permissionNames);

            $role = Role::create(
                id: RoleId::generate(),
                name: $trimmed,
                isSystem: false,
                permissions: $permissions,
                byUserId: $byUserId,
            );

            $this->roles->save($role);

            foreach ($role->releaseEvents() as $event) {
                event($event);
            }

            return $role;
        });
    }

    /**
     * The catalogue is the single source of truth (ADR-015 §4.3), so a
     * grant is refused unless the catalogue defines it. This is the
     * boundary where membership is checked — the PermissionName value
     * object validates shape only, because the domain may not import
     * Infrastructure.
     *
     * @param  array<int, string>  $names
     * @return array<int, PermissionName>
     */
    public static function assertKnown(array $names): array
    {
        $unknown = array_values(array_filter(
            $names,
            static fn (string $name): bool => ! PermissionCatalog::has($name)
        ));

        if ($unknown !== []) {
            throw new UnknownPermissionException($unknown);
        }

        return PermissionName::fromMany($names);
    }
}
