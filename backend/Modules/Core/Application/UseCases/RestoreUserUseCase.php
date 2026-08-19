<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Events\UserRestored;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * Brings a soft-deleted account back.
 *
 * Unguarded by PE-5 and PE-6, and that is not an omission. Both guards exist
 * to stop the platform losing its administrators; restoring one can only add
 * to them. Refusing a restore is how an environment stays stranded.
 *
 * The account returns exactly as it was, roles included — the pivot rows were
 * never detached, so nothing is reconstructed and nothing can be reconstructed
 * wrongly. Whether it can then act is a separate question: a restored account
 * that was deactivated before deletion is still deactivated afterwards, and
 * needs users.activate as well. Two states, two decisions, deliberately not
 * collapsed into one.
 */
final class RestoreUserUseCase
{
    public function __construct(
        private UserRepositoryContract $users,
    ) {}

    public function execute(string $userId, ?string $byUserId = null): User
    {
        return DB::transaction(function () use ($userId, $byUserId): User {
            $id = new UserId($userId);
            $user = $this->users->findWithTrashed($id);

            if ($user === null) {
                throw new DomainException('No such account.');
            }

            // Restoring a live account is a no-op, not an error — but it must
            // not write an audit entry claiming something happened.
            if (! $user->isDeleted()) {
                return $user;
            }

            $this->users->restore($id);

            event(new UserRestored(
                userId: $userId,
                byUserId: $byUserId,
                occurredAt: now()->toIso8601String(),
            ));

            return $this->users->findOrFail($id);
        });
    }
}
