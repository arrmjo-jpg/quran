<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Repositories;

use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * UserRepositoryContract
 *
 * Domain persistence interface for User aggregate root.
 */
interface UserRepositoryContract
{
    public function findOrFail(UserId $id): User;

    public function find(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    /**
     * Persists the user and the set of roles they hold, together. The
     * role set is replaced wholesale to match the aggregate.
     */
    public function save(User $user): void;

    public function delete(UserId $id): void;

    /**
     * How many accounts currently hold this role.
     *
     * Exists for PE-5: the last account holding super_admin may not lose
     * it. Counting is a persistence question, so it lives here rather
     * than being answered by loading every user.
     */
    public function countUsersWithRole(RoleId $roleId): int;
}
