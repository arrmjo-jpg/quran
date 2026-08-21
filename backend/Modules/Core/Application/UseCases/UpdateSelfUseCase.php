<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Entities\User;

/**
 * What `PATCH /me` actually does: an account editing itself, across both
 * tables that describe it.
 *
 * THE TRANSACTION LIVES HERE, not in the controller and not in either of the
 * two use cases it calls. One request may touch `users` and `user_profiles`
 * together, and a failure partway must leave neither changed — a caller who
 * renamed themselves and supplied a bad link should not find the rename
 * applied. Putting that guarantee in a controller would make it a property of
 * one entry point rather than of the operation.
 *
 * IT ORCHESTRATES AND DECIDES NOTHING. The account rules stay in
 * UpdateUserProfileUseCase and the profile rules in UpdateSelfProfileUseCase;
 * this only says that for a self edit the two happen together. Both inner use
 * cases remain callable on their own — the admin path still calls the first
 * without ever reaching the second, which is what keeps profile editing out of
 * the administrator's hands.
 *
 * Nested DB::transaction is a savepoint under Laravel, so the inner
 * transaction in UpdateUserProfileUseCase stays correct and the outer one
 * still rolls everything back.
 */
final class UpdateSelfUseCase
{
    public function __construct(
        private UpdateUserProfileUseCase $updateAccount,
        private UpdateSelfProfileUseCase $updateProfile,
    ) {}

    /**
     * @param  array<string, mixed>  $accountChanges  `name` and `preferred_locale`, present only if sent
     * @param  array<string, mixed>  $profileChanges  `display_name`, `bio`, `social_links`, present only if sent
     */
    public function execute(string $userId, array $accountChanges, array $profileChanges): User
    {
        return DB::transaction(function () use ($userId, $accountChanges, $profileChanges): User {
            $user = $this->updateAccount->execute(
                $userId,
                $accountChanges['name'] ?? null,
                $accountChanges['preferred_locale'] ?? null,
                // The actor is the account itself. Editing one's own name is
                // allowed, unlike editing one's own roles, which PE-3 refuses.
                $userId,
            );

            // Called only when the request actually carried profile fields, so
            // renaming yourself does not create an empty profile row for an
            // account that has never had one.
            if ($profileChanges !== []) {
                $this->updateProfile->execute($userId, $profileChanges);
            }

            return $user;
        });
    }
}
