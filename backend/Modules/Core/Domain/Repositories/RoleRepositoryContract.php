<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Repositories;

use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\ValueObjects\RoleId;

interface RoleRepositoryContract
{
    public function find(RoleId $id): ?Role;

    public function findOrFail(RoleId $id): Role;

    public function findByName(string $name): ?Role;

    /** @return array<int, Role> */
    public function all(): array;

    /**
     * Persist the role and its permission set together. The set is
     * replaced wholesale rather than diffed here — the aggregate already
     * derived the delta and recorded it as an event; the repository's job
     * is only to make the stored state match the aggregate.
     */
    public function save(Role $role): void;

    /**
     * Hard delete. Roles are not soft-deleted: a soft-deleted role whose
     * pivot rows still exist is an authorization hazard, and the deletion
     * event already carries the name and permission set an auditor needs.
     */
    public function delete(RoleId $id): void;

    public function existsWithName(string $name): bool;
}
