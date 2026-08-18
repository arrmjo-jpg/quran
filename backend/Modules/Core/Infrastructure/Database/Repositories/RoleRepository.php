<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Repositories;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\PermissionName;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Infrastructure\Database\Models\RoleModel;

final class RoleRepository implements RoleRepositoryContract
{
    public function find(RoleId $id): ?Role
    {
        $model = RoleModel::query()->find($id->value);

        return $model ? $this->toDomain($model) : null;
    }

    public function findOrFail(RoleId $id): Role
    {
        return $this->find($id) ?? throw (new ModelNotFoundException)->setModel(RoleModel::class, [$id->value]);
    }

    public function findByName(string $name): ?Role
    {
        $model = RoleModel::query()->where('name', $name)->where('guard_name', 'web')->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** @return array<int, Role> */
    public function all(): array
    {
        return RoleModel::query()
            ->orderBy('name')
            ->get()
            ->map(fn (RoleModel $model): Role => $this->toDomain($model))
            ->all();
    }

    public function save(Role $role): void
    {
        RoleModel::query()->updateOrCreate(
            ['id' => $role->id->value],
            [
                'name' => $role->getName(),
                'guard_name' => $role->getGuardName(),
                'is_system' => $role->isSystem(),
            ]
        );

        $this->syncPermissions($role);
    }

    public function delete(RoleId $id): void
    {
        // Both pivots are cleared explicitly rather than left to the FK
        // cascade. The cascade exists and would do it on MySQL, but the
        // test connection runs with foreign keys disabled, so relying on
        // it would mean this method behaved differently under test than
        // in production — and the difference would be orphaned grants,
        // which is the one outcome worth being certain about.
        DB::table('role_has_permissions')->where('role_id', $id->value)->delete();
        DB::table('role_user')->where('role_id', $id->value)->delete();

        RoleModel::query()->where('id', $id->value)->delete();
    }

    public function existsWithName(string $name): bool
    {
        return RoleModel::query()->where('name', $name)->where('guard_name', 'web')->exists();
    }

    /**
     * Replaces the role's grants with exactly what the aggregate holds.
     *
     * Resolves names to permission ids through the `permissions` table
     * rather than trusting the caller: a name with no row would otherwise
     * be silently dropped here, leaving a role that claims a capability
     * the pivot never recorded. The use case rejects unknown names before
     * reaching this point, so a miss at this layer means the catalogue and
     * the table have drifted, and failing loudly is correct.
     */
    private function syncPermissions(Role $role): void
    {
        $names = $role->getPermissionNames();

        DB::table('role_has_permissions')->where('role_id', $role->id->value)->delete();

        if ($names === []) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('name', $names)
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        $unresolved = array_diff($names, $ids->keys()->all());

        if ($unresolved !== []) {
            throw new \RuntimeException(
                'Cannot grant permissions with no row in the permissions table: '
                .implode(', ', $unresolved)
                .'. Run the PermissionsSeeder — the catalogue and the table have drifted.'
            );
        }

        DB::table('role_has_permissions')->insert(array_map(
            static fn (string $permissionId): array => [
                'permission_id' => $permissionId,
                'role_id' => $role->id->value,
            ],
            $ids->values()->all()
        ));
    }

    private function toDomain(RoleModel $model): Role
    {
        $names = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $model->id)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();

        return new Role(
            id: new RoleId($model->id),
            name: $model->name,
            isSystem: (bool) $model->is_system,
            permissions: PermissionName::fromMany($names),
            guardName: $model->guard_name,
        );
    }
}
