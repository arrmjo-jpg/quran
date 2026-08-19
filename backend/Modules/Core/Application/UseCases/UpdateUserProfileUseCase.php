<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Events\UserProfileUpdated;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\Locale;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * Edits an account's display details — name and preferred locale.
 *
 * NO PE GUARD, and the absence is deliberate. PE-1, PE-3, PE-5 and PE-6 all
 * protect capability: who may act, and whether the platform keeps an
 * administrator. A name is none of those. Adding a guard here would suggest
 * this operation could strand something, and a guard that protects nothing
 * teaches the next reader that guards are decoration.
 *
 * Editing one's own name is therefore allowed, unlike editing one's own roles
 * (PE-3). The two are different acts: one changes what a person is called, the
 * other what they can do.
 *
 * Email is not editable here — see UpdateUserRequest for why.
 */
final class UpdateUserProfileUseCase
{
    public function __construct(
        private UserRepositoryContract $users,
    ) {}

    public function execute(string $userId, string $name, string $locale, ?string $byUserId = null): User
    {
        return DB::transaction(function () use ($userId, $name, $locale, $byUserId): User {
            $user = $this->users->findOrFail(new UserId($userId));

            $before = $user->getName();

            $user->updateProfile($name, new Locale($locale));
            $this->users->save($user);

            // Only when something changed. A log full of entries recording
            // that a form was submitted unaltered buries the ones that matter.
            if ($before !== $user->getName()) {
                event(new UserProfileUpdated(
                    userId: $userId,
                    previousName: $before,
                    name: $user->getName(),
                    byUserId: $byUserId,
                    occurredAt: now()->toIso8601String(),
                ));
            }

            return $user;
        });
    }
}
