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

    /**
     * Soft delete. Users are never destroyed (ADR-016 D5), so this sets
     * deleted_at and nothing more — the row, its roles and its history stay.
     */
    public function delete(UserId $id): void;

    /**
     * Undo a soft delete.
     *
     * Restoring returns the account exactly as it was, roles included: the
     * pivot rows were never touched, so nothing has to be reconstructed and
     * nothing can be reconstructed wrongly.
     */
    public function restore(UserId $id): void;

    /**
     * Find including soft-deleted rows.
     *
     * Separate from find() rather than a flag on it, so that every caller
     * that wants a deleted account has said so. The default must stay "not
     * deleted": an authorization path that silently loaded a deleted account
     * would be answering questions about someone who is gone.
     */
    public function findWithTrashed(UserId $id): ?User;

    /**
     * How many accounts currently hold this role.
     *
     * Exists for PE-5: the last account holding super_admin may not lose
     * it. Counting is a persistence question, so it lives here rather
     * than being answered by loading every user.
     */
    public function countUsersWithRole(RoleId $roleId): int;
}
