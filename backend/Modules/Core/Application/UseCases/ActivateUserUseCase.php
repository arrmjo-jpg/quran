<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * Puts an account back into service.
 *
 * Deliberately unguarded, unlike its opposite. Deactivation can strand the
 * platform, so it asks two questions first; activation can only ever add an
 * administrator, and refusing it is what would leave someone stranded.
 *
 * No cache invalidation, for the same reason as deactivation: the effective
 * permission set is derived from roles and has not changed. What changed is
 * whether the account may act on it, and that is asked per request.
 */
final class ActivateUserUseCase
{
    public function __construct(
        private UserRepositoryContract $users,
    ) {}

    public function execute(string $userId, ?string $byUserId = null): User
    {
        return DB::transaction(function () use ($userId): User {
            $user = $this->users->findOrFail(new UserId($userId));

            $user->activate();
            $this->users->save($user);

            return $user;
        });
    }
}
