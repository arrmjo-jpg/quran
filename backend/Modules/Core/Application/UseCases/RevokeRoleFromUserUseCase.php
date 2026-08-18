<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * Removes one role from a user. Delegates to SyncUserRolesUseCase for
 * the same reason AssignRoleToUserUseCase does — one write path, one set
 * of guards, one event.
 */
final readonly class RevokeRoleFromUserUseCase
{
    public function __construct(
        private UserRepositoryContract $users,
        private SyncUserRolesUseCase $sync,
    ) {}

    public function execute(string $userId, string $roleId, ?string $byUserId = null): User
    {
        $current = $this->users->findOrFail(new UserId($userId))->getRoleIdValues();

        $remaining = array_values(array_filter(
            $current,
            static fn (string $id): bool => $id !== $roleId
        ));

        return $this->sync->execute($userId, $remaining, $byUserId);
    }
}
