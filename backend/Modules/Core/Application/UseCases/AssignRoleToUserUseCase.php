<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * Adds one role to a user.
 *
 * A convenience over SyncUserRolesUseCase, not a second mechanism: it
 * computes the intended final set and delegates, so PE-3, PE-5, the
 * unknown-role check and the UserRolesChanged event all apply exactly
 * once and in one place. A separate write path here would be a second
 * door into the pivot with its own set of things to remember.
 */
final readonly class AssignRoleToUserUseCase
{
    public function __construct(
        private UserRepositoryContract $users,
        private SyncUserRolesUseCase $sync,
    ) {}

    public function execute(string $userId, string $roleId, ?string $byUserId = null): User
    {
        $current = $this->users->findOrFail(new UserId($userId))->getRoleIdValues();

        return $this->sync->execute($userId, [...$current, $roleId], $byUserId);
    }
}
