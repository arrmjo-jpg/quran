<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Entities;

use InvalidArgumentException;
use Modules\Core\Domain\Concerns\HasDomainEvents;
use Modules\Core\Domain\Events\RoleCreated;
use Modules\Core\Domain\Events\RolePermissionsChanged;
use Modules\Core\Domain\Events\RoleRenamed;
use Modules\Core\Domain\Exceptions\SystemRoleImmutableException;
use Modules\Core\Domain\ValueObjects\PermissionName;
use Modules\Core\Domain\ValueObjects\RoleId;

/**
 * Role Aggregate Root — ADR-015 §3, §4.
 *
 * A named, admin-editable bundle of permissions. Roles are data, not
 * code: the initial set ships as a seeder, and no role name is ever
 * hardcoded in a check anywhere in this codebase.
 *
 * The aggregate owns the *set* of permissions it grants. It does not own
 * the permissions themselves — those are catalogue reference data — and
 * it holds them as PermissionName value objects rather than ids, so a
 * role can be reasoned about without a join.
 *
 * SYSTEM ROLES: `isSystem` is persisted as a column and read from it. A
 * system role cannot be renamed, cannot be deleted, and cannot have
 * permissions revoked. Nothing may flip the flag — there is deliberately
 * no method to set it after construction, because a promote/demote path
 * would let PE-4 be bypassed through the very UI it protects.
 *
 * NOT THIS AGGREGATE'S JOB: verifying that a permission exists in the
 * catalogue (the catalogue lives in Infrastructure, which the domain may
 * not import — the use case checks it), and the PE-1/PE-2 subset rules,
 * which need the acting user's effective permissions and therefore
 * belong to the epics that build permission resolution.
 */
final class Role
{
    use HasDomainEvents;

    /** @var array<string, PermissionName> keyed by name, so grants are idempotent */
    private array $permissions = [];

    /**
     * @param  array<int, PermissionName>  $permissions
     */
    public function __construct(
        public readonly RoleId $id,
        private string $name,
        private readonly bool $isSystem = false,
        array $permissions = [],
        private readonly string $guardName = 'web',
    ) {
        self::assertValidName($name);

        foreach ($permissions as $permission) {
            $this->permissions[$permission->value] = $permission;
        }
    }

    /**
     * @param  array<int, PermissionName>  $permissions
     */
    public static function create(
        RoleId $id,
        string $name,
        bool $isSystem = false,
        array $permissions = [],
        ?string $byUserId = null,
    ): self {
        $role = new self($id, $name, $isSystem, $permissions);

        $role->recordEvent(new RoleCreated(
            roleId: $id->value,
            name: $role->name,
            isSystem: $isSystem,
            byUserId: $byUserId,
            occurredAt: now()->toIso8601String(),
        ));

        return $role;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGuardName(): string
    {
        return $this->guardName;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    /** @return array<int, PermissionName> */
    public function getPermissions(): array
    {
        return array_values($this->permissions);
    }

    /** @return array<int, string> */
    public function getPermissionNames(): array
    {
        return array_keys($this->permissions);
    }

    public function hasPermission(PermissionName $permission): bool
    {
        return isset($this->permissions[$permission->value]);
    }

    public function rename(string $newName, ?string $byUserId = null): void
    {
        $this->assertMutable('renamed');
        self::assertValidName($newName);

        $newName = trim($newName);

        if ($newName === $this->name) {
            return;
        }

        $oldName = $this->name;
        $this->name = $newName;

        $this->recordEvent(new RoleRenamed(
            roleId: $this->id->value,
            oldName: $oldName,
            newName: $newName,
            byUserId: $byUserId,
            occurredAt: now()->toIso8601String(),
        ));
    }

    /**
     * Replace the permission set wholesale, recording only what actually
     * changed. Callers pass the intended final set; the delta is derived
     * here so a caller cannot mis-report it.
     *
     * A SYSTEM ROLE REFUSES THIS ENTIRELY — additions included. Its
     * permissions are defined in the seeder, in code, and change only
     * when that definition changes and the seeder re-runs. There is
     * deliberately no path by which an API, a UI, or a use case can alter
     * super_admin, which is what makes "system role" mean immutable
     * rather than merely inconvenient to edit.
     *
     * The seeder does not call this. It constructs the role from its
     * definition and hands it to the repository, so a system role's set
     * is written by reconstitution, never by mutation.
     *
     * @param  array<int, PermissionName>  $permissions
     */
    public function syncPermissions(array $permissions, ?string $byUserId = null): void
    {
        $desired = [];
        foreach ($permissions as $permission) {
            $desired[$permission->value] = $permission;
        }

        $added = array_values(array_diff(array_keys($desired), array_keys($this->permissions)));
        $removed = array_values(array_diff(array_keys($this->permissions), array_keys($desired)));

        if ($added === [] && $removed === []) {
            return;
        }

        if ($this->isSystem) {
            throw new SystemRoleImmutableException($this->name, 'granted or revoked permissions');
        }

        $this->permissions = $desired;

        $this->recordEvent(new RolePermissionsChanged(
            roleId: $this->id->value,
            roleName: $this->name,
            added: $added,
            removed: $removed,
            byUserId: $byUserId,
            occurredAt: now()->toIso8601String(),
        ));
    }

    /**
     * Guard for deletion. The repository performs the delete and the use
     * case records the event, but whether a role *may* be deleted is a
     * domain question and is answered here.
     */
    public function assertDeletable(): void
    {
        $this->assertMutable('deleted');
    }

    private function assertMutable(string $operation): void
    {
        if ($this->isSystem) {
            throw new SystemRoleImmutableException($this->name, $operation);
        }
    }

    private static function assertValidName(string $name): void
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new InvalidArgumentException('A role name cannot be empty.');
        }

        // snake_case, matching the seeded names (super_admin,
        // competition_manager). Display labels are an i18n concern, so the
        // stored name stays a stable machine identifier and a rename never
        // happens just to change what an admin reads.
        if (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $trimmed) !== 1) {
            throw new InvalidArgumentException(
                "Invalid role name: '{$name}'. Expected snake_case, e.g. competition_manager."
            );
        }
    }
}
